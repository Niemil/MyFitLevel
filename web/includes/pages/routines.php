<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
$editId = isset($args[0]) ? (int)$args[0] : 0;

/** Save the item list of a routine (array of ['id' => exercise id, 'n' => sets]). */
function routine_save_items(int $rid, int $uid, array $items): void
{
    q('UPDATE routines SET payload = ? WHERE id = ? AND user_id = ?', [json_encode(array_values($items)), $rid, $uid]);
}

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $rid = (int)($_POST['rid'] ?? 0);
    $own = $rid ? row('SELECT * FROM routines WHERE id = ? AND user_id = ?', [$rid, $uid]) : null;

    if ($do === 'create') {
        $name = clean_text((string)($_POST['name'] ?? ''), 60);
        if ($name === '') {
            flash('Give the routine a name.', 'err');
            redirect('routines');
        }
        if ((int)val('SELECT COUNT(*) FROM routines WHERE user_id = ?', [$uid]) >= 50) {
            flash('You have 50 routines already. Delete one first.', 'err');
            redirect('routines');
        }
        q('INSERT INTO routines (user_id, name, payload, created_at) VALUES (?,?,?,?)', [$uid, $name, '[]', now()]);
        redirect('routines/' . (int)val('SELECT LAST_INSERT_ID()'));
    }
    if ($do === 'delete' && $own) {
        q('DELETE FROM routines WHERE id = ? AND user_id = ?', [$rid, $uid]);
        flash('Routine deleted.');
        redirect('routines');
    }
    if ($do === 'mode') {
        set_setting('rtmode:' . $uid, ($_POST['mode'] ?? '') === 'advanced' ? 'advanced' : 'basic');
        redirect($rid ? 'routines/' . $rid : 'routines');
    }
    if ($do === 'program') {
        [$made, $skip, $days] = program_install($uid, (string)($_POST['code'] ?? ''), !empty($_POST['with_schedule']));
        flash($made ? "Added $made routine" . ($made > 1 ? 's' : '') . ($days ? " and set $days training days in your schedule" : '') . '.' : 'Could not add that programme.', $made ? 'ok' : 'err');
        redirect($made && $days ? 'routines#schedule' : 'routines');
    }
    if ($do === 'save_all' && $own) {
        $items = routine_items($own);
        $lb = ($u['unit'] ?? 'kg') === 'lb';
        foreach ($items as $i => &$it) {
            $it['n'] = max(1, min(20, (int)($_POST['n'][$i] ?? $it['n'])));
            $rp = trim(clean_text((string)($_POST['reps'][$i] ?? ''), 10));
            if ($rp !== '') { $it['reps'] = $rp; } else { unset($it['reps']); }
            $wv = str_replace(',', '.', trim((string)($_POST['w'][$i] ?? '')));
            if ($wv !== '' && is_numeric($wv) && (float)$wv > 0 && (float)$wv < 1000) { $it['w'] = round($lb ? (float)$wv / LB_PER_KG : (float)$wv, 2); } else { unset($it['w']); }
            $rs = (int)($_POST['rest'][$i] ?? 0);
            if ($rs > 0) { $it['rest'] = min(600, $rs); } else { unset($it['rest']); }
            $nt = trim(clean_text((string)($_POST['note'][$i] ?? ''), 100));
            if ($nt !== '') { $it['note'] = $nt; } else { unset($it['note']); }
        }
        unset($it);
        $mv = (string)($_POST['mv'] ?? '');
        if (preg_match('/^(up|down|rm):(\d+)$/', $mv, $m) && isset($items[(int)$m[2]])) {
            $k = (int)$m[2];
            if ($m[1] === 'rm') { unset($items[$k]); $items = array_values($items); }
            elseif ($m[1] === 'up' && $k > 0) { [$items[$k - 1], $items[$k]] = [$items[$k], $items[$k - 1]]; }
            elseif ($m[1] === 'down' && $k < count($items) - 1) { [$items[$k + 1], $items[$k]] = [$items[$k], $items[$k + 1]]; }
        }
        routine_save_items($rid, $uid, array_values($items));
        if ($mv === '') { flash('Routine saved.'); }
        redirect('routines/' . $rid . ($mv !== '' ? '#ex' : ''));
    }
    if ($do === 'duplicate' && $own) {
        q('INSERT INTO routines (user_id, name, payload, created_at) VALUES (?,?,?,?)', [$uid, clean_text('Copy of ' . $own['name'], 60), $own['payload'], now()]);
        redirect('routines/' . (int)val('SELECT LAST_INSERT_ID()'));
    }
    if ($do === 'rename' && $own) {
        $name = clean_text((string)($_POST['name'] ?? ''), 60);
        if ($name !== '') {
            q('UPDATE routines SET name = ? WHERE id = ?', [$name, $rid]);
        }
        redirect('routines/' . $rid);
    }
    if ($own && in_array($do, ['add', 'remove', 'up', 'down', 'sets'], true)) {
        $items = routine_items($own);
        $i = (int)($_POST['i'] ?? -1);
        if ($do === 'add') {
            $nm = clean_text((string)($_POST['exname'] ?? ''), 100);
            $ex = row('SELECT id FROM exercises WHERE name = ? AND active = 1', [$nm]);
            if (!$ex && $nm !== '') {   // typed but not picked: accept a single close match
                $like = rows('SELECT id FROM exercises WHERE name LIKE ? AND active = 1 LIMIT 2', ['%' . str_replace(['%', '_'], '', $nm) . '%']);
                $ex = count($like) === 1 ? $like[0] : null;
            }
            if (!$ex) {
                flash('Pick an exercise from the list.', 'err');
            } elseif (count($items) >= 30) {
                flash('A routine can have at most 30 exercises.', 'err');
            } else {
                $items[] = ['id' => (int)$ex['id'], 'n' => max(1, min(20, (int)($_POST['n'] ?? 3)))];
            }
        } elseif (isset($items[$i])) {
            if ($do === 'remove') {
                unset($items[$i]);
            } elseif ($do === 'up' && $i > 0) {
                [$items[$i - 1], $items[$i]] = [$items[$i], $items[$i - 1]];
            } elseif ($do === 'down' && $i < count($items) - 1) {
                [$items[$i + 1], $items[$i]] = [$items[$i], $items[$i + 1]];
            } elseif ($do === 'sets') {
                $items[$i]['n'] = max(1, min(20, (int)($_POST['n'] ?? 3)));
            }
        }
        routine_save_items($rid, $uid, array_values($items));
        redirect('routines/' . $rid);
    }
    if ($do === 'schedule') {
        q('DELETE FROM routine_schedule WHERE user_id = ?', [$uid]);
        foreach (DOW_NAMES as $d => $_n) {
            $r = (int)($_POST['dow'][$d] ?? 0);
            if ($r && row('SELECT id FROM routines WHERE id = ? AND user_id = ?', [$r, $uid])) {
                q('INSERT INTO routine_schedule (user_id, dow, routine_id) VALUES (?,?,?)', [$uid, $d, $r]);
            }
        }
        flash('Schedule saved.');
        redirect('routines#schedule');
    }
    redirect('routines');
}

$list = rows('SELECT * FROM routines WHERE user_id = ? ORDER BY name', [$uid]);
$exNames = [];
$exInfo = [];
foreach (rows('SELECT id, name, slug, muscle, equipment, kind FROM exercises WHERE active = 1') as $x) {
    $exNames[(int)$x['id']] = $x['name'];
    $x['tier'] = exercise_tier($x);
    $exInfo[(int)$x['id']] = $x;
}
$advanced = setting('rtmode:' . $uid) === 'advanced';
$ulb = ($u['unit'] ?? 'kg') === 'lb';
function rt_group(string $g): string
{
    return ['fullbody' => 'Full body'][$g] ?? ucfirst($g);
}
function rt_est_min(array $items): int
{
    $sec = 0;
    foreach ($items as $it) {
        $sec += (int)$it['n'] * ((int)($it['rest'] ?? 90) + 40);
    }
    return (int)round($sec / 60);
}

/* ---------- one routine ---------- */
if ($editId) {
    $r = row('SELECT * FROM routines WHERE id = ? AND user_id = ?', [$editId, $uid]);
    if (!$r) {
        redirect('routines');
    }
    $items = routine_items($r);
    layout_header($r['name'], 'routines');
    $totalSets = 0; $byMuscle = []; $byTier = [];
    foreach ($items as $it) {
        $inf = $exInfo[(int)$it['id']] ?? null;
        $totalSets += (int)$it['n'];
        if ($inf) {
            $byMuscle[$inf['muscle']] = ($byMuscle[$inf['muscle']] ?? 0) + (int)$it['n'];
            $byTier[$inf['tier']] = ($byTier[$inf['tier']] ?? 0) + 1;
        }
    }
    arsort($byMuscle);
    $rid = (int)$r['id'];
    ?>
<div class="rthead"><a href="<?= e(url('routines')) ?>">← All routines</a>
  <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="do" value="mode"><input type="hidden" name="rid" value="<?= $rid ?>"><input type="hidden" name="mode" value="<?= $advanced ? 'basic' : 'advanced' ?>">
    <button class="btn small ghost" type="submit"><?= $advanced ? 'Switch to simple editor' : 'Switch to advanced editor' ?></button></form></div>
<div class="card">
  <form method="post" class="row" style="gap:.5rem;align-items:center"><?= csrf_field() ?><input type="hidden" name="do" value="rename"><input type="hidden" name="rid" value="<?= $rid ?>">
    <input name="name" value="<?= e($r['name']) ?>" maxlength="60" style="flex:1"><button class="btn small ghost" type="submit">Rename</button>
  </form>
  <?php if ($items): ?>
  <div class="rtstats"><span><b><?= count($items) ?></b> exercises</span><span><b><?= $totalSets ?></b> sets</span><span><b>~<?= rt_est_min($items) ?></b> min</span></div>
  <div class="rtchips"><?php foreach ($byMuscle as $m => $c): ?><span class="chip"><?= e(rt_group($m)) ?> <b><?= (int)$c ?></b></span><?php endforeach; ?>
    <?php foreach ($byTier as $t => $c): ?><?= tier_chip($t) ?><?php endforeach; ?></div>
  <p style="margin-top:.8rem"><a class="btn" href="<?= e(url('log?routine=' . $rid)) ?>">▶ Start this workout</a></p>
  <?php endif; ?>
</div>
<div class="card" id="ex">
  <h3>Exercises</h3>
  <?php if (!$items): ?><p class="muted">Empty so far. Search or browse by level below and add your first exercise.</p><?php endif; ?>
  <?php if ($advanced && $items): ?>
  <p class="muted small">Advanced editor: set target reps (e.g. <code>8-12</code>, <code>5</code> or <code>30s</code>), a target weight (<?= $ulb ? 'lb' : 'kg' ?>), rest and a note for each exercise. The tracker pre-fills reps and weight when you start the routine.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="save_all"><input type="hidden" name="rid" value="<?= $rid ?>">
  <?php foreach ($items as $i => $it): $inf = $exInfo[(int)$it['id']] ?? null; $wshow = isset($it['w']) ? fmt_num($ulb ? (float)$it['w'] * LB_PER_KG : (float)$it['w'], 1) : ''; ?>
    <div class="rtadv">
      <div class="rtadvtop">
        <span class="rtname"><b><?= e(chr(65 + ($i % 26))) ?></b> <?= $inf ? '<a href="' . e(url('exercises/' . $inf['slug'])) . '">' . e($inf['name']) . '</a>' : 'Removed exercise' ?></span>
        <?= $inf ? tier_chip($inf['tier']) : '' ?>
      </div>
      <?php if ($inf): ?><div class="muted small"><?= e(rt_group($inf['muscle'])) ?> · <?= e($inf['equipment']) ?></div><?php endif; ?>
      <div class="rtfields">
        <label>Sets<select name="n[<?= $i ?>]"><?php for ($k = 1; $k <= 20; $k++): ?><option value="<?= $k ?>" <?= (int)$it['n'] === $k ? 'selected' : '' ?>><?= $k ?></option><?php endfor; ?></select></label>
        <label>Reps<input name="reps[<?= $i ?>]" value="<?= e((string)($it['reps'] ?? '')) ?>" maxlength="10" placeholder="8-12"></label>
        <label>Weight<input name="w[<?= $i ?>]" value="<?= e($wshow) ?>" inputmode="decimal" maxlength="7" placeholder="<?= $ulb ? 'lb' : 'kg' ?>"></label>
        <label>Rest<select name="rest[<?= $i ?>]"><option value="0">Default</option><?php foreach ([30, 45, 60, 90, 120, 180, 240, 300] as $rs): ?><option value="<?= $rs ?>" <?= (int)($it['rest'] ?? 0) === $rs ? 'selected' : '' ?>><?= $rs >= 60 ? fmt_num($rs / 60, 1) . ' min' : $rs . ' s' ?></option><?php endforeach; ?></select></label>
      </div>
      <input class="rtnote" name="note[<?= $i ?>]" value="<?= e((string)($it['note'] ?? '')) ?>" maxlength="100" placeholder="Note, e.g. pause on the chest, slow down">
      <div class="rtmv"><button class="btn small ghost" name="mv" value="up:<?= $i ?>" aria-label="Move up">↑</button><button class="btn small ghost" name="mv" value="down:<?= $i ?>" aria-label="Move down">↓</button><button class="btn small ghost danger" name="mv" value="rm:<?= $i ?>" aria-label="Remove">✕ Remove</button></div>
    </div>
  <?php endforeach; ?>
    <button class="btn wide" type="submit">Save changes</button>
  </form>
  <?php else: foreach ($items as $i => $it): $inf = $exInfo[(int)$it['id']] ?? null; ?>
  <div class="rtrow">
    <span class="rtname"><b><?= e(chr(65 + ($i % 26))) ?></b> <?= e($exNames[(int)$it['id']] ?? 'Removed exercise') ?><?= $inf ? ' ' . tier_chip($inf['tier']) : '' ?><?php if (!empty($it['reps'])): ?> <small class="muted">· <?= e((string)$it['reps']) ?></small><?php endif; ?></span>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="sets"><input type="hidden" name="rid" value="<?= $rid ?>"><input type="hidden" name="i" value="<?= (int)$i ?>">
      <select name="n" data-autosubmit aria-label="Sets"><?php for ($k = 1; $k <= 10; $k++): ?><option value="<?= $k ?>" <?= (int)$it['n'] === $k ? 'selected' : '' ?>><?= $k ?> set<?= $k > 1 ? 's' : '' ?></option><?php endfor; ?></select><noscript><button class="btn small ghost">OK</button></noscript>
    </form>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="rid" value="<?= $rid ?>"><input type="hidden" name="i" value="<?= (int)$i ?>">
      <button class="btn small ghost" name="do" value="up" aria-label="Move up">↑</button><button class="btn small ghost" name="do" value="down" aria-label="Move down">↓</button><button class="btn small ghost" name="do" value="remove" aria-label="Remove">✕</button>
    </form>
  </div>
  <?php endforeach; endif; ?>
  <h4 style="margin-top:1rem">Add an exercise</h4>
  <div class="rtfilters">
    <select id="rtfl-tier" aria-label="Level"><option value="">Any level</option><?php foreach (TIER_LABELS as $tk => $tl): ?><option value="<?= e($tk) ?>"><?= e($tl) ?></option><?php endforeach; ?></select>
    <select id="rtfl-muscle" aria-label="Muscle"><option value="">Any muscle</option><?php foreach (['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'core', 'fullbody', 'cardio'] as $mk): ?><option value="<?= e($mk) ?>"><?= e(rt_group($mk)) ?></option><?php endforeach; ?></select>
  </div>
  <form method="post" class="rtadd"><?= csrf_field() ?><input type="hidden" name="do" value="add"><input type="hidden" name="rid" value="<?= $rid ?>">
    <div class="rtsearch"><input name="exname" id="rtname" placeholder="Search, or pick a level / muscle above" autocomplete="off" required><div class="rtresults" id="rtresults" hidden></div></div>
    <select name="n" aria-label="Sets"><?php for ($k = 1; $k <= 10; $k++): ?><option value="<?= $k ?>" <?= $k === 3 ? 'selected' : '' ?>><?= $k ?> set<?= $k > 1 ? 's' : '' ?></option><?php endfor; ?></select>
    <button class="btn" type="submit">Add</button>
  </form>
  <script type="application/json" id="rtdata"><?= json_encode(array_values(array_map(static fn($x) => ['n' => $x['name'], 'm' => $x['muscle'], 'e' => $x['equipment'], 't' => $x['tier']], $exInfo)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</div>
<div class="card">
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="rid" value="<?= $rid ?>">
    <button class="btn small ghost" name="do" value="duplicate">Duplicate</button>
    <button class="btn small ghost" name="do" value="delete" data-confirm="Delete this routine?">Delete</button>
  </form>
</div>
<?php
    layout_footer('routines', ['routines.js']);
    return;
}

/* ---------- overview + schedule ---------- */
$sched = [];
foreach (rows('SELECT dow, routine_id FROM routine_schedule WHERE user_id = ?', [$uid]) as $s) {
    $sched[(int)$s['dow']] = (int)$s['routine_id'];
}
$todayDow = today_dow($u);
layout_header('Routines', 'routines');
?>
<h1>Routines</h1>
<p class="muted">Build your own workouts once, start them with one tap, and put them on a weekly schedule. Free for everyone.</p>

<div class="card">
  <form method="post" class="rtadd"><?= csrf_field() ?><input type="hidden" name="do" value="create">
    <input name="name" maxlength="60" placeholder="New routine, e.g. Push day" required>
    <button class="btn" type="submit">Create</button>
  </form>
</div>

<form method="post" class="inl rtmode"><?= csrf_field() ?><input type="hidden" name="do" value="mode"><input type="hidden" name="mode" value="<?= $advanced ? 'basic' : 'advanced' ?>">
  <span class="muted small">Editor: <b><?= $advanced ? 'Advanced' : 'Simple' ?></b> · <?= $advanced ? 'adds target reps, weight, rest and a note per exercise' : 'just exercises and number of sets' ?>. No fixed schedule? Skip it and use <a href="<?= e(url('lists')) ?>">My lists</a> (Push / Pull / Legs) instead.</span> <button class="btn small ghost" type="submit"><?= $advanced ? 'Use simple editor' : 'Use advanced editor' ?></button></form>
<?php if (!$list): ?>
<div class="card center"><p class="muted">No routines yet. Create one above, or save a finished workout as a routine from the Track page.</p></div>
<?php endif; ?>
<?php foreach ($list as $r):
    $its = routine_items($r);
    $names = array_map(static fn($it) => $exNames[(int)$it['id']] ?? '?', array_slice($its, 0, 4)); ?>
<div class="card rtcard">
  <div class="rtinfo">
    <h3><a href="<?= e(url('routines/' . (int)$r['id'])) ?>"><?= e($r['name']) ?></a></h3>
    <span class="muted small"><?= count($its) ?> exercise<?= count($its) === 1 ? '' : 's' ?><?= $names ? ' · ' . e(implode(', ', $names)) . (count($its) > 4 ? '…' : '') : '' ?></span>
  </div>
  <div class="rtbtns">
    <?php if ($its): ?><a class="btn small" href="<?= e(url('log?routine=' . (int)$r['id'])) ?>">Start</a><?php endif; ?>
    <a class="btn small ghost" href="<?= e(url('routines/' . (int)$r['id'])) ?>">Edit</a>
  </div>
</div>
<?php endforeach; ?>

<h2 id="programs">Starter programmes</h2>
<p class="muted small">Not sure where to start? Add a ready-made programme. Each one creates routines you can change however you like. Levels: Beginner, Intermediate, Advanced.</p>
<?php foreach (starter_programs() as $pc => [$pn, $plevel, $pdays, $pdesc, $proutines, $psched]): ?>
<div class="card prog">
  <div class="proghead"><h3><?= e($pn) ?></h3><?= tier_chip($plevel) ?><span class="muted small"><?= (int)$pdays ?> days/week</span></div>
  <p class="muted small"><?= e($pdesc) ?></p>
  <details><summary class="small">See the exercises</summary>
    <?php foreach ($proutines as $prn => $pits): ?><div class="small"><b><?= e($prn) ?></b>: <?= e(implode(', ', array_map(static fn($x) => $x[0] . ' ' . $x[1] . '×' . $x[2], $pits))) ?></div><?php endforeach; ?>
  </details>
  <form method="post" class="progform"><?= csrf_field() ?><input type="hidden" name="do" value="program"><input type="hidden" name="code" value="<?= e($pc) ?>">
    <label class="small"><input type="checkbox" name="with_schedule" value="1" checked> Also set my weekly schedule (replaces the current one)</label>
    <button class="btn small" type="submit">Add programme</button></form>
</div>
<?php endforeach; ?>

<h2 id="schedule">Weekly schedule</h2>
<div class="card">
  <?php if (!$list): ?>
    <p class="muted">Create a routine first, then plan your week here.</p>
  <?php else: ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="schedule">
    <?php $weekSets = []; $weekMin = 0; $weekDays = 0;
    foreach (DOW_NAMES as $d => $dn):
        $sr = null;
        foreach ($list as $rr) { if ((int)$rr['id'] === ($sched[$d] ?? 0)) { $sr = $rr; } }
        $sitems = $sr ? routine_items($sr) : [];
        if ($sr) { $weekDays++; $weekMin += rt_est_min($sitems); }
        foreach ($sitems as $sit) { $sg = $exInfo[(int)$sit['id']]['muscle'] ?? ''; if ($sg) { $weekSets[$sg] = ($weekSets[$sg] ?? 0) + (int)$sit['n']; } }
    ?>
    <div class="rtrow<?= $d === $todayDow ? ' today' : '' ?>">
      <span class="rtname"><?= e($dn) ?><?= $d === $todayDow ? ' <small class="muted">(today)</small>' : '' ?></span>
      <select name="dow[<?= $d ?>]">
        <option value="0">Rest day</option>
        <?php foreach ($list as $r): ?><option value="<?= (int)$r['id'] ?>" <?= ($sched[$d] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php if ($sr): ?><div class="muted small rtsum"><?= count($sitems) ?> exercises · ~<?= rt_est_min($sitems) ?> min</div><?php endif; ?>
    <?php endforeach; ?>
    <?php if ($weekDays): arsort($weekSets); $wmax = max($weekSets) ?: 1; ?>
    <div class="weeksum"><b><?= $weekDays ?> training day<?= $weekDays > 1 ? 's' : '' ?></b> · about <?= round($weekMin / 60, 1) ?> h a week
      <?php if ($advanced): ?><div class="vol"><?php foreach ($weekSets as $wg => $wc): ?><div class="volrow"><span><?= e(rt_group($wg)) ?></span><div class="xpbar"><i style="width:<?= (int)round($wc / $wmax * 100) ?>%"></i></div><b><?= (int)$wc ?></b></div><?php endforeach; ?><div class="muted small">Planned sets per week by muscle group. Aim for roughly 10-20 hard sets per muscle if you want to grow.</div></div>
      <?php else: ?><div class="muted small">Switch to the advanced editor to see planned sets per muscle each week.</div><?php endif; ?></div>
    <?php endif; ?>
    <button class="btn" type="submit" style="margin-top:.8rem">Save schedule</button>
  </form>
  <p class="muted small">Your scheduled workout for today shows up at the top of your feed with a Start button.</p>
  <?php endif; ?>
</div>
<?php layout_footer('routines'); ?>
