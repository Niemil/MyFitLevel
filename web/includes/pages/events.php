<?php
declare(strict_types=1);

/* Events: bonus-point periods and goal events set up by the admins. */

$now = now();
$active = event_rows('starts_at <= ? AND ends_at > ?', [$now, $now]);
$soon = event_rows('starts_at > ? AND starts_at < ?', [$now, date('Y-m-d H:i:s', time() + 60 * 86400)]);
$past = array_reverse(event_rows('ends_at <= ? AND ends_at > ?', [$now, date('Y-m-d H:i:s', time() - 90 * 86400)]));
$show = function (array $e): void { ?>
  <div class="card">
    <h3><?= $e['kind'] === 'goal' ? '🎯' : '⚡' ?> <?= e($e['title']) ?></h3>
    <p><b><?= e(event_label($e)) ?></b></p>
    <?php if ($e['description'] !== ''): ?><p class="muted"><?= e($e['description']) ?></p><?php endif; ?>
    <p class="muted small"><?= e(date('M j H:i', strtotime($e['starts_at']))) ?> – <?= e(date('M j H:i', strtotime($e['ends_at']))) ?></p>
  </div>
<?php };
define('GAME_CSS', true);
layout_header('Events', '', ['index' => true, 'path' => 'events', 'desc' => 'Bonus point events and goals on ' . site_name() . '.']);
?>
<div class="prose">
  <h1>Events</h1>
  <p class="lead" style="margin-left:0">Now and then there is a bonus period, like a double-points weekend, or a goal to hit together. Bonus points are added automatically to everything you log during the event.</p>
  <h2>Happening now</h2>
  <?php if (!$active): ?><p class="muted">Nothing right now. Your <a href="<?= e(url('goals')) ?>">weekly goal</a> is always on.</p><?php endif; ?>
  <?php foreach ($active as $e) { $show($e); } ?>
  <?php if ($soon): ?><h2>Coming up</h2><?php foreach ($soon as $e) { $show($e); } endif; ?>
  <h2>The year ahead</h2>
  <p class="muted">Seasons run all year. A small boost keeps going through the summer holidays, and there is a Boss Weekend every month.</p>
  <div class="evtiles">
  <?php foreach (array_slice(seasons_upcoming(), 0, 9) as $su): ?>
    <div class="evtile"><span class="ev-ic"><?= $su['icon'] ?></span><b><?= e($su['title']) ?></b><small><?= e(date('M j', $su['start'])) ?> – <?= e(date('M j', $su['end'] - 1)) ?></small><small class="ev-b"><?= e($su['label']) ?></small></div>
  <?php endforeach; ?>
  </div>
  <?php if ($past): ?><h2>Recent</h2><?php foreach ($past as $e) { $show($e); } endif; ?>
</div>
<?php layout_footer(); ?>
