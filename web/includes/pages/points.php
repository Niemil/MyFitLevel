<?php
declare(strict_types=1);

$name = site_name();
$sample = rows("SELECT name, kind, coef, bw_pct, equipment, per_side FROM exercises WHERE slug IN ('back-squat','barbell-bench-press','deadlift','overhead-press','barbell-row','pull-up') AND active = 1");

layout_header('How points (points) work', '', [
    'index' => true, 'path' => 'points',
    'desc' => 'How ' . $name . ' calculates points for every set: coefficient × estimated 1RM^1.3. Heavier lifts pay more than proportionally, extra reps add only a little.',
]);
?>
<div class="prose">
  <h1>How points work</h1>
  <p class="lead" style="margin-left:0">Every set you log earns points. Heavier and harder work pays more, but a pile of easy reps never beats a heavy set. The model is our own and is built on public, citable exercise science.</p>

  <h2>Strength sets</h2>
  <div class="formula">points = coefficient × e1RM<sup>1.3</sup> × 0.2085 (e1RM in kg)</div>
  <ul>
    <li><b>e1RM</b> is your estimated one-rep max for the set: <code>load × (1 + reps ÷ 30)</code>, the widely used Epley equation.</li>
    <li><b>Strength pays more than linearly.</b> The e1RM is raised to the power 1.3, so a stronger lifter earns noticeably more per set, and strong people level faster. Reps count through the e1RM (counted up to 12 reps, where the estimate stays reliable), so a set of ten earns somewhat more than a set of one at the same weight, but nowhere near ten times as much. More sets are how you earn more.</li>
    <li><b>Coefficient = movement × equipment.</b> The barbell squat and deadlift count 1.15, other big compound lifts (bench and overhead press, rows, pull-ups, dips, lunges) 1.00, other compound moves 0.80, isolation moves (curls, raises, extensions, calves) 0.60. Free weights keep that value; cables ×0.90, Smith machine ×0.90 and fixed machines ×0.80, since a guided path asks less of your stabilising muscles.</li>
    <li><b>Dumbbells: log one dumbbell.</b> The site counts both hands for you, so 20 kg per hand is scored as 40 kg of work.</li>
    <li><b>Bodyweight moves</b> (push-ups, pull-ups, dips) use a share of your bodyweight plus any weight you add.</li>
    <li><b>Warm-up sets</b> (mark a set with W) earn half points and never count as a personal record or a working set.</li>
    <li><b>Personal records</b> give a bonus of <?= (int)PR_BONUS ?> pts when you beat your best estimated one-rep max for that exercise.</li>
  </ul>

  <?php if ($sample): ?>
  <div class="card">
    <h4>100 kg × 5 reps (60 kg × 8 for the bodyweight lifts, 75 kg body)</h4>
    <table class="cmp">
      <thead><tr><th>Exercise</th><th>Coef.</th><th>pts</th></tr></thead>
      <tbody>
      <?php foreach ($sample as $x):
          $c = set_points($x, ['weight_kg' => 100, 'reps' => 5], 75); ?>
        <tr><td><?= e($x['name']) ?></td><td><?= e(number_format((float)$x['coef'], 2)) ?></td><td><?= (int)$c['points'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <h2>Try it</h2>
  <div class="card" id="calc" data-scale="<?= e((string)POINTS_SCALE) ?>">
    <div class="calc">
      <label>Weight <input id="c_w" inputmode="decimal" value="135"></label>
      <label>Unit <select id="c_u"><option value="lb">lb</option><option value="kg">kg</option></select></label>
      <label>Reps <input id="c_r" inputmode="numeric" value="5"></label>
      <label>Coefficient <input id="c_c" inputmode="decimal" value="1.0"></label>
    </div>
    <div>= <span class="bigpts" id="c_out">62</span> points</div>
  </div>

  <h2>Cardio and timed exercises</h2>
  <p>Cardio is scored on <b>distance and pace</b>: <code>points = distance<sup>1.2</sup> × (pace ÷ typical pace)<sup>1.3</sup> × factor</code>. Longer is disproportionately harder (twice the distance earns about 2.3× the points) and faster pays extra. A 10-minute mile run is worth about 80 pts, a 7-minute mile about 130. If you log only time, the activity is scored at its typical pace. Each activity has its own factor, based on how much energy it costs per kilometre. Timed moves like planks earn points per minute held.</p>

  <h2>Levels</h2>
  <p>Your total points sets your level, from 1 to 100. Level 2 needs 250 pts, level 10 about 7,000, level 30 about 70,000, level 50 about 210,000 and level 100 about 850,000. The curve is tuned for real life: an active lifter (three or four sessions a week, daily steps) reaches level 50 in about a year and level 100 in about three, while the early levels come quickly so the start feels good. On top of the sets you log, bonus points come from your weekly goal (with a streak), quests, milestones, challenges and special events. See <a href="<?= e(url('goals')) ?>">goals &amp; events</a>. See the full <a href="<?= e(url('levels')) ?>">level table</a>.</p>

  <h2>Where the numbers come from</h2>
  <ul>
    <li>Estimated 1RM: Epley (1985) <code>w × (1 + reps/30)</code>; Brzycki (1993) is a close alternative and the two agree within a few percent at 3–8 reps. Both are approximations and get less exact above about 10 reps.</li>
    <li>Cardio: effort per minute is scaled from the MET values in the <i>Compendium of Physical Activities</i> (Ainsworth et al., updated 2024), where MET is the activity's energy cost relative to rest.</li>
    <li>Calibration: we compared our model with publicly reported results from the old gamified lifting sites (running grids of distance × pace, a few lifting sets, level thresholds) and tuned our own constants until the numbers landed close: within a few percent for running, a bench set and a squat set. No formulas were copied.</li>
    <li>Load and growth: Schoenfeld et al. (2017) found similar muscle growth with low and high loads, while strength gains favour heavy loads, which is why heavy weight pays more than linearly while extra reps add only a little.</li>
  </ul>
  <p><a class="btn" href="<?= e(url('exercises')) ?>">Browse the exercise library</a></p>
</div>
<?php layout_footer('', ['points.js']); ?>
