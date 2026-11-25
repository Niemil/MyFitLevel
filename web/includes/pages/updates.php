<?php
declare(strict_types=1);

$log = require APP_ROOT . '/includes/changelog.php';
layout_header('Updates', '', ['index' => true, 'path' => 'updates', 'desc' => 'What is new and fixed on ' . site_name() . ', with the date of every update.']);
$tag = ['new' => 'New', 'fix' => 'Fix', 'improve' => 'Better'];
?>
<h1 class="pagetitle">Updates</h1>
<p class="muted">What we are building and fixing, newest first. Current version <b><?= e(APP_VERSION) ?></b>. Times are Swedish time.</p>
<?php foreach ($log as $en): [$ver, $when, $head, $pts, $kind] = $en; $t = strtotime($when); ?>
<div class="card" style="margin-bottom:10px">
  <div class="muted small"><span class="pr"><?= e($tag[$kind] ?? 'Update') ?></span> v<?= e($ver) ?> · <?= e(date('D j M Y, H:i', $t)) ?> · <b><?= e(rel_time($when)) ?></b></div>
  <h3 style="margin:6px 0 4px"><?= e($head) ?></h3>
  <ul style="margin:0;padding-left:18px">
    <?php foreach ($pts as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endforeach; ?>
<?php layout_footer(); ?>
