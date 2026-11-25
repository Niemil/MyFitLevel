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
