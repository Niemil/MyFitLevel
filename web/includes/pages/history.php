<?php
declare(strict_types=1);

/* Where every point and coin came from. */
$u = require_login();
$uid = (int)$u['id'];
game_ensure();
define('GAME_CSS', true);

$tab = ($_GET['tab'] ?? '') === 'coins' ? 'coins' : 'points';
$wal = game_wallet($uid);

// ---- points by source
$wo = (int)val('SELECT COALESCE(SUM(points),0) FROM workouts WHERE user_id = ?', [$uid]);
$qu = (int)val('SELECT COALESCE(SUM(reward),0) FROM user_quests WHERE user_id = ?', [$uid]);
$qs = (int)val('SELECT COALESCE(SUM(reward),0) FROM quest_steps WHERE user_id = ?', [$uid]);
$ev = event_bonus_total($uid);
$st = game_steps_points($uid);
$sources = ['🏋️ Workouts' => $wo, '🧭 Quests' => $qu + $qs, '⚡ Event bonuses' => $ev, '👟 Steps' => $st];
$totalPts = array_sum($sources);

$entries = [];
if ($tab === 'points') {
    foreach (rows('SELECT performed_at AS t, title, points FROM workouts WHERE user_id = ? ORDER BY performed_at DESC LIMIT 60', [$uid]) as $r) {
        $entries[] = [strtotime((string)$r['t']), '🏋️', 'Workout' . ($r['title'] ? ': ' . $r['title'] : ''), '+' . number_format((int)$r['points']) . ' pts'];
    }
    foreach (rows('SELECT completed_at AS t, code, reward FROM user_quests WHERE user_id = ? ORDER BY completed_at DESC LIMIT 30', [$uid]) as $r) {
        $entries[] = [strtotime((string)$r['t']), '🧭', 'Quest finished: ' . ucwords(str_replace('_', ' ', (string)$r['code'])), '+' . number_format((int)$r['reward']) . ' pts'];
    }
    foreach (rows('SELECT done_at AS t, quest, step, reward FROM quest_steps WHERE user_id = ? ORDER BY done_at DESC LIMIT 30', [$uid]) as $r) {
        $entries[] = [strtotime((string)$r['t']), $r['quest'] === 'event' ? '⚡' : '🧭', ($r['quest'] === 'event' ? 'Event goal' : 'Quest step: ' . ucwords(str_replace('_', ' ', (string)$r['quest']))), '+' . number_format((int)$r['reward']) . ' pts'];
    }
    $per = max(1, (int)game_cfg('steps_per_point'));
    $cap = (int)game_cfg('steps_cap_day');
    foreach (rows('SELECT day, steps FROM daily_steps WHERE user_id = ? ORDER BY day DESC LIMIT 30', [$uid]) as $r) {
        $p = intdiv(min((int)$r['steps'], $cap), $per);
        if ($p > 0) {
            $entries[] = [strtotime($r['day'] . ' 12:00:00'), '👟', number_format((int)$r['steps']) . ' steps', '+' . $p . ' pts'];
        }
    }
} else {
    foreach (rows('SELECT * FROM game_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 150', [$uid]) as $r) {
        $icons = ['workout' => '🏋️', 'boss' => '⚔️', 'chest' => '🎁', 'season' => '🏆', 'buy' => '🛍️', 'sell' => '💱', 'team_gift' => '🏦', 'quest' => '🧭', 'team' => '🏰', 'steps' => '👟', 'catchup' => '🎉'];
        $entries[] = [strtotime((string)$r['created_at']), $icons[$r['kind']] ?? '🪙', ($r['note'] ?: ucwords(str_replace('_', ' ', (string)$r['kind']))), ((int)$r['amount'] > 0 ? '+' : '') . number_format((int)$r['amount']) . ' 🪙'];
    }
}
usort($entries, fn($a, $b) => $b[0] <=> $a[0]);
$entries = array_slice($entries, 0, 80);

$earned = (int)val('SELECT COALESCE(SUM(amount),0) FROM game_ledger WHERE user_id = ? AND amount > 0', [$uid]);
$spent = -(int)val('SELECT COALESCE(SUM(amount),0) FROM game_ledger WHERE user_id = ? AND amount < 0', [$uid]);
$kinds = rows('SELECT kind, SUM(amount) AS s, COUNT(*) AS n FROM game_ledger WHERE user_id = ? GROUP BY kind ORDER BY s DESC', [$uid]);

layout_header('Points & coins', 'me');
?>
<h1 class="pagetitle">📒 Points &amp; coins</h1>
<div class="seg">
  <a class="<?= $tab === 'points' ? 'on' : '' ?>" href="<?= e(url('history')) ?>">⭐ Points</a>
  <a class="<?= $tab === 'coins' ? 'on' : '' ?>" href="<?= e(url('history') . '?tab=coins') ?>">🪙 Coins</a>
</div>

<?php if ($tab === 'points'): ?>
<div class="card">
  <h3>Your <?= number_format($totalPts) ?> points came from</h3>
  <p class="muted small">Walking counts too: every <?= (int)game_cfg('steps_per_point') ?> steps is 1 point, up to <?= number_format((int)game_cfg('steps_cap_day')) ?> steps a day.</p>
  <?php foreach ($sources as $lab => $n): ?>
    <div class="hbar"><div class="hbar-top"><span><?= e($lab) ?></span><b><?= number_format($n) ?></b></div><div class="hbar-track"><i style="width:<?= $totalPts ? max(2, round($n / $totalPts * 100)) : 0 ?>%"></i></div></div>
  <?php endforeach; ?>
  <p class="muted small" style="margin-bottom:0">Boss damage, coins and items do not change your points, only your level-ups come from points.</p>
</div>
<?php else: ?>
<div class="gb-facts">
  <div><b>🪙 <?= number_format((int)$wal['coins']) ?></b><span>balance</span></div>
  <div><b>+<?= number_format($earned) ?></b><span>earned</span></div>
  <div><b>−<?= number_format($spent) ?></b><span>spent</span></div>
</div>
<div class="card">
  <h3>By type</h3>
  <?php foreach ($kinds as $k): ?>
    <div class="gb-row"><span class="grow"><?= e(ucwords(str_replace('_', ' ', (string)$k['kind']))) ?> <small class="muted">(<?= (int)$k['n'] ?>x)</small></span><b><?= ((int)$k['s'] > 0 ? '+' : '') . number_format((int)$k['s']) ?></b></div>
  <?php endforeach; if (!$kinds): ?><p class="muted">Nothing yet. Log a workout to earn your first coins.</p><?php endif; ?>
</div>
<div class="card">
  <h3>How to earn coins</h3>
  <div class="gb-row"><span>🏋️</span><span class="grow">Your first 2 workouts each day</span></div>
  <div class="gb-row"><span>👟</span><span class="grow">Steps: 5,000 / 10,000 / 15,000 in a day</span></div>
  <div class="gb-row"><span>🎁</span><span class="grow">Daily chest on the home page</span></div>
  <div class="gb-row"><span>⚔️</span><span class="grow">Boss kills: prize pool shared by damage</span></div>
  <div class="gb-row"><span>🧭</span><span class="grow">Seasons, team quests and team contests</span></div>
</div>
<?php endif; ?>

<div class="card">
  <h3>Latest</h3>
  <?php foreach ($entries as [$t, $ic, $text, $amt]): ?>
    <div class="gb-row"><span><?= e($ic) ?></span><span class="grow"><?= e($text) ?><br><small class="muted"><?= e(date('M j', $t) . (in_array($ic, ['👟'], true) ? '' : date(' H:i', $t))) ?></small></span><b><?= e($amt) ?></b></div>
  <?php endforeach; if (!$entries): ?><p class="muted">Nothing here yet.</p><?php endif; ?>
</div>
<?php layout_footer(); ?>
