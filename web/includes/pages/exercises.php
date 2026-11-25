<?php
declare(strict_types=1);

$name = site_name();
$all = rows('SELECT slug, name, muscle, equipment, kind FROM exercises WHERE active = 1 ORDER BY muscle, name');
$lvl = isset(TIER_LABELS[$_GET['level'] ?? '']) ? (string)$_GET['level'] : '';
foreach ($all as &$ax) { $ax['tier'] = exercise_tier($ax); }
unset($ax);
$totalAll = count($all);
if ($lvl !== '') { $all = array_values(array_filter($all, static fn($x) => $x['tier'] === $lvl)); }
$by = [];
foreach ($all as $x) {
    $by[$x['muscle']][] = $x;
}
$order = ['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'core', 'fullbody', 'cardio'];
uksort($by, fn($a, $b) => (array_search($a, $order) === false ? 99 : array_search($a, $order)) <=> (array_search($b, $order) === false ? 99 : array_search($b, $order)));
$labels = ['chest' => 'Chest', 'back' => 'Back', 'shoulders' => 'Shoulders', 'biceps' => 'Biceps', 'triceps' => 'Triceps', 'legs' => 'Legs & glutes', 'core' => 'Core', 'fullbody' => 'Full body', 'cardio' => 'Cardio, outdoors & sports'];

layout_header('Exercise library', '', [
    'index' => true, 'path' => 'exercises',
    'desc' => 'Browse ' . $totalAll . ' exercises: gym lifts, bodyweight moves, cardio, walks, hikes, yoga and sports. Log them in ' . $name . ' and earn points.',
]);
?>
<div class="prose" style="max-width:none">
  <h1>Exercise library</h1>
  <p class="lead" style="margin-left:0"><?= $totalAll ?> exercises across the gym, outdoors and at home. Every one earns points when you log it.</p>
  <div class="chips" style="margin:8px 0 14px"><a class="chip<?= $lvl === '' ? ' on' : '' ?>" href="<?= e(url('exercises')) ?>">All levels</a>
    <?php foreach (TIER_LABELS as $tk => $tl): ?><a class="chip<?= $lvl === $tk ? ' on' : '' ?>" href="<?= e(url('exercises?level=' . $tk)) ?>"><?= e($tl) ?></a><?php endforeach; ?></div>
  <?php foreach ($by as $m => $list): ?>
    <h2><?= e($labels[$m] ?? ucfirst($m)) ?> <small class="muted">(<?= count($list) ?>)</small></h2>
    <div class="exgrid">
      <?php foreach ($list as $x): ?>
        <a class="card" href="<?= e(url('exercises/' . $x['slug'])) ?>"><b><?= e($x['name']) ?></b><br><small><?= e($x['equipment'] ?: 'any') ?></small> <?= tier_chip($x['tier']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php layout_footer(); ?>
