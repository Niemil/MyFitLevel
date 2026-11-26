<?php
declare(strict_types=1);

/* Your training in numbers: volume, balance, records and a graph per exercise. Private to the logged-in user. */
$u = require_login();
game_ensure();
define('GAME_CSS', true);
$uid = (int)$u['id'];
$unit = ($u['unit'] ?? 'kg') === 'lb' ? 'lb' : 'kg';
$k = $unit === 'lb' ? LB_PER_KG : 1.0;

$t = row("SELECT COUNT(*) w, COALESCE(SUM(volume_kg),0) v, COALESCE(SUM(points),0) p, COUNT(DISTINCT DATE(performed_at)) d FROM workouts WHERE user_id = ?", [$uid]) ?: ['w' => 0, 'v' => 0, 'p' => 0, 'd' => 0];
$gt = row('SELECT COALESCE(SUM(duration_sec),0) s, COUNT(duration_sec) c, MIN(performed_at) f FROM workouts WHERE user_id = ? AND duration_sec > 0', [$uid]) ?: ['s' => 0, 'c' => 0];
$sr = row("SELECT COUNT(*) s, COALESCE(SUM(reps),0) r FROM workout_sets WHERE user_id = ? AND is_warmup = 0", [$uid]) ?: ['s' => 0, 'r' => 0];

// streaks (days in a row with at least one workout)
$days = array_column(rows("SELECT DISTINCT DATE(performed_at) d FROM workouts WHERE user_id = ? ORDER BY d", [$uid]), 'd');
$best = 0; $run = 0; $prev = null;
foreach ($days as $d) {
    $run = ($prev !== null && strtotime($d) - strtotime($prev) <= 90000) ? $run + 1 : 1;
    $best = max($best, $run); $prev = $d;
}
$cur = 0;
if ($days) {
    $last = end($days);
    if (strtotime(date('Y-m-d')) - strtotime($last) <= 90000 * 1) { $cur = $run; }
}

// weekly volume, last 26 weeks
$wk = [];
for ($i = 25; $i >= 0; $i--) { $wk[date('Y-m-d', strtotime("monday this week -$i weeks"))] = 0.0; }
foreach (rows("SELECT performed_at, volume_kg FROM workouts WHERE user_id = ? AND performed_at >= ?", [$uid, date('Y-m-d', strtotime('monday this week -25 weeks'))]) as $r) {
    $m = date('Y-m-d', strtotime('monday this week', strtotime($r['performed_at'])));
    if (isset($wk[$m])) { $wk[$m] += (float)$r['volume_kg'] * $k; }
}
$weekBars = [];
foreach ($wk as $m => $v) { $weekBars[] = [date('j M', strtotime($m)), $v]; }

// muscle balance, last 30 days (working sets)
$groups = ['chest' => 'Push', 'shoulders' => 'Push', 'triceps' => 'Push', 'back' => 'Pull', 'biceps' => 'Pull', 'legs' => 'Legs', 'core' => 'Core'];
$mus = []; $ppl = ['Push' => 0, 'Pull' => 0, 'Legs' => 0, 'Core' => 0];
foreach (rows("SELECT e.muscle m, COUNT(*) c FROM workout_sets s JOIN workouts w ON w.id = s.workout_id JOIN exercises e ON e.id = s.exercise_id
    WHERE s.user_id = ? AND s.is_warmup = 0 AND w.performed_at >= ? GROUP BY e.muscle", [$uid, date('Y-m-d', strtotime('-30 days'))]) as $r) {
    if (isset($groups[$r['m']])) { $mus[$r['m']] = (int)$r['c']; $ppl[$groups[$r['m']]] += (int)$r['c']; }
}
arsort($mus);
$pplTot = max(1, array_sum($ppl));

// weekday spread
$dow = array_fill(1, 7, 0);
foreach (rows("SELECT DAYOFWEEK(performed_at) d, COUNT(*) c FROM workouts WHERE user_id = ? GROUP BY d", [$uid]) as $r) { $dow[((int)$r['d'] + 5) % 7 + 1] = (int)$r['c']; }
$dn = [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dowMax = max(1, max($dow));

// personal records: best estimated one-rep max per weighted exercise
$prs = rows("SELECT e.id, e.name, MAX(s.weight_kg * (1 + s.reps / 30)) e1, MAX(s.weight_kg) mx
    FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id
    WHERE s.user_id = ? AND s.is_warmup = 0 AND e.kind = 'weight' AND s.weight_kg > 0 AND s.reps > 0
    GROUP BY e.id, e.name ORDER BY e1 DESC LIMIT 12", [$uid]);

// exercise explorer
$done = rows("SELECT e.id, e.name, COUNT(*) c FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id
    WHERE s.user_id = ? AND s.is_warmup = 0 GROUP BY e.id, e.name ORDER BY c DESC LIMIT 80", [$uid]);
$exId = (int)($_GET['ex'] ?? 0);
if (!$exId && $done) { $exId = (int)$done[0]['id']; }
$exName = ''; $c1 = $c2 = $c3 = []; $recent = [];
foreach ($done as $d) { if ((int)$d['id'] === $exId) { $exName = (string)$d['name']; } }
if ($exName !== '') {
    foreach (rows("SELECT w.id wid, DATE(w.performed_at) d, MAX(s.weight_kg * (1 + s.reps / 30)) e1, MAX(s.weight_kg) mx, SUM(s.weight_kg * s.reps) vol, COUNT(*) sets, MAX(s.reps) mr
        FROM workout_sets s JOIN workouts w ON w.id = s.workout_id
        WHERE s.user_id = ? AND s.exercise_id = ? AND s.is_warmup = 0 GROUP BY w.id, d ORDER BY d DESC, w.id DESC LIMIT 40", [$uid, $exId]) as $r) {
        $lab = date('j M', strtotime($r['d']));
        array_unshift($c1, [$lab, (float)$r['e1'] * $k]);
        array_unshift($c2, [$lab, (float)$r['mx'] * $k]);
        array_unshift($c3, [$lab, (float)$r['vol'] * $k]);
        if (count($recent) < 6) { $recent[] = $r; }
    }
}

layout_header('Insights', 'me');
?>
<h1 class="pagetitle">Insights</h1>
<?php if (!(int)$t['w']): ?>
<div class="card center"><p class="muted">Log your first workout and your numbers, balance and records show up here.</p><a class="btn" href="<?= e(url('log')) ?>">Track a workout</a></div>
<?php else: ?>
<div class="card">
  <div class="statgrid ins4">
    <div><b><?= number_format((int)$t['w']) ?></b><span>workouts</span></div>
    <div><b><?= e(fmt_big((float)$t['v'] * $k)) ?></b><span><?= e($unit) ?> lifted</span></div>
    <div><b><?= number_format((int)$sr['s']) ?></b><span>working sets</span></div>
    <div><b><?= e(fmt_big((float)$sr['r'])) ?></b><span>reps</span></div>
    <div><b><?= number_format((int)$t['d']) ?></b><span>training days</span></div>
    <div><b><?= $cur ?></b><span>day streak</span></div>
    <div><b><?= $best ?></b><span>best streak</span></div>
    <div><b><?= number_format((int)$t['p']) ?></b><span>points from lifting</span></div>
    <div><b><?= e(fmt_duration((int)$gt['s'])) ?></b><span>time training</span></div>
    <div><b><?= (int)$gt['c'] ? e(fmt_duration((int)round($gt['s'] / $gt['c']))) : '-' ?></b><span>average workout</span></div>
  </div>
</div>

<div class="card">
  <h3>Weekly volume <small class="muted">(<?= e($unit) ?>, 26 weeks)</small></h3>
  <?= chart_bars($weekBars, ' ' . $unit) ?>
</div>

<div class="card">
  <h3>Push, pull and legs <small class="muted">working sets, last 30 days</small></h3>
  <?php if (array_sum($ppl) === 0): ?><p class="muted small">No sets in the last 30 days.</p><?php else: ?>
  <div class="insbal"><?php foreach ($ppl as $nm => $c): if (!$c) continue; ?><i class="b-<?= strtolower($nm) ?>" style="flex:<?= $c ?>" title="<?= e($nm) ?>"></i><?php endforeach; ?></div>
  <div class="insleg"><?php foreach ($ppl as $nm => $c): ?><span><i class="b-<?= strtolower($nm) ?>"></i><?= e($nm) ?> <?= round($c * 100 / $pplTot) ?>%</span><?php endforeach; ?></div>
  <div class="insrows">
    <?php $mm = max(1, max($mus)); foreach ($mus as $m => $c): ?>
    <div class="insrow"><span><?= e(ucfirst($m)) ?></span><div><i class="b-<?= strtolower($groups[$m]) ?>" style="width:<?= round($c * 100 / $mm) ?>%"></i></div><b><?= $c ?></b></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Which days you train</h3>
  <div class="insdow"><?php foreach ($dow as $i => $c): ?><div><div class="col"><i style="height:<?= round($c * 100 / $dowMax) ?>%"></i></div><small><?= $dn[$i] ?></small><b><?= $c ?></b></div><?php endforeach; ?></div>
</div>

<?php if ($prs): ?>
<div class="card">
  <h3>Your records <small class="muted">best estimated one-rep max</small></h3>
  <?php foreach ($prs as $p): ?>
  <a class="gb-row insprow" href="<?= e(url('insights') . '?ex=' . (int)$p['id']) ?>#explore"><span class="grow"><?= e($p['name']) ?><br><small class="muted">heaviest set <?= e(fmt_big((float)$p['mx'] * $k)) ?> <?= e($unit) ?></small></span><b><?= e(fmt_big((float)$p['e1'] * $k)) ?> <?= e($unit) ?></b></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" id="explore">
  <h3>Exercise explorer</h3>
  <?php if (!$done): ?><p class="muted small">Nothing logged yet.</p><?php else: ?>
  <form method="get" action="<?= e(url('insights')) ?>#explore" class="insform">
    <select name="ex" onchange="this.form.submit()" aria-label="Exercise">
      <?php foreach ($done as $d): ?><option value="<?= (int)$d['id'] ?>"<?= (int)$d['id'] === $exId ? ' selected' : '' ?>><?= e($d['name']) ?> (<?= (int)$d['c'] ?> sets)</option><?php endforeach; ?>
    </select>
    <noscript><button class="btn small">Show</button></noscript>
  </form>
  <?php if ($c2): ?>
    <details class="fold" open><summary>Estimated one-rep max <small class="muted">(<?= e($unit) ?>)</small></summary><?= chart_line($c1, ' ' . $unit) ?></details>
    <details class="fold"><summary>Heaviest set per session <small class="muted">(<?= e($unit) ?>)</small></summary><?= chart_line($c2, ' ' . $unit) ?></details>
    <details class="fold"><summary>Volume per session <small class="muted">(<?= e($unit) ?>)</small></summary><?= chart_line($c3, ' ' . $unit) ?></details>
    <h4>Latest sessions</h4>
    <?php foreach ($recent as $r): ?>
    <a class="gb-row insprow" href="<?= e(url('workout/' . (int)$r['wid'])) ?>"><span class="grow"><?= e(date('M j', strtotime($r['d']))) ?><br><small class="muted"><?= (int)$r['sets'] ?> sets, best <?= (int)$r['mr'] ?> reps</small></span><b><?= e(fmt_big((float)$r['mx'] * $k)) ?> <?= e($unit) ?></b></a>
    <?php endforeach; ?>
  <?php else: ?><p class="muted small">This one is a cardio or bodyweight move, so there is no weight graph. Pick a lift above.</p><?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php layout_footer(); ?>
