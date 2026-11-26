<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];

if (is_post()) {
    csrf_check();
    $nonce = (string)($_POST['nonce'] ?? '');
    if ($nonce !== '' && ($_SESSION['last_nonce'] ?? '') === $nonce) {
        redirect('feed');   // double submit
    }
    if (!rate_ok('log:' . $uid, 60, 3600)) {
        flash('You are logging a lot of workouts. Slow down a bit.', 'err');
        redirect('log');
    }
    $res = save_workout($u, [
        'performed_at' => $_POST['performed_at'] ?? '',
        'title' => $_POST['title'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'ex' => $_POST['ex'] ?? [],
        'gym_id' => $_POST['gym_id'] ?? 0,
        'duration' => $_POST['duration'] ?? 0,
        'edit_id' => $_POST['edit_id'] ?? 0,
    ]);
    if (!$res['ok']) {
        flash($res['error'], 'err');
        redirect('log');
    }
    rate_hit('log:' . $uid);
    if ((int)($_POST['edit_id'] ?? 0)) {
        flash('Workout updated.');
        redirect('workout/' . $res['workout_id']);
    }
    q('DELETE FROM workout_drafts WHERE user_id = ?', [$uid]);
    $_SESSION['last_nonce'] = $nonce;
    $a = $res['awards'];
    $_SESSION['complete'] = [
        'wid' => (int)$res['workout_id'], 'points' => (int)$res['points'],
        'level_up' => $a['level_up'], 'quests' => $a['quests'], 'badges' => $a['badges'],
        'quest_steps' => $a['quest_steps'] ?? [], 'quest_done' => $a['quest_done'] ?? [],
    ];
    redirect('workout/' . $res['workout_id']);
}

$exercises = rows("SELECT id, name, slug, muscle, equipment, kind, coef, bw_pct, dist_coef, subs, per_side FROM exercises WHERE active = 1 ORDER BY name");
foreach ($exercises as &$exRow) {
    $exRow['id'] = (int)$exRow['id'];
    $exRow['coef'] = (float)$exRow['coef'];
    $exRow['bw_pct'] = (float)$exRow['bw_pct'];
    $exRow['dist_coef'] = (float)$exRow['dist_coef'];
    $exRow['subs'] = $exRow['subs'] === '' ? [] : explode(',', $exRow['subs']);
    $exRow['per_side'] = (int)$exRow['per_side'];
    $exRow['ref'] = cardio_ref_kmh((string)$exRow['name']);
}
unset($exRow);
$recent = array_map('intval', array_column(rows(
    'SELECT exercise_id, MAX(id) m FROM workout_sets WHERE user_id = ? GROUP BY exercise_id ORDER BY m DESC LIMIT 15', [$uid]), 'exercise_id'));
$freq = array_map('intval', array_column(rows(
    'SELECT exercise_id, COUNT(DISTINCT workout_id) c FROM workout_sets WHERE user_id = ? GROUP BY exercise_id ORDER BY c DESC, MAX(id) DESC LIMIT 15', [$uid]), 'exercise_id'));
$steps = [];
foreach (rows('SELECT exercise_id, step, unit FROM user_exercise_prefs WHERE user_id = ?', [$uid]) as $r) {
    $v = (float)$r['step'];
    if ($r['unit'] !== $u['unit']) {
        $v = $u['unit'] === 'lb' ? round($v * LB_PER_KG, 1) : round($v / LB_PER_KG, 1);
    }
    $steps[(int)$r['exercise_id']] = $v;
}
$counts = [];
foreach (rows('SELECT exercise_id, COUNT(DISTINCT workout_id) c FROM workout_sets WHERE user_id = ? GROUP BY exercise_id', [$uid]) as $r) {
    $counts[(int)$r['exercise_id']] = (int)$r['c'];
}
$routines = [];
foreach (rows('SELECT id, name, payload FROM routines WHERE user_id = ? ORDER BY id DESC', [$uid]) as $r) {
    $ritems = json_decode($r['payload'], true) ?: [];
    foreach ($ritems as &$ri) {
        if (isset($ri['w'])) {
            $ri['wu'] = round(($u['unit'] ?? 'kg') === 'lb' ? (float)$ri['w'] * LB_PER_KG : (float)$ri['w'], 1);
        }
    }
    unset($ri);
    $routines[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'items' => $ritems];
}
$favs = array_map('intval', array_column(rows(
    'SELECT exercise_id FROM favorite_exercises WHERE user_id = ? ORDER BY created_at DESC', [$uid]), 'exercise_id'));
ql_ensure_tables();
$exLists = [];
foreach (rows('SELECT id, name, color, payload FROM ex_lists WHERE user_id = ? ORDER BY pos, id', [$uid]) as $l) {
    $ids = json_decode((string)$l['payload'], true);
    $exLists[] = ['id' => (int)$l['id'], 'name' => $l['name'], 'color' => $l['color'], 'ids' => is_array($ids) ? array_values(array_map('intval', $ids)) : []];
}
$last = last_sessions($uid, $u['unit']);
// Dumbbell weights this member actually uses (used in 2+ sets), so the +/- buttons can hop between them
$dbUsed = [];
foreach (rows("SELECT s.weight_kg w, COUNT(*) c FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id
               WHERE s.user_id = ? AND s.is_warmup = 0 AND s.weight_kg > 0 AND (e.equipment = 'dumbbell' OR e.per_side = 1)
               GROUP BY s.weight_kg HAVING c >= 2 ORDER BY s.weight_kg", [$uid]) as $r) {
    $dbUsed[] = round($u['unit'] === 'lb' ? (float)$r['w'] * LB_PER_KG : (float)$r['w'], 1);
}

$gyms = gyms_for_logger($u);
$_tzNow = new DateTime('now', user_tz($u));
$nowLocal = $_tzNow->format('Y-m-d\TH:i');
$maxLocal = (clone $_tzNow)->modify('+1 hour')->format('Y-m-d\TH:i');

// Unfinished workout (autosaved)
$draft = null;
$dr = row('SELECT payload, updated_at FROM workout_drafts WHERE user_id = ?', [$uid]);
if ($dr) {
    $draft = json_decode($dr['payload'], true) ?: null;
    if ($draft) {
        $draft['saved'] = $dr['updated_at'];
    }
}

// Edit an existing workout: load it into the logger (never touches the unfinished draft)
$editData = null;
if (isset($_GET['edit'])) {
    $ew = row('SELECT * FROM workouts WHERE id = ? AND user_id = ?', [(int)$_GET['edit'], $uid]);
    if ($ew) {
        $byEx = [];
        foreach (rows('SELECT * FROM workout_sets WHERE workout_id = ? ORDER BY id', [$ew['id']]) as $s) {
            $byEx[$s['exercise_id']][] = [
                'w' => $s['weight_kg'] !== null ? (string)round($u['unit'] === 'lb' ? $s['weight_kg'] * LB_PER_KG : (float)$s['weight_kg'], 1) : '',
                'r' => (string)($s['reps'] ?? ''), 't' => (string)($s['duration_sec'] ?? ''),
                'd' => $s['distance_m'] ? (string)round($s['distance_m'] / 1000, 2) : '',
                'wu' => (int)$s['is_warmup'], 'k' => 1,
            ];
        }
        $notesBy = array_column(rows('SELECT exercise_id, note FROM workout_ex_notes WHERE workout_id = ?', [$ew['id']]), 'note', 'exercise_id');
        $its = [];
        foreach ($byEx as $exId => $sets) {
            $its[] = ['id' => (int)$exId, 'sets' => $sets, 'note' => (string)($notesBy[$exId] ?? '')];
        }
        $editData = ['id' => (int)$ew['id'], 'title' => (string)$ew['title'], 'notes' => (string)$ew['notes'],
            'when' => date('Y-m-d\TH:i', strtotime($ew['performed_at'])), 'gym' => (string)(int)$ew['gym_id'], 'items' => $its, 'started' => 1, 'began' => 0, 'ts' => 0];
    }
}

// "Repeat this workout" (only if there is no unfinished draft to protect)
$prefill = [];
$repeatBlocked = false;
if (isset($_GET['repeat'])) {
    $wid = (int)$_GET['repeat'];
    $own = row('SELECT id, title FROM workouts WHERE id = ? AND user_id = ?', [$wid, $uid]);
    if ($own && $draft && !empty($draft['items'])) {
        $repeatBlocked = true;
    } elseif ($own) {
        $byEx = [];
        foreach (rows('SELECT * FROM workout_sets WHERE workout_id = ? ORDER BY id', [$wid]) as $s) {
            $byEx[$s['exercise_id']][] = [
                'w' => $s['weight_kg'] !== null ? (string)round($u['unit'] === 'lb' ? $s['weight_kg'] * LB_PER_KG : (float)$s['weight_kg'], 1) : '',
                'r' => (string)($s['reps'] ?? ''), 't' => (string)($s['duration_sec'] ?? ''),
                'd' => $s['distance_m'] ? (string)round($s['distance_m'] / 1000, 2) : '',
                'wu' => (int)$s['is_warmup'], 'k' => 0,
            ];
        }
        foreach ($byEx as $exId => $sets) {
            $prefill[] = ['id' => (int)$exId, 'sets' => $sets];
        }
        $draft = ['title' => (string)$own['title'], 'items' => $prefill, 'ts' => 0, 'fromRepeat' => true];
    }
}
if ($repeatBlocked) {
    flash('You have an unfinished workout. Finish or discard it first, then repeat the old one.', 'err');
}

$data = [
    'unit' => $u['unit'],
    'bw' => (float)($u['bodyweight_kg'] ?: 75),
    'scale' => POINTS_SCALE,
    'exercises' => $exercises,
    'recent' => $recent,
    'freq' => $freq,
    'favs' => $favs,
    'lists' => $exLists,
    'favUrl' => url('log/fav'),
    'prefUrl' => url('log/pref'),
    'guideUrl' => url('guide/'),
    'steps' => (object)$steps,
    'prefill' => $u['prefill'],
    'counts' => (object)$counts,
    'routines' => $routines,
    'autoRoutine' => (int)($_GET['routine'] ?? 0),
    'routineUrl' => url('log/routine'),
    'dbUsed' => $dbUsed,
    'gyms' => $gyms,
    'gymDefault' => (int)($u['default_gym_id'] ?? 0),
    'last' => (object)$last,
    'draft' => $editData ? null : $draft,
    'edit' => $editData,
    'draftUrl' => url('log/draft'),
    'storeKey' => 'mfl_draft_' . $uid,
];

layout_header('Track workout', 'log');
?>
<div class="logtop">
  <h1 class="pagetitle"><?= $editData ? 'Edit workout' : 'Track a workout' ?></h1>
  <span class="savestate" id="savestate" aria-live="polite">Autosave on</span>
</div>
<?php if ($editData): ?><div class="card verifybar"><span>Editing a saved workout. Changes replace it (points are recalculated).</span><a class="btn ghost small" href="<?= e(url('workout/' . $editData['id'])) ?>">Cancel</a></div><?php endif; ?>
<div id="resumebar" class="resume" hidden>
  <?= mascot('happy', 44) ?>
  <div class="grow"><b>Welcome back!</b> <span id="resumetxt">Your unfinished workout is restored.</span></div>
  <button type="button" class="btn ghost small danger discardbtn" id="discardbtn">Discard</button>
</div>
<form method="post" id="logform" class="logform" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="nonce" value="<?= e(bin2hex(random_bytes(8))) ?>">
  <input type="hidden" name="duration" id="f_dur" value="0">
  <?php if ($editData): ?><input type="hidden" name="edit_id" value="<?= (int)$editData['id'] ?>"><?php endif; ?>

  <div class="card startcard" id="startcard">
    <h2>When did you train?</h2>
    <button type="button" class="btn big wide" id="startnow">▶ Start workout now</button>
    <div class="or"><span>or log one you already did</span></div>
    <label>Date &amp; time
      <input type="datetime-local" name="performed_at" id="f_when" value="<?= e($nowLocal) ?>">
    </label>
    <button type="button" class="btn ghost wide" id="startpast">Continue</button>
  </div>

  <div id="phase2" hidden>
    <div class="card sessionbar">
      <div class="sessrow">
        <div><div class="timer" id="timer">00:00</div><div class="small muted" id="sesssub">0 exercises · 0 sets</div></div>
        <div class="sessright small muted"><span id="sesswhen"></span><button type="button" class="btn ghost small danger discardbtn" id="discardtop">Discard</button></div>
      </div>
      <details class="sessdet">
        <summary>Title, gym &amp; time</summary>
        <label>Title <small class="muted">(optional)</small>
          <input name="title" id="f_title" maxlength="80" placeholder="Push day, Morning walk…">
        </label>
        <label class="gymsel">Gym <small class="muted">(optional)</small>
          <select name="gym_id" id="f_gym">
            <option value="0">No gym / not set</option>
            <?php foreach ($gyms as $gy): ?><option value="<?= (int)$gy['id'] ?>"><?= $gy['home'] ? '🏠 ' : '' ?><?= e($gy['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <a class="small muted" href="<?= e(url('gyms')) ?>">Manage or find gyms</a>
      </details>
    </div>

    <div id="exercises"></div>

    <div class="card addcard">
      <div id="recentChips" class="chips"></div>
      <button type="button" class="btn wide" id="openpicker">+ Add exercise <small>(<?= count($exercises) ?>)</small></button>
    </div>

    <div class="card routinebox" id="routinebox" hidden>
      <b>Save as routine</b> <span class="muted small">to start this workout again with one tap</span>
      <div class="commentform"><input id="routinename" maxlength="60" placeholder="e.g. Upper body"><button type="button" class="btn small" id="routinesave">Save</button></div>
      <div class="small muted" id="routinemsg" role="status"></div>
    </div>

    <div class="card">
      <label>Notes for the whole workout <small class="muted">(optional)</small>
        <textarea name="notes" id="f_notes" rows="2" maxlength="1000" placeholder="How did it feel?"></textarea>
      </label>
    </div>

    <div class="savebar">
      <div class="estbox"><b id="estpts">0</b> points <small class="muted">estimated</small></div>
      <button class="btn big" type="submit" id="savebtn"><?= $editData ? 'Save changes' : 'Finish workout' ?></button>
    </div>
  </div>
  <div id="hidden"></div>
</form>

<div class="sheet" id="picker" hidden role="dialog" aria-modal="true" aria-label="Add exercise">
  <div class="sheetbox">
    <div class="sheethead">
      <input type="search" id="exsearch" placeholder="Search exercises, e.g. bench, walk, yoga…" autocomplete="off" enterkeyhint="search">
      <button type="button" class="btn ghost small" id="closepicker">Close</button>
    </div>
    <div class="filters scrollx" id="musclefilter"></div>
    <div class="filters scrollx" id="equipfilter"></div>
    <div id="bodywrap" class="bodywrap" hidden></div>
    <div id="activefilter" class="activefilter" hidden></div>
    <div id="exresults" class="results"></div>
  </div>
</div>

<dialog id="guide" class="modal guide">
  <h3 id="guidetitle"></h3>
  <p class="muted small" id="guidemuscles"></p>
  <ol id="guidesteps" class="guidesteps"></ol>
  <p><a id="guidevideo" class="btn ghost small" href="#" target="_blank" rel="noopener noreferrer">▶ Watch how it's done</a> <a id="guidepage" class="muted small" href="#" target="_blank">Full exercise page</a></p>
  <div class="modalbtns"><button type="button" class="btn" id="guideclose">Got it</button></div>
</dialog>

<div class="fsave" id="fsave" aria-live="polite" hidden>
  <span class="fsv-glow"></span>
  <?= fito('lift', 200, 'fsv') ?>
  <div class="fsv-pts"><span id="fsvpts">0</span><small>points</small></div>
  <div class="fsv-msg">Saving your workout…</div>
  <div class="fsv-bar"><i></i></div>
</div>

<dialog id="unticked" class="modal">
  <h3>Some sets aren't ticked</h3>
  <p class="muted"><b id="unn">0</b> planned sets (pre-filled from last time) are not ticked as done. Only ticked sets count.</p>
  <div class="modalbtns">
    <button type="button" class="btn" id="tickall">Tick all &amp; finish</button>
    <button type="button" class="btn ghost" id="skipun">Skip them &amp; finish</button>
    <button type="button" class="btn ghost" id="cancelun">Go back</button>
  </div>
</dialog>
<script type="application/json" id="fit-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php layout_footer('log', ['body.js', 'search-terms.js', 'logger.js']); ?>
