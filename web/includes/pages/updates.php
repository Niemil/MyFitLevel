<?php
declare(strict_types=1);

$days = require APP_ROOT . '/includes/updates_summary.php';
layout_header('Updates', '', ['index' => true, 'path' => 'updates', 'desc' => 'What is new on ' . site_name() . ', day by day.']);
?>
<h1 class="pagetitle">Updates</h1>
<p class="muted">The big things, day by day, newest first. Current version <b><?= e(APP_VERSION) ?></b>.</p>
<?php foreach ($days as [$date, $head, $pts]): ?>
<div class="card" style="margin-bottom:10px">
  <div class="muted small"><?= e(date('D j M Y', strtotime($date))) ?></div>
  <h3 style="margin:6px 0 4px"><?= e($head) ?></h3>
  <ul style="margin:0;padding-left:18px">
    <?php foreach ($pts as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endforeach; ?>
<?php layout_footer(); ?>
