<?php
declare(strict_types=1);

/* What is boosting you right now, and for how long. */
$u = require_login();
game_ensure();
define('GAME_CSS', true);
$uid = (int)$u['id'];
$w = game_wallet($uid);
$gear = game_gear($uid);
$tm = game_team_of($uid);
$team = $tm ? game_team_bonus((int)$tm['id']) : ['dmg' => 0, 'coin' => 0];
$ev = game_event_bonus();
$n = now();
$ptsEvents = rows("SELECT title, description, pct, ends_at FROM events WHERE kind = 'boost' AND pct > 0 AND starts_at <= ? AND ends_at > ? ORDER BY ends_at", [$n, $n]);
$upcoming = rows('SELECT title, description, starts_at FROM events WHERE starts_at > ? AND starts_at < ? ORDER BY starts_at LIMIT 4', [$n, date('Y-m-d H:i:s', time() + 14 * 86400)]);
$left = static function (?string $t): string {
    if (!$t) { return ''; }
    $s = max(0, strtotime($t) - time());
    return ($s >= 86400 ? intdiv($s, 86400) . 'd ' . intdiv($s % 86400, 3600) . 'h' : ($s >= 3600 ? intdiv($s, 3600) . 'h ' . intdiv($s % 3600, 60) . 'm' : max(1, intdiv($s, 60)) . ' min')) . ' left';
};
$lvlPct = (int)round((int)$u['level'] / 20 * 100);
$potD = game_boost_active($w);
$potC = game_coin_boost_active($w);

$rows = [];
$rows[] = ['Your level', '+' . $lvlPct . '% boss damage', 'Grows with every level. Always on.', ''];
if ($gear['dmg'] > 0 || $gear['coin'] > 0) {
    $rows[] = ['Your gear', ($gear['dmg'] ? '+' . (int)$gear['dmg'] . '% damage ' : '') . ($gear['coin'] ? '+' . (int)$gear['coin'] . '% coins' : ''), 'From items you have equipped in the Shop.', ''];
}
if ($potD) {
    $rows[] = ['Damage potion', '+' . $potD . '% damage', 'Bought in the Shop.', $left($w['boost_until'])];
}
if ($potC) {
    $rows[] = ['Coin potion', '+' . $potC . '% coins', 'Bought in the Shop.', $left($w['coin_boost_until'])];
}
if ($team['dmg'] > 0 || $team['coin'] > 0) {
    $rows[] = ['Your team', ($team['dmg'] ? '+' . (int)$team['dmg'] . '% damage ' : '') . ($team['coin'] ? '+' . (int)$team['coin'] . '% coins' : ''), 'Grows when your team levels up.', ''];
}
foreach ($ev['events'] as $e) {
    $rows[] = [$e['title'], trim(((int)$e['game_dmg_pct'] ? '+' . (int)$e['game_dmg_pct'] . '% damage ' : '') . ((int)$e['game_coin_pct'] ? '+' . (int)$e['game_coin_pct'] . '% coins' : '')), (string)$e['description'], $left($e['ends_at'])];
}
foreach ($ptsEvents as $e) {
    $rows[] = [$e['title'], '+' . (int)$e['pct'] . '% points', (string)$e['description'], $left($e['ends_at'])];
}

layout_header('Boosts', 'quests');
?>
<h1>Boosts</h1>
<p class="muted">Everything that is making your workouts count for more right now.</p>
<div class="card">
  <?php foreach ($rows as $r): ?>
    <div class="gb-row"><span class="grow"><b><?= e($r[0]) ?></b><br><small class="muted"><?= e($r[2]) ?></small></span>
      <span style="text-align:right"><b><?= e($r[1]) ?></b><?php if ($r[3] !== ''): ?><br><small class="muted"><?= e($r[3]) ?></small><?php endif; ?></span></div>
  <?php endforeach; ?>
  <?php if (!$potD): ?><p class="muted small" style="margin-bottom:0">No damage potion active. <a href="<?= e(url('shop')) ?>">Potions are in the Shop.</a></p><?php endif; ?>
</div>
<?php if ($upcoming): ?>
<div class="card">
  <h3>Coming up</h3>
  <?php foreach ($upcoming as $e): ?>
    <div class="gb-row"><span class="grow"><b><?= e($e['title']) ?></b><br><small class="muted"><?= e($e['description']) ?></small></span><span class="muted small">starts <?= e(date('M j', strtotime((string)$e['starts_at']))) ?></span></div>
  <?php endforeach; ?>
</div>
<?php endif;
layout_footer();
