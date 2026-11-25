<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
ql_ensure_tables();

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $cid = (int)($_POST['cid'] ?? 0);
    if ($do === 'create') {
        $opp = row('SELECT id, username FROM users WHERE username = ? AND is_banned = 0', [trim((string)($_POST['username'] ?? ''))]);
        $days = in_array((int)($_POST['days'] ?? 7), [3, 7, 14, 30], true) ? (int)$_POST['days'] : 7;
        if (!$opp || (int)$opp['id'] === $uid) {
            flash('Pick another member by username.', 'err');
        } elseif ((int)val("SELECT COUNT(*) FROM challenges WHERE status IN ('pending','active') AND (challenger_id = ? OR opponent_id = ?)", [$uid, $uid]) >= 5) {
            flash('You have 5 open challenges already.', 'err');
        } else {
            q('INSERT INTO challenges (challenger_id, opponent_id, days, status, created_at) VALUES (?,?,?,?,?)', [$uid, $opp['id'], $days, 'pending', now()]);
            q('INSERT INTO notifications (user_id, actor_id, type, text, created_at) VALUES (?,?,?,?,?)',
              [$opp['id'], $uid, 'quest', display_name($u) . " challenged you to a $days-day points duel. Open Challenges to answer.", now()]);
            flash('Challenge sent to @' . $opp['username'] . '.');
        }
    } elseif (in_array($do, ['accept', 'decline', 'cancel'], true) && $cid) {
        $c = row('SELECT * FROM challenges WHERE id = ?', [$cid]);
        if ($c && $c['status'] === 'pending' && $do === 'accept' && (int)$c['opponent_id'] === $uid) {
            $start = now();
            q("UPDATE challenges SET status = 'active', starts_at = ?, ends_at = ? WHERE id = ?", [$start, date('Y-m-d H:i:s', time() + (int)$c['days'] * 86400), $cid]);
            q('INSERT INTO notifications (user_id, actor_id, type, text, created_at) VALUES (?,?,?,?,?)', [$c['challenger_id'], $uid, 'quest', display_name($u) . ' accepted your challenge. Game on!', now()]);
        } elseif ($c && $c['status'] === 'pending' && in_array($uid, [(int)$c['opponent_id'], (int)$c['challenger_id']], true)) {
            q("UPDATE challenges SET status = 'declined' WHERE id = ?", [$cid]);
        }
    }
    redirect('challenges');
}

challenges_settle();
$list = rows('SELECT c.*, a.username au, a.display_name an, b.username bu, b.display_name bn
              FROM challenges c JOIN users a ON a.id = c.challenger_id JOIN users b ON b.id = c.opponent_id
              WHERE (c.challenger_id = ? OR c.opponent_id = ?) AND c.status <> ? ORDER BY c.id DESC LIMIT 40', [$uid, $uid, 'declined']);
$friends = rows('SELECT us.username, us.display_name FROM follows f JOIN users us ON us.id = f.followee_id WHERE f.follower_id = ? AND us.is_banned = 0 ORDER BY us.username LIMIT 100', [$uid]);

layout_header('Challenges', 'quests');
?>
<h1 class="pagetitle">Challenges</h1>
<p class="muted">Duel a friend: whoever earns the most workout points in the time wins <b><?= CHALLENGE_BONUS ?></b> bonus points (a tie pays both half). Quest and bonus points don't count, only workouts.</p>

<div class="card">
  <h4>Challenge someone</h4>
  <form method="post" class="chalform"><?= csrf_field() ?><input type="hidden" name="do" value="create">
    <label>Username <input name="username" list="friendlist" required maxlength="30" placeholder="e.g. joey"></label>
    <datalist id="friendlist"><?php foreach ($friends as $f): ?><option value="<?= e($f['username']) ?>"><?= e(display_name($f)) ?></option><?php endforeach; ?></datalist>
    <label>Length <select name="days"><option value="3">3 days</option><option value="7" selected>7 days</option><option value="14">14 days</option><option value="30">30 days</option></select></label>
    <button class="btn" type="submit">Send challenge</button>
  </form>
</div>

<?php if (!$list): ?><div class="card center muted">No challenges yet. Pick a friend above.</div><?php endif; ?>
<?php foreach ($list as $c):
    $meIsA = (int)$c['challenger_id'] === $uid;
    $oppName = $meIsA ? ($c['bn'] ?: $c['bu']) : ($c['an'] ?: $c['au']);
    $oppUser = $meIsA ? $c['bu'] : $c['au'];
    $show = in_array($c['status'], ['active', 'done'], true);
    if ($show) {
        $end = $c['status'] === 'done' ? $c['ends_at'] : now();
        $mine = challenge_score($uid, $c['starts_at'], $c['ends_at']);
        $theirs = challenge_score((int)($meIsA ? $c['opponent_id'] : $c['challenger_id']), $c['starts_at'], $c['ends_at']);
        $tot = max(1, $mine + $theirs);
    } ?>
<div class="card chal">
  <div class="chalhead"><b>You vs <a href="<?= e(url('u/' . $oppUser)) ?>"><?= e($oppName) ?></a></b>
    <span class="muted small"><?= (int)$c['days'] ?> days · <?= e($c['status']) ?><?= $c['status'] === 'active' ? ' · ends ' . e(rel_time($c['ends_at'])) : '' ?></span></div>
  <?php if ($show): ?>
    <div class="chalbar"><i style="width:<?= (int)round($mine / $tot * 100) ?>%"></i></div>
    <div class="chalscore"><span><b><?= number_format($mine) ?></b> you</span><span><b><?= number_format($theirs) ?></b> <?= e($oppName) ?></span></div>
    <?php if ($c['status'] === 'done'): ?><p class="small <?= (int)$c['winner_id'] === $uid ? 'ok' : 'muted' ?>"><?= !$c['winner_id'] ? 'Tie.' : ((int)$c['winner_id'] === $uid ? '🏆 You won!' : 'They won this one.') ?></p><?php endif; ?>
  <?php elseif ($c['status'] === 'pending'): ?>
    <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>">
      <?php if (!$meIsA): ?><button class="btn small" name="do" value="accept" type="submit">Accept</button> <button class="btn ghost small" name="do" value="decline" type="submit">Decline</button>
      <?php else: ?><span class="muted small">Waiting for an answer.</span> <button class="btn ghost small" name="do" value="cancel" type="submit">Cancel</button><?php endif; ?></form>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php layout_footer('quests'); ?>
