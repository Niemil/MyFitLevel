<?php
declare(strict_types=1);

/*
 * TEMPORARY calibration helper (Admin > Calibration).
 * Lets the admin collect real "exercise, weight, reps, points" examples from the old gamified lifting sites
 * and compare them with what this site's scoring model gives. Data lives in one setting ('calib_points').
 * When scoring is finished: press "Delete all calibration data" in the tab, then delete this file
 * (includes/pages/admin_calib.php); the tab disappears by itself.
 */

function calib_load(): array
{
    $j = json_decode((string)setting('calib_points', '[]'), true);
    return is_array($j) ? $j : [];
}

function calib_known(): array
{
    // [type, exercise name, value1 (kg or minutes), reps or km, real points, source]
    $lb = 1 / 2.20462;
    return [
        ['lift', 'Barbell Bench Press', round(100 * $lb, 2), 1, 31, 'Octopus Overlords forum'],
        ['lift', 'Barbell Bench Press', round(100 * $lb, 2), 2, 32, 'Octopus Overlords forum (5 sets = 160)'],
        ['lift', 'Barbell Bench Press', round(100 * $lb, 2), 10, 40, 'Octopus Overlords forum'],
        ['lift', 'Machine Chest Press', round(100 * $lb, 2), 1, 25, 'Octopus Overlords forum'],
        ['lift', 'Barbell Bench Press', round(160 * $lb, 2), 6, 85, 'shrinkinguy blog'],
        ['lift', 'Back Squat', round(135 * $lb, 2), 5, 62, 'remembered / site example'],
        ['lift', 'Push-Up', 0, 25, 62, 'shrinkinguy blog (body weight unknown)'],
        ['lift', 'Sit-Up', 0, 25, 12, 'shrinkinguy blog (body weight unknown)'],
        ['cardio', 'Running', 10, 1.609, 82, 'fellrnr table: 1 mile at 10:00'],
        ['cardio', 'Running', 7, 1.609, 130, 'fellrnr table: 1 mile at 7:00'],
        ['cardio', 'Running', 80, 12.875, 999, 'fellrnr table: 8 miles at 10:00'],
        ['cardio', 'Running', 224, 51.499, 8383, 'fellrnr table: 32 miles at 7:00'],
        ['cardio', 'Treadmill Walk', 30, 3.219, 101, 'Octopus Overlords forum (6% incline)'],
    ];
}

function calib_handle(string $do): bool
{
    if ($do === 'calib_add') {
        $type = ($_POST['type'] ?? '') === 'cardio' ? 'cardio' : 'lift';
        $name = clean_text((string)($_POST['exname'] ?? ''), 100);
        $unit = ($_POST['unit'] ?? 'kg') === 'lb' ? 1 / 2.20462 : 1.0;
        $v1 = (float)str_replace(',', '.', (string)($_POST['v1'] ?? '0'));
        $v2 = (float)str_replace(',', '.', (string)($_POST['v2'] ?? '0'));
        $pts = (float)str_replace(',', '.', (string)($_POST['pts'] ?? '0'));
        if ($name === '' || $pts <= 0) {
            flash('Pick an exercise and enter the real points.', 'err');
        } else {
            $l = calib_load();
            $l[] = [$type, $name, $type === 'lift' ? round($v1 * $unit, 2) : $v1, $v2, $pts, clean_text((string)($_POST['src'] ?? ''), 120)];
            set_setting('calib_points', json_encode(array_slice($l, -500)));
            flash('Example added.');
        }
        return true;
    }
    if ($do === 'calib_known') {
        set_setting('calib_points', json_encode(calib_known()));
        flash('Loaded the examples found so far.');
        return true;
    }
    if ($do === 'calib_del') {
        $l = calib_load();
        unset($l[(int)($_POST['i'] ?? -1)]);
        set_setting('calib_points', json_encode(array_values($l)));
        return true;
    }
    if ($do === 'calib_clear') {
        set_setting('calib_points', '[]');
        flash('All calibration data deleted.');
        return true;
    }
    return false;
}

function calib_view(): void
{
    $list = calib_load();
    $names = rows("SELECT name FROM exercises WHERE active = 1 AND kind IN ('weight','bodyweight','cardio') ORDER BY name");
    ?>
<div class="card">
  <h3>Calibration helper <span class="muted small">(temporary)</span></h3>
  <p class="muted small">Collect real examples from the old site (exercise, weight, reps, points). The table shows what this site gives for the same set. Differences over about 15% are worth a look. Delete the data when you are done.</p>
  <details>
    <summary><b>How to find examples</b></summary>
    <ol class="small">
      <li><b>Wayback Machine:</b> open web.archive.org and paste <code>fitocracy.com/profile/</code> (or a username). Old profile and activity pages list exercise, weight, reps and points per set.</li>
      <li><b>Google / Bing / DuckDuckGo images:</b> search <code>fitocracy workout points</code>, <code>fitocracy "pts" squat</code>, <code>fitocracy activity feed</code>.</li>
      <li><b>Reddit and forums:</b> search "Fitocracy points" together with an exercise name; people often wrote "I got X points for Y".</li>
      <li><b>Write each example below:</b> pick the exercise, enter weight and reps (or minutes and km for cardio), enter the points the old site gave, and add where you found it. Aim for 20-30 varied sets (barbell, dumbbell, machine, bodyweight, cardio).</li>
      <li>Dumbbells: enter the weight of <b>one</b> dumbbell, as logged on the old site.</li>
    </ol>
  </details>
  <form method="post" class="inline" style="margin:.6rem 0"><?= csrf_field() ?><input type="hidden" name="do" value="calib_known"><button class="btn small ghost" type="submit">Load the examples found so far</button></form>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="calib_add">
    <div class="calc">
      <label>Type <select name="type"><option value="lift">Strength / bodyweight</option><option value="cardio">Cardio</option></select></label>
      <label>Exercise <input name="exname" list="calibex" required></label>
      <label>Weight / minutes <input name="v1" inputmode="decimal" value="0"></label>
      <label>Unit <select name="unit"><option value="kg">kg</option><option value="lb">lb</option></select></label>
      <label>Reps / km <input name="v2" inputmode="decimal" value="1"></label>
      <label>Real points <input name="pts" inputmode="decimal" required></label>
      <label>Source <input name="src" placeholder="link or note"></label>
    </div>
    <datalist id="calibex"><?php foreach ($names as $n): ?><option value="<?= e($n['name']) ?>"><?php endforeach; ?></datalist>
    <button class="btn" type="submit">Add example</button>
  </form>
</div>
<?php if ($list):
    $errs = []; ?>
<div class="card tablewrap">
  <table class="cmp">
    <thead><tr><th>Exercise</th><th>Set</th><th>Real</th><th>Ours</th><th>Diff</th><th>Source</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $i => $c):
        [$type, $name, $v1, $v2, $real, $src] = array_pad($c, 6, '');
        $ex = row('SELECT * FROM exercises WHERE name = ?', [$name]);
        if (!$ex) { $ours = null; }
        elseif ($type === 'cardio') { $ours = (int)set_points($ex, ['duration_sec' => (int)round($v1 * 60), 'distance_m' => (int)round($v2 * 1000)], 75)['points']; }
        else { $ours = (int)set_points($ex, ['weight_kg' => (float)$v1, 'reps' => (int)$v2], 75)['points']; }
        $diff = ($ours !== null && $real > 0) ? round(($ours / $real - 1) * 100) : null;
        if ($diff !== null) { $errs[] = abs($diff); }
        $setTxt = $type === 'cardio' ? rtrim(rtrim(number_format((float)$v1, 1), '0'), '.') . ' min, ' . rtrim(rtrim(number_format((float)$v2, 2), '0'), '.') . ' km' : number_format((float)$v1, 1) . ' kg × ' . (int)$v2; ?>
      <tr>
        <td><?= e($name) ?></td><td><?= e($setTxt) ?></td><td><?= e((string)$real) ?></td>
        <td><?= $ours === null ? '?' : (int)$ours ?></td>
        <td style="color:<?= $diff !== null && abs($diff) > 15 ? 'var(--bad,#d33)' : 'inherit' ?>"><?= $diff === null ? '' : ($diff > 0 ? '+' : '') . $diff . '%' ?></td>
        <td class="muted small"><?= e((string)$src) ?></td>
        <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="calib_del"><input type="hidden" name="i" value="<?= (int)$i ?>"><button class="btn small ghost" type="submit">✕</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($errs): ?><p class="muted small">Average difference: <b><?= round(array_sum($errs) / count($errs)) ?>%</b> over <?= count($errs) ?> examples. Bodyweight moves assume a 75 kg body.</p><?php endif; ?>
</div>
<div class="card">
  <form method="post" onsubmit="return confirm('Delete all calibration data?')"><?= csrf_field() ?><input type="hidden" name="do" value="calib_clear"><button class="btn small ghost" type="submit">Delete all calibration data</button></form>
  <p class="muted small">When scoring is final: press this, then delete the file <code>includes/pages/admin_calib.php</code> on the server and the tab is gone.</p>
</div>
<?php endif;
}
