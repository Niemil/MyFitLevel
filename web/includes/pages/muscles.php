<?php
declare(strict_types=1);

/* Muscle levels: every muscle levels up from the sets you log. Training many body areas in a week boosts boss damage. */
$u = require_login();
$uid = (int)$u['id'];
$ms = muscle_stats($uid);
$bal = muscle_balance($uid);
$names = muscle_regions();
$order = array_keys($names);
usort($order, fn($a, $b) => [$ms['lv'][$b], $ms['xp'][$b]] <=> [$ms['lv'][$a], $ms['xp'][$a]]);
$missing = array_keys(array_filter($bal['areas'], fn($v) => !$v));
$weak = end($order);
layout_header('Muscle levels', 'me');
?>
<h1 class="pagetitle">Muscle levels</h1>
<p class="muted">Every working set gives XP to the muscles it trains: 10 XP for the main muscle and 5 for the helpers. Level 1 takes 2 sets, level 5 about 50 and level 10 about 200.</p>
<div class="card">
  <h3>This week</h3>
  <p class="small muted">Train a body area in the last 7 days and your boss hits get +2%. All six areas gives +12%.</p>
  <div class="ml-areas">
    <?php foreach ($bal['areas'] as $a => $on): ?><span class="ml-area <?= $on ? 'on' : '' ?>"><?= e($a) ?><small><?= $on ? 'done' : 'not yet' ?></small></span><?php endforeach; ?>
  </div>
  <p style="margin:.8rem 0 0"><b>Bonus now: +<?= (int)$bal['bonus'] ?>% boss damage</b><?= $missing ? '<span class="muted small"> · still open: ' . e(implode(', ', $missing)) . '</span>' : '' ?></p>
  <p class="small muted" style="margin-bottom:0">Full body weeks so far: <b><?= (int)$ms['full_weeks'] ?></b>. They count towards achievements.</p>
</div>
<div class="card">
  <h3>Your muscles</h3>
  <?php foreach ($order as $m):
      $lv = $ms['lv'][$m]; $xp = $ms['xp'][$m]; $lo = muscle_xp_for($lv); $hi = muscle_xp_for($lv + 1);
      $pct = (int)round(($xp - $lo) / max(1, $hi - $lo) * 100); ?>
  <div class="ml-row">
    <div class="ml-top"><b><?= e($names[$m]) ?></b><span>Level <?= $lv ?></span></div>
    <div class="ml-bar"><i style="width:<?= max(0, min(100, $pct)) ?>%"></i></div>
    <div class="small muted"><?= number_format((int)$xp) ?> XP · <?= number_format($hi - (int)$xp) ?> to level <?= $lv + 1 ?></div>
  </div>
  <?php endforeach; ?>
  <?php if ($ms['xp'][$weak] < 20): ?><p class="small muted" style="margin-bottom:0">Not started yet: <?= e($names[$weak]) ?>. Pick an exercise for it next time.</p><?php endif; ?>
</div>
<p class="small muted">See the heat map and balance in <a href="<?= e(url('insights')) ?>">Insights</a>. New achievements for muscle levels are on the <a href="<?= e(url('badges')) ?>">achievements</a> page.</p>
<?php layout_footer(); ?>
