<?php
declare(strict_types=1);

$log = require APP_ROOT . '/includes/changelog.php';
layout_header('Updates', '', ['index' => true, 'path' => 'updates', 'desc' => 'What is new and fixed on ' . site_name() . ', with the date of every update.']);
$tag = ['new' => 'New', 'fix' => 'Fix', 'improve' => 'Better'];
?>
<h1 class="pagetitle">Updates</h1>
<p class="muted">What we are building and fixing, newest first. Current version <b><?= e(APP_VERSION) ?></b>. Times are Swedish time.</p>
<?php
/* Updates made within 4 hours of each other are shown as one block, so a busy night does not become dozens of rows. */
$groups = [];
foreach ($log as $en) {
    $t = strtotime($en[1]);
    $g = count($groups) - 1;
    if ($g >= 0 && $groups[$g][0][1] && strtotime($groups[$g][0][1]) - $t <= 4 * 3600) {
        $groups[$g][] = $en;
    } else {
        $groups[] = [$en];
    }
}
foreach ($groups as $grp):
    [$ver, $when, $head, , $kind] = $grp[0];
    $t = strtotime($when);
    $oldest = end($grp);
    $pts = [];
    foreach ($grp as $en) { foreach ($en[3] as $p) { $pts[] = $p; } }
    ?>
<div class="card" style="margin-bottom:10px">
  <div class="muted small"><span class="pr"><?= e($tag[$kind] ?? 'Update') ?></span> v<?= e($ver) ?><?= count($grp) > 1 ? ' (with ' . (count($grp) - 1) . ' earlier ' . (count($grp) === 2 ? 'update' : 'updates') . ')' : '' ?> · <?= e(date('D j M Y, H:i', $t)) ?> · <b><?= e(rel_time($when)) ?></b></div>
  <h3 style="margin:6px 0 4px"><?= e($head) ?></h3>
  <ul style="margin:0;padding-left:18px">
    <?php foreach ($pts as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
  </ul>
  <?php if (count($grp) > 1): ?>
  <details style="margin-top:8px"><summary class="muted small">Versions in this block</summary>
    <ul class="small muted" style="margin:6px 0 0;padding-left:18px">
      <?php foreach ($grp as $en): ?><li>v<?= e($en[0]) ?> · <?= e(date('H:i', strtotime($en[1]))) ?> · <?= e($en[2]) ?></li><?php endforeach; ?>
    </ul>
  </details>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php layout_footer(); ?>
