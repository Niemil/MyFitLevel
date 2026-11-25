<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
dm_ensure();
$withName = $args[0] ?? null;
$other = $withName ? row('SELECT id, username, display_name, avatar_token, level FROM users WHERE username = ? AND is_banned = 0', [$withName]) : null;

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    if ($other && (int)$other['id'] !== $uid) {
        $oid = (int)$other['id'];
        if ($do === 'send') {
            $err = dm_send($uid, $oid, (string)($_POST['body'] ?? ''));
            if ($err !== '') {
                flash($err, 'err');
            }
        } elseif ($do === 'block') {
            q('INSERT IGNORE INTO dm_blocks (user_id, blocked_id, created_at) VALUES (?,?,?)', [$uid, $oid, now()]);
            flash('Blocked. They can no longer message you.');
        } elseif ($do === 'unblock') {
            q('DELETE FROM dm_blocks WHERE user_id = ? AND blocked_id = ?', [$uid, $oid]);
            flash('Unblocked.');
        }
        redirect('messages/' . $other['username'] . '#end');
    }
    redirect('messages');
}

define('GAME_CSS', true);
if ($withName && !$other) {
    http_response_code(404);
    layout_header('Messages');
    echo '<div class="card center"><h2>Member not found</h2><a href="' . e(url('messages')) . '">Back to messages</a></div>';
    layout_footer();
    exit;
}

if ($other && (int)$other['id'] !== $uid) {
    $oid = (int)$other['id'];
    q('UPDATE dm_messages SET read_at = ? WHERE to_id = ? AND from_id = ? AND read_at IS NULL', [now(), $uid, $oid]);
    q("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND actor_id = ? AND type = 'dm'", [$uid, $oid]);
    $msgs = array_reverse(rows('SELECT * FROM dm_messages WHERE (from_id = ? AND to_id = ?) OR (from_id = ? AND to_id = ?) ORDER BY id DESC LIMIT 80', [$uid, $oid, $oid, $uid]));
    $iBlocked = dm_blocked($uid, $oid);
    layout_header('Messages with ' . display_name($other));
    ?>
<div class="dm-head card">
  <a href="<?= e(url('messages')) ?>" class="muted">← All</a>
  <a class="dm-who" href="<?= e(url('u/' . $other['username'])) ?>"><?= uavatar($other, 40) ?> <b><?= e(display_name($other)) ?></b> <?= level_chip((int)$other['level']) ?></a>
  <form method="post" class="inl" data-confirm="<?= $iBlocked ? 'Unblock this member?' : 'Block this member? They will not be able to message you.' ?>"><?= csrf_field() ?>
    <input type="hidden" name="do" value="<?= $iBlocked ? 'unblock' : 'block' ?>"><button class="btn ghost small" type="submit"><?= $iBlocked ? 'Unblock' : 'Block' ?></button></form>
</div>
<div class="dm-thread">
  <?php if (!$msgs): ?><p class="muted center">No messages yet. Say hi 👋</p><?php endif; ?>
  <?php foreach ($msgs as $m): $mine = (int)$m['from_id'] === $uid; ?>
    <div class="dm-b <?= $mine ? 'mine' : 'theirs' ?>"><?= nl2br(e((string)$m['body'])) ?><small><?= e(rel_time($m['created_at'])) ?><?= $mine && $m['read_at'] ? ' · seen' : '' ?></small></div>
  <?php endforeach; ?>
  <span id="end"></span>
</div>
<form method="post" class="dm-form card"><?= csrf_field() ?><input type="hidden" name="do" value="send">
  <textarea name="body" rows="2" maxlength="1000" placeholder="Write a message…" required></textarea>
  <button class="btn" type="submit">Send</button>
</form>
<?php
    layout_footer();
    exit;
}

$last = rows('SELECT m.*, CASE WHEN m.from_id = ? THEN m.to_id ELSE m.from_id END AS other_id FROM dm_messages m
              WHERE m.id IN (SELECT MAX(id) FROM dm_messages WHERE from_id = ? OR to_id = ? GROUP BY LEAST(from_id, to_id), GREATEST(from_id, to_id))
              ORDER BY m.id DESC LIMIT 50', [$uid, $uid, $uid]);
$unread = array_column(rows('SELECT from_id, COUNT(*) c FROM dm_messages WHERE to_id = ? AND read_at IS NULL GROUP BY from_id', [$uid]), 'c', 'from_id');
$users = [];
if ($last) {
    $ids = array_values(array_unique(array_map(fn($m) => (int)$m['other_id'], $last)));
    foreach (rows('SELECT id, username, display_name, avatar_token, level FROM users WHERE id IN (' . in_clause($ids) . ')', $ids) as $r) {
        $users[(int)$r['id']] = $r;
    }
}
layout_header('Messages');
?>
<h1 class="pagetitle">Messages</h1>
<div class="card">
  <?php if (!$last): ?><p class="muted center">No conversations yet. Open a member's profile and tap <b>Message</b>.</p><?php endif; ?>
  <?php foreach ($last as $m): $o = $users[(int)$m['other_id']] ?? null; if (!$o) { continue; } $n = (int)($unread[(int)$o['id']] ?? 0); ?>
    <a class="dm-row<?= $n ? ' unread' : '' ?>" href="<?= e(url('messages/' . $o['username'])) ?>">
      <?= uavatar($o, 44) ?>
      <span class="grow"><b><?= e(display_name($o)) ?></b><small class="muted"><?= (int)$m['from_id'] === $uid ? 'You: ' : '' ?><?= e(mb_strimwidth(str_replace("\n", ' ', (string)$m['body']), 0, 70, '…')) ?></small></span>
      <span class="muted small"><?= e(rel_time($m['created_at'])) ?></span><?php if ($n): ?><span class="dot"><?= $n ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<p class="muted small">Messages are private between the two of you. You can block anyone from the conversation page.</p>
<?php layout_footer(); ?>
