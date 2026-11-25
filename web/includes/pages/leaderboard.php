<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
$range = $_GET['range'] ?? 'week';
if (!in_array($range, ['week', 'month', 'all'], true)) {
    $range = 'week';
}

if ($range === 'all') {
    $list = rows('SELECT id, username, display_name, level, xp AS pts FROM users WHERE is_banned = 0 AND xp > 0 ORDER BY xp DESC LIMIT 50');
} else {
    $since = $range === 'week' ? date('Y-m-d 00:00:00', strtotime('monday this week')) : date('Y-m-01 00:00:00');
    $list = rows('SELECT us.id, us.username, us.display_name, us.avatar_token, us.level, SUM(w.points) pts
                  FROM workouts w JOIN users us ON us.id = w.user_id
                  WHERE us.is_banned = 0 AND w.performed_at >= ?
                  GROUP BY us.id, us.username, us.display_name, us.avatar_token, us.level
                  ORDER BY pts DESC LIMIT 50', [$since]);
}

layout_header('Leaderboard', 'rank');
?>
<h1 class="pagetitle">Leaderboard</h1>
<div class="tabs">
  <a class="<?= $range === 'week' ? 'on' : '' ?>" href="<?= e(url('leaderboard?range=week')) ?>">This week</a>
  <a class="<?= $range === 'month' ? 'on' : '' ?>" href="<?= e(url('leaderboard?range=month')) ?>">This month</a>
  <a class="<?= $range === 'all' ? 'on' : '' ?>" href="<?= e(url('leaderboard?range=all')) ?>">All time</a>
</div>
<div class="card">
  <?php if (!$list): ?><p class="muted center">Nobody has scored yet. Be the first!</p><?php endif; ?>
  <?php foreach ($list as $i => $r): ?>
    <div class="person rankrow <?= (int)$r['id'] === $uid ? 'meRow' : '' ?>">
      <div class="rank"><?= $i < 3 ? ['🥇', '🥈', '🥉'][$i] : ($i + 1) ?></div>
      <a href="<?= e(url('u/' . $r['username'])) ?>"><?= uavatar($r, 36) ?></a>
      <div class="grow"><a class="uname" href="<?= e(url('u/' . $r['username'])) ?>"><?= e(display_name($r)) ?></a> <?= level_chip((int)$r['level']) ?><?= role_badge($r) ?></div>
      <b><?= number_format((int)$r['pts']) ?> <small class="muted">pts</small></b>
    </div>
  <?php endforeach; ?>
</div>
<?php layout_footer('rank'); ?>
