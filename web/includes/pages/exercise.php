<?php
declare(strict_types=1);

$name = site_name();
$ex = row('SELECT * FROM exercises WHERE slug = ? AND active = 1', [$args[0]]);
if (!$ex) {
    http_response_code(404);
    layout_header('Exercise not found');
    echo '<div class="card center">' . mascot('oops', 110) . '<h2>Exercise not found</h2><a class="btn" href="' . e(url('exercises')) . '">Exercise library</a></div>';
    layout_footer();
    exit;
}
$labels = ['chest' => 'chest', 'back' => 'back', 'shoulders' => 'shoulders', 'biceps' => 'biceps', 'triceps' => 'triceps', 'legs' => 'legs and glutes', 'core' => 'core', 'fullbody' => 'full body', 'cardio' => 'cardio'];
$mu = $labels[$ex['muscle']] ?? $ex['muscle'];
$equip = $ex['equipment'] && $ex['equipment'] !== 'any' ? $ex['equipment'] : '';
$related = rows('SELECT slug, name FROM exercises WHERE active = 1 AND muscle = ? AND id <> ? ORDER BY RAND() LIMIT 8', [$ex['muscle'], $ex['id']]);

switch ($ex['kind']) {
    case 'weight':
        $kg = ($ex['equipment']==='dumbbell'||(int)$ex['per_side']===1) ? 20.0 : 60.0; $pts = (int)round((float)$ex['coef'] * (($kg * (($ex['equipment']==='dumbbell'||(int)$ex['per_side']===1)?2:1) * (1 + 8/30)) ** LOAD_EXP) * POINTS_SCALE);
        $how = 'Strength sets earn points from the estimated one-rep max and the reps. For example, ' . (int)$kg . ' kg' . ($kg < 30 ? ' per hand' : '') . ' × 8 reps is worth about ' . $pts . ' points here.';
        break;
    case 'bodyweight':
        $eff = 75 * (float)$ex['bw_pct']; $pts = (int)round((float)$ex['coef'] * (($eff * (1 + 10/30)) ** LOAD_EXP) * POINTS_SCALE);
        $how = 'This counts a share of your bodyweight, plus any weight you add. For a 75 kg person, 10 reps is worth about ' . $pts . ' points.';
        break;
    case 'cardio':
        $ref = cardio_ref_kmh($ex['name']);
        $pts = (int)round(cardio_points($ex, 1800, (float)$ex['dist_coef'] > 0 ? (int)round($ref * 500) : 0));
        $how = (float)$ex['dist_coef'] > 0
            ? 'Cardio scores distance and pace: longer and faster pays more than proportionally. 30 minutes at a typical pace (' . rtrim(rtrim(number_format($ref, 1), '0'), '.') . ' km/h) is worth about ' . $pts . ' points.'
            : 'Cardio earns points per minute. 30 minutes is worth about ' . $pts . ' points.';
        break;
    default:
        $pts = (int)round((float)$ex['coef'] * 2);
        $how = 'Timed exercises earn points per minute. Holding it for 2 minutes is worth about ' . $pts . ' points.';
}
$title = $ex['name'] . ': muscles, equipment & points';
layout_header($title, '', [
    'index' => true, 'path' => 'exercises/' . $ex['slug'], 'full_title' => false,
    'desc' => $ex['name'] . ' targets your ' . $mu . ($equip ? ' using ' . $equip : '') . '. Log it in ' . $name . ' and earn points. ' . $how,
]);
?>
<div class="prose">
  <p class="small"><a href="<?= e(url('exercises')) ?>">← Exercise library</a></p>
  <h1><?= e($ex['name']) ?></h1>
  <div class="card">
    <div class="kv"><span>Main area</span><b style="text-transform:capitalize"><?= e($mu) ?></b></div>
    <div class="kv"><span>Level</span><b><?= tier_chip(exercise_tier($ex)) ?></b></div>
    <?php if ($equip): ?><div class="kv"><span>Equipment</span><b style="text-transform:capitalize"><?= e($equip) ?></b></div><?php endif; ?>
    <?php if ($ex['subs'] !== ''): ?><div class="kv"><span>Muscles worked</span><b><?= e(implode(', ', array_map(fn($m) => muscle_regions()[$m] ?? $m, explode(',', $ex['subs'])))) ?></b></div><?php endif; ?>
    <?php if ((int)$ex['per_side'] === 1): ?><div class="kv"><span>Weight</span><b>Per hand (one dumbbell)</b></div><?php elseif ((int)$ex['per_side'] === 2): ?><div class="kv"><span>Weight</span><b>Per side (one cable stack)</b></div><?php endif; ?>
    <div class="kv"><span>Type</span><b><?= e(['weight' => 'Weighted (weight × reps)', 'bodyweight' => 'Bodyweight (reps)', 'cardio' => 'Cardio (time + distance)', 'duration' => 'Timed hold'][$ex['kind']]) ?></b></div>
  </div>
  <?php $guide = exercise_guide($ex['name']); ?>
  <h2>How to do <?= e($ex['name']) ?></h2>
  <?php if ($guide): ?>
    <ol class="guidesteps"><?php foreach ($guide as $st): ?><li><?= e($st) ?></li><?php endforeach; ?></ol>
    <p class="muted small">Instructions from the public-domain <a href="https://github.com/yuhonas/free-exercise-db" rel="noopener">Free Exercise DB</a>. Start light and ask a qualified coach if unsure.</p>
  <?php else: ?>
    <p>New to this one? Watch a short video to learn the movement before you load it up.</p>
  <?php endif; ?>
  <p><a class="btn ghost small" href="<?= e(exercise_video_url($ex['name'])) ?>" target="_blank" rel="noopener noreferrer">▶ Find a how-to video</a></p>
  <h2>points for <?= e($ex['name']) ?></h2>
  <p><?= e($how) ?> <a href="<?= e(url('points')) ?>">See how points works</a>.</p>
  <p><?php if (me()): ?><a class="btn" href="<?= e(url('log')) ?>">Track a workout</a><?php else: ?><a class="btn" href="<?= e(url('register')) ?>">Join free and log it</a><?php endif; ?></p>
  <?php if ($related): ?>
  <h2>More <?= e($mu) ?> exercises</h2>
  <div class="chips">
    <?php foreach ($related as $r): ?><a class="chip" href="<?= e(url('exercises/' . $r['slug'])) ?>"><?= e($r['name']) ?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>
