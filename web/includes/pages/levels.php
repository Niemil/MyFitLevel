<?php
declare(strict_types=1);

$u = me();
$table = level_table();
$scale = level_scale();
$mine = $u ? (int)$u['level'] : 0;
layout_header('Level chart', '', ['index' => true, 'path' => 'levels', 'desc' => 'How many points each level takes on ' . site_name() . ', with a chart of the whole level curve.']);
?>
<div class="prose">
  <h1>Level chart</h1>
  <p class="lead" style="margin-left:0">100 levels. The first ones come quickly, then every level takes a bit more time than the last. An active member who trains three or four times a week, walks daily and collects weekly goals, quests and seasonal boosts reaches level 50 in about a year and level 100 in about three. Taking it easier just means a longer road. Each ten levels or so you also get a new rank name.</p>
  <div class="card"><?= chart_levels($table, $mine) ?></div>
  <div class="card tablewrap">
    <table class="lvltable">
      <tr><th>Level</th><th>Rank</th><th>Total points</th><th>From previous</th></tr>
      <?php $prev = 0; foreach ($table as $lv => $v): $pts = (int)round($v * $scale); ?>
        <tr class="<?= $lv === $mine ? 'me' : '' ?>"><td><?= level_chip($lv) ?></td><td><?= $lv === 1 || level_title($lv) !== level_title($lv - 1) ? e(level_title($lv)) : '' ?></td><td><?= number_format($pts) ?></td><td><?= $lv > 1 ? '+' . number_format($pts - $prev) : '' ?></td></tr>
      <?php $prev = $pts; endforeach; ?>
    </table>
  </div>
  <p class="muted small">See <a href="<?= e(url('points')) ?>">how points work</a> for the per-set formula.</p>
</div>
<?php layout_footer(); ?>
