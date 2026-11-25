<?php
declare(strict_types=1);

$u = require_login();
if (!is_staff($u)) {
    http_response_code(403);
    exit('Forbidden');
}
$uid = (int)$u['id'];
$tab = in_array($_GET['tab'] ?? '', ['reports', 'members', 'log'], true) ? $_GET['tab'] : 'reports';

/** Short text + owner for a reported thing. */
function report_target(string $kind, int $id): ?array
{
    switch ($kind) {
        case 'post':
            $r = row('SELECT p.body, p.user_id, us.username FROM posts p JOIN users us ON us.id = p.user_id WHERE p.id = ?', [$id]);
            return $r ? ['text' => $r['body'] ?: '(photo post)', 'uid' => (int)$r['user_id'], 'user' => $r['username'], 'link' => 'post/' . $id] : null;
        case 'workout':
            $r = row('SELECT w.title, w.notes, w.user_id, us.username FROM workouts w JOIN users us ON us.id = w.user_id WHERE w.id = ?', [$id]);
            return $r ? ['text' => trim(($r['title'] ?? '') . ' ' . ($r['notes'] ?? '')) ?: '(workout)', 'uid' => (int)$r['user_id'], 'user' => $r['username'], 'link' => 'workout/' . $id] : null;
        case 'comment':
            $r = row('SELECT c.body, c.user_id, c.workout_id, us.username FROM comments c JOIN users us ON us.id = c.user_id WHERE c.id = ?', [$id]);
            return $r ? ['text' => $r['body'], 'uid' => (int)$r['user_id'], 'user' => $r['username'], 'link' => 'workout/' . $r['workout_id']] : null;
        case 'pcomment':
            $r = row('SELECT c.body, c.user_id, c.post_id, us.username FROM post_comments c JOIN users us ON us.id = c.user_id WHERE c.id = ?', [$id]);
            return $r ? ['text' => $r['body'], 'uid' => (int)$r['user_id'], 'user' => $r['username'], 'link' => 'post/' . $r['post_id']] : null;
        case 'user':
            $r = row('SELECT id, username, bio FROM users WHERE id = ?', [$id]);
            return $r ? ['text' => $r['bio'] ?: '(profile)', 'uid' => (int)$r['id'], 'user' => $r['username'], 'link' => 'u/' . $r['username']] : null;
    }
    return null;
}

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $rid = (int)($_POST['report_id'] ?? 0);

    if ($do === 'dismiss' && $rid) {
        q("UPDATE reports SET status = 'dismissed', handled_by = ? WHERE id = ?", [$uid, $rid]);
        mod_log($uid, 'dismiss report', null, '#' . $rid);
        flash('Report dismissed.');
    } elseif ($do === 'remove' && $rid) {
        $rp = row('SELECT * FROM reports WHERE id = ?', [$rid]);
        if ($rp) {
            $t = report_target($rp['kind'], (int)$rp['target_id']);
            match ($rp['kind']) {
                'post' => (function () use ($rp) {
                    foreach (rows('SELECT * FROM images WHERE post_id = ?', [$rp['target_id']]) as $im) { image_delete($im); }
                    q('DELETE FROM posts WHERE id = ?', [$rp['target_id']]);
                })(),
                'workout' => q('DELETE FROM workouts WHERE id = ?', [$rp['target_id']]),
                'comment' => q('DELETE FROM comments WHERE id = ?', [$rp['target_id']]),
                'pcomment' => q('DELETE FROM post_comments WHERE id = ?', [$rp['target_id']]),
                default => null,
            };
            q("UPDATE reports SET status = 'done', handled_by = ? WHERE kind = ? AND target_id = ?", [$uid, $rp['kind'], $rp['target_id']]);
            mod_log($uid, 'remove ' . $rp['kind'], $t['uid'] ?? null, mb_substr($t['text'] ?? '', 0, 120));
            flash('Removed.');
        }
    } elseif ($do === 'suspend') {
        $days = (string)($_POST['days'] ?? '');
        $dn = $days === 'perm' ? null : max(1, min(365, (int)$days));
        $err = ban_user($u, (int)($_POST['user_id'] ?? 0), $dn, trim((string)($_POST['reason'] ?? '')));
        flash($err ?: 'Member suspended.', $err ? 'err' : 'ok');
        if (!$err && $rid) {
            q("UPDATE reports SET status = 'done', handled_by = ? WHERE id = ?", [$uid, $rid]);
        }
    } elseif ($do === 'unsuspend') {
        $tid = (int)($_POST['user_id'] ?? 0);
        q('UPDATE users SET is_banned = 0, banned_until = NULL, ban_reason = NULL WHERE id = ?', [$tid]);
        mod_log($uid, 'unsuspend', $tid);
        flash('Member restored.');
    }
    redirect('mod?tab=' . $tab);
}

function suspend_form(int $userId, int $reportId = 0): void
{
    ?>
<form method="post" class="inl modsusp"><?= csrf_field() ?>
  <input type="hidden" name="do" value="suspend"><input type="hidden" name="user_id" value="<?= $userId ?>"><input type="hidden" name="report_id" value="<?= $reportId ?>">
  <select name="days"><option value="1">1 day</option><option value="7">7 days</option><option value="30">30 days</option><option value="perm">Permanent</option></select>
  <input name="reason" maxlength="200" placeholder="Reason (shown to the member)">
  <button class="btn small danger" type="submit">Suspend</button>
</form>
<?php
}

layout_header('Moderation', 'mod');
?>
<div class="narrowcol">
<h1 class="pagetitle">Moderation</h1>
<div class="tabs">
  <a class="<?= $tab === 'reports' ? 'on' : '' ?>" href="<?= e(url('mod?tab=reports')) ?>">Reports</a>
  <a class="<?= $tab === 'members' ? 'on' : '' ?>" href="<?= e(url('mod?tab=members')) ?>">Members</a>
  <a class="<?= $tab === 'log' ? 'on' : '' ?>" href="<?= e(url('mod?tab=log')) ?>">Log</a>
</div>

<?php if ($tab === 'reports'):
    $reps = rows("SELECT r.*, rp.username AS reporter FROM reports r JOIN users rp ON rp.id = r.reporter_id WHERE r.status = 'open' ORDER BY r.id DESC LIMIT 60"); ?>
  <?php if (!$reps): ?><div class="card center muted">No open reports. 🎉</div><?php endif; ?>
  <?php foreach ($reps as $r): $t = report_target($r['kind'], (int)$r['target_id']); ?>
  <div class="card">
    <div class="small muted">#<?= (int)$r['id'] ?> · <?= e($r['kind']) ?> · <?= e(REPORT_REASONS[$r['reason']] ?? $r['reason']) ?> · reported by @<?= e($r['reporter']) ?> · <?= e(rel_time($r['created_at'])) ?></div>
    <?php if (!$t): ?>
      <p class="muted">The content is gone already.</p>
      <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>"><button class="btn small ghost" name="do" value="dismiss">Close</button></form>
    <?php else: ?>
      <p class="modquote"><?= nl2br(e(mb_substr($t['text'], 0, 400))) ?></p>
      <div class="small">by <a href="<?= e(url('u/' . $t['user'])) ?>">@<?= e($t['user']) ?></a> · <a href="<?= e(url($t['link'])) ?>" target="_blank" rel="noopener">open</a></div>
      <div class="modact">
        <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
          <button class="btn small ghost" name="do" value="dismiss">Dismiss</button>
          <?php if ($r['kind'] !== 'user'): ?><button class="btn small ghost danger" name="do" value="remove" data-confirm="Remove this content?">Remove content</button><?php endif; ?>
        </form>
        <?php suspend_form($t['uid'], (int)$r['id']); ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

<?php elseif ($tab === 'members'):
    $q = trim((string)($_GET['q'] ?? ''));
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $ms = $q !== ''
        ? rows('SELECT * FROM users WHERE username LIKE ? OR display_name LIKE ? ORDER BY id DESC LIMIT 30', [$like, $like])
        : rows('SELECT * FROM users WHERE is_banned = 1 ORDER BY id DESC LIMIT 30'); ?>
  <form method="get" class="commentform"><input type="hidden" name="tab" value="members">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Find a member…" maxlength="40"><button class="btn small">Search</button></form>
  <p class="muted small"><?= $q === '' ? 'Currently suspended members:' : 'Results:' ?></p>
  <?php if (!$ms): ?><div class="card muted center">Nobody here.</div><?php endif; ?>
  <?php foreach ($ms as $m): ?>
  <div class="card">
    <div><a href="<?= e(url('u/' . $m['username'])) ?>"><b>@<?= e($m['username']) ?></b></a> <?= role_badge($m) ?>
      <?php if ($m['is_banned']): ?><span class="pr bad">suspended<?= $m['banned_until'] ? ' until ' . e(date('j M H:i', strtotime($m['banned_until']))) : ' (permanent)' ?></span><?php endif; ?></div>
    <?php if ($m['ban_reason']): ?><div class="small muted"><?= e($m['ban_reason']) ?></div><?php endif; ?>
    <div class="modact">
      <?php if ($m['is_banned']): ?>
        <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>"><button class="btn small ghost" name="do" value="unsuspend">Restore</button></form>
      <?php elseif ((int)$m['id'] !== $uid && !$m['is_admin']): suspend_form((int)$m['id']); endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

<?php else:
    $log = rows('SELECT l.*, a.username AS actor, t.username AS target FROM mod_log l JOIN users a ON a.id = l.actor_id LEFT JOIN users t ON t.id = l.target_user_id ORDER BY l.id DESC LIMIT 100'); ?>
  <?php if (!$log): ?><div class="card muted center">Nothing logged yet.</div><?php endif; ?>
  <?php foreach ($log as $l): ?>
  <div class="card small"><b>@<?= e($l['actor']) ?></b> · <?= e($l['action']) ?><?= $l['target'] ? ' · @' . e($l['target']) : '' ?>
    <span class="muted">· <?= e(rel_time($l['created_at'])) ?></span><?= $l['detail'] !== '' ? '<div class="muted">' . e($l['detail']) . '</div>' : '' ?></div>
  <?php endforeach; ?>
<?php endif; ?>
</div>
<?php layout_footer('mod'); ?>
