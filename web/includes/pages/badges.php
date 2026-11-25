<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
$earned = array_column(rows('SELECT code, earned_at FROM user_badges WHERE user_id = ?', [$uid]), 'earned_at', 'code');
$stats = user_stats($uid);
$defs = badge_defs();

layout_header('Achievements', 'me');
?>
<h1 class="pagetitle">Achievements <span class="muted">(<?= count($earned) ?>/<?= count($defs) ?>)</span></h1>
<div class="badgegrid">
<?php foreach ($defs as $code => [$name, $desc, $icon, $metric, $target]):
    $has = isset($earned[$code]); ?>
  <div class="card bcard <?= $has ? '' : 'locked' ?>">
    <div class="bicon"><?= $icon ?></div>
    <h4><?= e($name) ?></h4>
    <p class="muted small"><?= e($desc) ?></p>
    <?php if ($has): ?>
      <div class="small ok">Earned <?= e(date('j M Y', strtotime($earned[$code]))) ?></div>
    <?php else: ?>
      <div class="xpbar"><i style="width:<?= (int)min(100, floor(metric_value($stats, $metric) / $target * 100)) ?>%"></i></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php
$ms = array_column(rows('SELECT code, completed_at FROM user_quests WHERE user_id = ?', [$uid]), 'completed_at', 'code');
?>
<h2>Milestones <span class="muted">(<?= count($ms) ?>/<?= count(quest_defs()) ?>)</span></h2>
<p class="muted small">Goals that count up on their own. Each pays bonus points. For step-by-step missions, see <a href="<?= e(url('quests')) ?>">Quests</a>.</p>
<div class="questgrid">
<?php foreach (quest_defs() as $code => [$name, $desc, $metric, $target, $reward]):
    $has = isset($ms[$code]); $pct = (int)min(100, floor(metric_value($stats, $metric) / $target * 100)); ?>
  <div class="card quest <?= $has ? 'doneq' : '' ?>">
    <div class="qtop"><h4><?= $has ? '✅ ' : '' ?><?= e($name) ?></h4><span class="reward">+<?= (int)$reward ?> pts</span></div>
    <p class="muted small"><?= e($desc) ?></p>
    <?php if (!$has): ?><div class="xpbar"><i style="width:<?= $pct ?>%"></i></div><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php layout_footer('me'); ?>
