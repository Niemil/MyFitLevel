<?php
declare(strict_types=1);

/*
 * Finer muscle regions (for the body map + heat map) and "weight per hand / per side" rules.
 * Derived from exercise name + group + equipment, so new built-in exercises get tagged automatically.
 */

function muscle_regions(): array
{
    return [
        'chest' => 'Chest', 'shoulders' => 'Shoulders', 'biceps' => 'Biceps', 'triceps' => 'Triceps',
        'forearms' => 'Forearms', 'abs' => 'Abs', 'obliques' => 'Obliques', 'quads' => 'Quads',
        'calves' => 'Calves', 'traps' => 'Traps', 'lats' => 'Lats', 'lowback' => 'Lower back',
        'glutes' => 'Glutes', 'hamstrings' => 'Hamstrings',
    ];
}

/** Ordered list of muscle regions; first = primary (full credit), others = secondary (half credit). */
function exercise_subs(string $name, string $group, string $equip): array
{
    $n = strtolower($name);
    $rules = [
        ['/wrist curl/', ['forearms']],
        ['/reverse curl/', ['forearms', 'biceps']],
        ['/zottman|hammer curl/', ['biceps', 'forearms']],
        ['/leg curl|nordic|glute-ham/', ['hamstrings']],
        ['/farmer|suitcase|sandbag carry|yoke/', ['forearms', 'traps']],
        ['/dead hang|chin-up hold/', ['forearms', 'lats']],
        ['/shrug/', ['traps']],
        ['/face pull|rear delt|reverse pec deck|y-raise|cuban|upright row/', ['shoulders', 'traps']],
        ['/chest dip|dumbbell pullover/', ['chest', 'triceps']],
        ['/close-grip bench|jm press|diamond|triceps|skull|kickback|tate press|pushdown|bench dip|machine dip|ring dip|triceps dip/', ['triceps', 'chest']],
        ['/glute kickback/', ['glutes']],
        ['/lateral raise|front raise|overhead press|push press|shoulder press|arnold|landmine press|z press|pike push|handstand|wall walk|kettlebell press/', ['shoulders', 'triceps']],
        ['/bench press|chest press|push-up|fly|crossover|pec deck|svend|squeeze press|floor press/', ['chest', 'triceps', 'shoulders']],
        ['/lat pulldown|pulldown|pull-up|chin-up|muscle-up|straight-arm/', ['lats', 'biceps']],
        ['/row/', ['lats', 'traps', 'biceps']],
        ['/romanian|stiff-leg|good morning/', ['hamstrings', 'glutes', 'lowback']],
        ['/deadlift|rack pull/', ['lowback', 'glutes', 'hamstrings']],
        ['/back extension|reverse hyper|superman/', ['lowback', 'glutes']],
        ['/leg extension|sissy/', ['quads']],
        ['/calf raise|tibialis/', ['calves']],
        ['/hip thrust|glute bridge|donkey kick|fire hydrant|frog pump|pull-through|abduction|adduction|lateral walk/', ['glutes', 'hamstrings']],
        ['/squat|leg press|lunge|step-up|wall sit|box jump|jump/', ['quads', 'glutes']],
        ['/side plank|side bend|woodchopper|russian twist|bicycle|pallof|landmine twist|copenhagen/', ['obliques', 'abs']],
        ['/mountain climber|bear crawl|plank shoulder/', ['abs', 'shoulders']],
        ['/bird dog/', ['abs', 'lowback']],
        ['/clean|snatch/', ['traps', 'quads', 'glutes']],
        ['/swing/', ['glutes', 'hamstrings']],
        ['/burpee/', ['quads', 'chest']],
        ['/battle rope|rope climb/', ['shoulders', 'lats']],
        ['/turkish/', ['shoulders', 'abs']],
        ['/sled|tire flip/', ['quads', 'glutes']],
        ['/slam|wall ball/', ['shoulders', 'abs']],
        ['/thruster|devil press/', ['quads', 'shoulders']],
    ];
    foreach ($rules as [$re, $subs]) {
        if (preg_match($re, $n)) {
            return $subs;
        }
    }
    switch ($group) {
        case 'chest': return ['chest', 'triceps'];
        case 'back': return ['lats', 'traps'];
        case 'shoulders': return ['shoulders'];
        case 'biceps': return ['biceps'];
        case 'triceps': return ['triceps'];
        case 'legs': return ['quads', 'glutes'];
        case 'core': return ['abs'];
    }
    return [];
}

/** 0 = normal, 1 = weight is per hand (one dumbbell), 2 = weight is per side (one cable stack). */
function exercise_side_mode(string $name, string $equip): int
{
    $n = strtolower($name);
    if ($equip === 'dumbbell') {
        return preg_match('/goblet|pullover|squeeze press|overhead triceps extension|side bend|suitcase/', $n) ? 0 : 1;
    }
    if ($equip === 'cable' && preg_match('/crossover|cable fly|rear delt fly/', $n)) {
        return 2;
    }
    return 0;
}

/** Heat per region for a user over the last $days days: [region => weighted working sets]. */
function muscle_heat(int $uid, int $days): array
{
    $since = date('Y-m-d H:i:s', time() - $days * 86400);
    $out = [];
    foreach (rows(
        "SELECT e.subs s, COUNT(*) c FROM workout_sets ws
         JOIN workouts w ON w.id = ws.workout_id
         JOIN exercises e ON e.id = ws.exercise_id
         WHERE ws.user_id = ? AND ws.is_warmup = 0 AND w.performed_at >= ? AND e.subs <> ''
         GROUP BY e.subs", [$uid, $since]) as $r) {
        foreach (explode(',', $r['s']) as $i => $m) {
            $out[$m] = ($out[$m] ?? 0) + (int)$r['c'] * ($i === 0 ? 1.0 : 0.5);
        }
    }
    return $out;
}


/** Written how-to steps for a built-in exercise, or null. (Loaded on demand; the file is large.) */
function exercise_guide(string $name): ?array
{
    static $g = null;
    if ($g === null) {
        $g = is_file(__DIR__ . '/guides.php') ? (require __DIR__ . '/guides.php') : [];
    }
    return $g[$name] ?? null;
}

function exercise_video_url(string $name): string
{
    return 'https://www.youtube.com/results?search_query=' . rawurlencode('how to do ' . $name . ' proper form');
}

/* ================= Muscle levels ================= */

/** The six body areas used for the weekly balance bonus. */
function muscle_areas(): array
{
    return [
        'Chest' => ['chest'], 'Back' => ['lats', 'traps', 'lowback'], 'Shoulders' => ['shoulders'],
        'Arms' => ['biceps', 'triceps', 'forearms'], 'Core' => ['abs', 'obliques'], 'Legs' => ['quads', 'hamstrings', 'glutes', 'calves'],
    ];
}

function muscle_level_for(float $xp): int
{
    return (int)floor(sqrt(max(0, $xp) / 20));
}

/** Total XP a muscle needs for a level (level n = 20 x n x n). */
function muscle_xp_for(int $level): int
{
    return 20 * $level * $level;
}

/** Muscle XP and levels from every working set ever logged: 10 XP per set for the main muscle, 5 for helpers. */
function muscle_stats(int $uid): array
{
    static $cache = [];
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }
    $areaOf = [];
    foreach (muscle_areas() as $a => $regs) {
        foreach ($regs as $r) { $areaOf[$r] = $a; }
    }
    $xp = array_fill_keys(array_keys(muscle_regions()), 0.0);
    $weeks = [];
    foreach (rows(
        "SELECT e.subs s, YEARWEEK(w.performed_at, 3) wk, COUNT(*) c FROM workout_sets ws
         JOIN workouts w ON w.id = ws.workout_id JOIN exercises e ON e.id = ws.exercise_id
         WHERE ws.user_id = ? AND ws.is_warmup = 0 AND e.subs <> '' GROUP BY e.subs, wk", [$uid]) as $r) {
        foreach (explode(',', $r['s']) as $i => $m) {
            if (!isset($xp[$m])) { continue; }
            $xp[$m] += (int)$r['c'] * ($i === 0 ? 10 : 5);
            if (isset($areaOf[$m])) { $weeks[$r['wk']][$areaOf[$m]] = true; }
        }
    }
    $lv = array_map('muscle_level_for', $xp);
    $full = 0;
    foreach ($weeks as $w) {
        if (count($w) >= count(muscle_areas())) { $full++; }
    }
    return $cache[$uid] = [
        'xp' => $xp, 'lv' => $lv, 'full_weeks' => $full, 'top' => $lv ? max($lv) : 0,
        'trained' => count(array_filter($xp, fn($x) => $x >= 20)), 'lv3' => count(array_filter($lv, fn($l) => $l >= 3)),
    ];
}

/** Which body areas were trained in the last 7 days, and the damage bonus that gives (2% per area, up to 12%). */
function muscle_balance(int $uid): array
{
    static $cache = [];
    $key = $uid . ':' . date('YmdH');
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $heat = muscle_heat($uid, 7);
    $areas = [];
    foreach (muscle_areas() as $a => $regs) {
        $areas[$a] = (bool)array_filter($regs, fn($r) => ($heat[$r] ?? 0) > 0);
    }
    $n = count(array_filter($areas));
    return $cache[$key] = ['areas' => $areas, 'n' => $n, 'bonus' => 2 * $n];
}
