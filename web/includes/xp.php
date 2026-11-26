<?php
declare(strict_types=1);

/* ================= Levels ================= */

/*
 * Level curve (our own design): 100 levels, tuned for real life instead of for any old site.
 *  - v3.31 retune: steps, seasons and daily rewards now add points on top of workouts, so the curve was rebuilt with those in mind.
 *  - An active member (3-4 sessions a week, daily steps, weekly goals, quests, seasons) reaches level 50 after roughly a year and level 100 after roughly three.
 *  - A relaxed member (2 sessions a week, 5k steps) is around level 40 after a year, 70+ after three and 100 after about five. A hard grinder gets to 100 in about two years.
 *  - The first workout is level 2, a first week lands around level 4-5, then each level takes more time than the last.
 * Totals are interpolated (log-log) between anchor levels and rounded to the nearest 50.
 * Admins can speed up / slow down levelling with the "level speed" multiplier.
 */
const LEVEL_MAX = 100;

function level_anchors(): array
{
    // level => total points needed
    return [1 => 0.0, 2 => 250.0, 10 => 7000.0, 20 => 30000.0, 30 => 70000.0, 50 => 210000.0, 75 => 470000.0, 100 => 850000.0];
}

function level_base(int $l): float
{
    if ($l <= 1) {
        return 0.0;
    }
    $a = level_anchors();
    $l = min($l, LEVEL_MAX);
    $ks = array_keys($a);
    for ($i = 1; $i < count($ks) - 1; $i++) {
        if ($l <= $ks[$i + 1]) {
            $x0 = $ks[$i] - 1; $x1 = $ks[$i + 1] - 1; $x = $l - 1;
            $t = log($x / $x0) / log($x1 / $x0);
            return $a[$ks[$i]] * (($a[$ks[$i + 1]] / $a[$ks[$i]]) ** $t);
        }
    }
    return $a[LEVEL_MAX];
}

/** A fun rank name for a level. */
function level_title(int $l): string
{
    $t = [1 => 'Rookie', 5 => 'Warm-up', 10 => 'Gym Newbie', 20 => 'Regular', 30 => 'Iron Apprentice', 40 => 'Gym Rat',
          50 => 'Half-Century', 60 => 'Powerhouse', 70 => 'Veteran', 80 => 'Titan', 90 => 'Legend', 100 => 'Mythic'];
    $out = 'Rookie';
    foreach ($t as $k => $v) {
        if ($l >= $k) {
            $out = $v;
        }
    }
    return $out;
}

function level_table(): array
{
    $t = [1 => 0];
    for ($l = 2; $l <= LEVEL_MAX; $l++) {
        $t[$l] = (int)(round(level_base($l) / 50) * 50);
    }
    return $t;
}

function level_scale(): float
{
    return max(0.25, min(10.0, (float)str_replace(',', '.', (string)setting('level_scale', '1'))));
}

function xp_for_level(int $lvl): int
{
    if ($lvl <= 1) {
        return 0;
    }
    $base = round(level_base($lvl) / 50) * 50;
    return (int)round($base * level_scale());
}

function level_from_xp(int $xp): int
{
    $l = 1;
    while ($l < LEVEL_MAX && xp_for_level($l + 1) <= $xp) {
        $l++;
    }
    return $l;
}

function level_progress(int $xp): array
{
    $l = level_from_xp($xp);
    $cur = xp_for_level($l);
    if ($l >= LEVEL_MAX) {
        // max level: bar stays full
        return ['level' => $l, 'cur' => $cur, 'next' => $cur, 'into' => 1, 'need' => 1, 'pct' => 100, 'max' => true];
    }
    $next = xp_for_level($l + 1);
    return [
        'level' => $l,
        'cur'   => $cur,
        'next'  => $next,
        'into'  => $xp - $cur,
        'need'  => $next - $cur,
        'pct'   => (int)min(100, max(0, round(($xp - $cur) / max(1, $next - $cur) * 100))),
    ];
}

function recalc_xp(int $uid): array
{
    staff_ensure();
    $w = (int)val('SELECT COALESCE(SUM(points),0) FROM workouts WHERE user_id = ?', [$uid]);
    $q = (int)val('SELECT COALESCE(SUM(reward),0) FROM user_quests WHERE user_id = ?', [$uid]);
    $qs = (int)val('SELECT COALESCE(SUM(reward),0) FROM quest_steps WHERE user_id = ?', [$uid]);
    $ev = event_bonus_total($uid);   // boost events: derived from the workouts inside each event window
    $xp = $w + $q + $qs + $ev + game_steps_points($uid) + (int)val('SELECT xp_adjust FROM users WHERE id = ?', [$uid]);
    $xp = max(0, $xp);
    $lvl = level_from_xp($xp);
    q('UPDATE users SET xp = ?, level = ? WHERE id = ?', [$xp, $lvl, $uid]);
    return ['xp' => $xp, 'level' => $lvl];
}

/* ================= Points ================= */

const DURATION_SCALE = 3.0;  // timed holds were far below everything else: a 2-minute plank paid 6 pts, a set of curls 15
const CARDIO_SCALE = 6.6;   // points per old-style cardio unit; anchored so a 10:00/mile run for 1 mile = ~82 pts
const CARDIO_DIST_EXP  = 1.2;   // long distances are disproportionately hard: 2x the distance = 2.3x the points
const CARDIO_SPEED_EXP = 1.3;   // faster than the reference pace pays extra, slower pays less

/** Typical pace (km/h) for an activity; the pace at which time-only logging is scored. */
function cardio_ref_kmh(string $name): float
{
    $n = strtolower($name);
    $map = ['swim' => 2.0, 'jogging' => 8.5, 'running' => 9.7, 'run' => 9.7, 'row' => 12.0, 'ski erg' => 10.0, 'cross-country' => 9.0,
            'ski touring' => 4.0, 'orienteering' => 7.0, 'kayak' => 5.0, 'canoe' => 4.5, 'paddle' => 4.5, 'rucking' => 4.5,
            'snowshoe' => 3.5, 'hiking' => 4.0, 'walk' => 5.0, 'mountain bik' => 15.0, 'e-bike' => 22.0, 'cycling' => 20.0,
            'stationary bike' => 20.0, 'skating' => 15.0, 'elliptical' => 8.0];
    foreach ($map as $k => $v) {
        if (strpos($n, $k) !== false) {
            return $v;
        }
    }
    return 6.0;
}

function cardio_points(array $ex, int $durSec, int $distM): float
{
    $coef = (float)$ex['coef'];
    $dc = (float)($ex['dist_coef'] ?? 0);
    if ($dc > 0 && $distM > 0) {
        $km = $distM / 1000;
        $ref = cardio_ref_kmh((string)($ex['name'] ?? ''));
        $v = $durSec > 0 ? $km / ($durSec / 3600) : $ref;
        $ratio = max(0.4, min(2.5, $v / $ref));
        return $dc * CARDIO_SCALE * ($km ** CARDIO_DIST_EXP) * ($ratio ** CARDIO_SPEED_EXP);
    }
    return $coef * CARDIO_SCALE * ($durSec / 60);
}

function set_points(array $ex, array $s, float $bodyweightKg): array
{
    $kg   = (float)($s['weight_kg'] ?? 0);
    $reps = (int)($s['reps'] ?? 0);
    $dur  = (int)($s['duration_sec'] ?? 0);
    $dist = (int)($s['distance_m'] ?? 0);
    $coef = (float)$ex['coef'];
    $pts = 0.0;
    $score = 0.0;

    // Hand-held weights are logged per hand; the work of a set is both hands.
    $side = ((int)($ex['per_side'] ?? 0) === 1 || ($ex['equipment'] ?? '') === 'dumbbell') ? 2.0 : 1.0;

    switch ($ex['kind']) {
        case 'weight':
            $load  = $kg * $side;
            $score = $load * (1 + min($reps, 12) / 30);          // Epley estimated 1RM (valid up to ~12 reps)
            $pts   = $reps > 0 ? $coef * (max(0.0, $score) ** LOAD_EXP) * POINTS_SCALE : 0.0;
            break;
        case 'bodyweight':
            $load  = $bodyweightKg * (float)$ex['bw_pct'] + $kg;
            $score = $load * (1 + min($reps, 12) / 30);
            $pts   = $reps > 0 ? $coef * (max(0.0, $score) ** LOAD_EXP) * POINTS_SCALE : 0.0;
            break;
        case 'cardio':
            $pts = cardio_points($ex, $dur, $dist);
            break;
        case 'duration':
            $pts = $coef * DURATION_SCALE * ($dur / 60);
            break;
    }
    return ['points' => max(1, (int)round($pts)), 'score' => round($score, 2)];
}

/* ================= Stats / badges / quests ================= */

function user_stats(int $uid): array
{
    $st = [];
    $w = row('SELECT COUNT(*) c, COALESCE(SUM(volume_kg),0) v, COALESCE(SUM(set_count),0) s FROM workouts WHERE user_id = ?', [$uid]);
    $st['workouts'] = (int)$w['c'];
    $st['volume_kg'] = (float)$w['v'];
    $st['sets'] = (int)$w['s'];
    $st['prs'] = (int)val('SELECT COUNT(*) FROM workout_sets WHERE user_id = ? AND is_pr = 1', [$uid]);
    $st['distinct_exercises'] = (int)val('SELECT COUNT(DISTINCT exercise_id) FROM workout_sets WHERE user_id = ?', [$uid]);
    $st['cardio_min'] = (int)round((float)val(
        "SELECT COALESCE(SUM(s.duration_sec),0)/60 FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id
         WHERE s.user_id = ? AND e.kind = 'cardio'", [$uid]));
    $st['workouts_7d'] = (int)val('SELECT COUNT(*) FROM workouts WHERE user_id = ? AND performed_at >= ?', [$uid, date('Y-m-d H:i:s', time() - 7 * 86400)]);

    foreach (['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'core', 'fullbody'] as $m) {
        $st['sets_' . $m] = 0;
    }
    foreach (rows('SELECT e.muscle m, COUNT(*) c FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.user_id = ? AND s.is_warmup = 0 GROUP BY e.muscle', [$uid]) as $r) {
        $st['sets_' . $r['m']] = (int)$r['c'];
    }
    $st['sets_arms'] = $st['sets_biceps'] + $st['sets_triceps'];

    $st['following'] = (int)val('SELECT COUNT(*) FROM follows WHERE follower_id = ?', [$uid]);
    $st['followers'] = (int)val('SELECT COUNT(*) FROM follows WHERE followee_id = ?', [$uid]);
    $st['props_given'] = (int)val('SELECT COUNT(*) FROM props WHERE user_id = ?', [$uid]);
    $st['comments'] = (int)val('SELECT COUNT(*) FROM comments WHERE user_id = ?', [$uid]);
    $st['groups'] = (int)val('SELECT COUNT(*) FROM group_members WHERE user_id = ?', [$uid]);
    $st['team_member'] = 0; $st['team_founded'] = 0; $st['boss_kills'] = 0;
    if (setting('game_tables_ok1') === '1') {
        $st['team_member'] = (int)val('SELECT COUNT(*) FROM team_members WHERE user_id = ?', [$uid]);
        $st['team_founded'] = (int)val("SELECT COUNT(*) FROM game_ledger WHERE user_id = ? AND kind = 'team_create'", [$uid]);
        $st['boss_kills'] = (int)val('SELECT COALESCE(kills,0) FROM game_wallet WHERE user_id = ?', [$uid]);
    }
    $st['steps_total'] = 0; $st['steps_best'] = 0; $st['steps_10k_days'] = 0;
    if (setting('game_tables_ok9') === '1') {
        $sr = row('SELECT COALESCE(SUM(steps),0) t, COALESCE(MAX(steps),0) b, COALESCE(SUM(steps >= 10000),0) d FROM daily_steps WHERE user_id = ?', [$uid]) ?: [];
        $st['steps_total'] = (int)($sr['t'] ?? 0); $st['steps_best'] = (int)($sr['b'] ?? 0); $st['steps_10k_days'] = (int)($sr['d'] ?? 0);
    }
    $st['level'] = (int)val('SELECT level FROM users WHERE id = ?', [$uid]);
    $st['gym_hours'] = (float)val('SELECT COALESCE(SUM(duration_sec),0)/3600 FROM workouts WHERE user_id = ?', [$uid]);
    // Strength relative to bodyweight (estimated 1RM as a percent of bodyweight)
    foreach (['bench_pct', 'squat_pct', 'dead_pct', 'ohp_pct', 'row_pct'] as $k) {
        $st[$k] = 0;
    }
    $bwKg = (float)val('SELECT bodyweight_kg FROM users WHERE id = ?', [$uid]);
    if ($bwKg > 20) {
        $map = ['Barbell Bench Press' => 'bench_pct', 'Back Squat' => 'squat_pct', 'Deadlift' => 'dead_pct', 'Overhead Press' => 'ohp_pct', 'Barbell Row' => 'row_pct'];
        foreach (rows('SELECT e.name n, MAX(s.weight_kg * (1 + LEAST(s.reps, 12) / 30)) b FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id
                       WHERE s.user_id = ? AND s.is_warmup = 0 AND s.weight_kg > 0 AND s.reps > 0 AND e.name IN (?,?,?,?,?) GROUP BY e.name', array_merge([$uid], array_keys($map))) as $r) {
            $st[$map[$r['n']]] = (int)round((float)$r['b'] / $bwKg * 100);
        }
    }

    // Day streak (consecutive days with a workout, ending today or yesterday)
    $days = array_column(rows('SELECT DISTINCT DATE(performed_at) d FROM workouts WHERE user_id = ? ORDER BY d DESC LIMIT 400', [$uid]), 'd');
    $streak = 0;
    if ($days) {
        $cursor = strtotime(date('Y-m-d'));
        if ($days[0] !== date('Y-m-d') && $days[0] === date('Y-m-d', $cursor - 86400)) {
            $cursor -= 86400;
        }
        foreach ($days as $d) {
            if ($d === date('Y-m-d', $cursor)) {
                $streak++;
                $cursor -= 86400;
            } elseif ($d > date('Y-m-d', $cursor)) {
                continue;
            } else {
                break;
            }
        }
    }
    $st['streak'] = $streak;
    return $st;
}

function badge_defs(): array
{
    // code => [name, description, icon, metric, target]
    return [
        'first_workout' => ['First Rep', 'Log your first workout', '🏁', 'workouts', 1],
        'workouts_10'   => ['Getting Hooked', 'Log 10 workouts', '🔥', 'workouts', 10],
        'workouts_50'   => ['Regular', 'Log 50 workouts', '💪', 'workouts', 50],
        'workouts_100'  => ['Centurion', 'Log 100 workouts', '🏛️', 'workouts', 100],
        'workouts_250'  => ['Iron Habit', 'Log 250 workouts', '⚙️', 'workouts', 250],
        'workouts_500'  => ['Lifer', 'Log 500 workouts', '👑', 'workouts', 500],
        'pr_1'          => ['New Peak', 'Set your first personal record', '📈', 'prs', 1],
        'pr_10'         => ['Record Breaker', 'Set 10 personal records', '🏆', 'prs', 10],
        'pr_50'         => ['PR Machine', 'Set 50 personal records', '🚀', 'prs', 50],
        'streak_3'      => ['Hat Trick', 'Work out 3 days in a row', '🔗', 'streak', 3],
        'streak_7'      => ['Full Week', 'Work out 7 days in a row', '📅', 'streak', 7],
        'streak_14'     => ['Two Weeks Strong', 'Work out 14 days in a row', '⚡', 'streak', 14],
        'streak_30'     => ['Unstoppable', 'Work out 30 days in a row', '🌋', 'streak', 30],
        'vol_10k'       => ['Ten Tonnes', 'Lift 10,000 kg in total', '🪨', 'volume_kg', 10000],
        'vol_100k'      => ['Hundred Tonnes', 'Lift 100,000 kg in total', '🏗️', 'volume_kg', 100000],
        'vol_1m'        => ['Megaton', 'Lift 1,000,000 kg in total', '🌍', 'volume_kg', 1000000],
        'level_5'       => ['Level 5', 'Reach level 5', '⭐', 'level', 5],
        'level_10'      => ['Level 10', 'Reach level 10', '🌟', 'level', 10],
        'level_25'      => ['Level 25', 'Reach level 25', '💫', 'level', 25],
        'level_50'      => ['Level 50', 'Reach level 50', '✨', 'level', 50],
        'level_100'     => ['Level 100', 'Reach level 100', '🔱', 'level', 100],
        'explorer'      => ['Explorer', 'Log 25 different exercises', '🧭', 'distinct_exercises', 25],
        'cardio_10h'    => ['Engine', 'Log 10 hours of cardio', '🫀', 'cardio_min', 600],
        'first_follow'  => ['Hello There', 'Follow another member', '👋', 'following', 1],
        'followers_10'  => ['Popular', 'Get 10 followers', '🎉', 'followers', 10],
        'props_25'      => ['Cheerleader', 'Give props 25 times', '👏', 'props_given', 25],
        'first_comment' => ['Chatterbox', 'Write your first comment', '💬', 'comments', 1],
        'group_joined'  => ['Team Player', 'Join a group', '🤝', 'groups', 1],
        'team_joined'   => ['Squad Up', 'Join a team', '🛡️', 'team_member', 1],
        'team_founder'  => ['Team Founder', 'Start your own team', '🏰', 'team_founded', 1],
        'boss_1'        => ['Boss Slayer', 'Help take down a boss', '⚔️', 'boss_kills', 1],
        'boss_10'       => ['Boss Hunter', 'Help take down 10 bosses', '🐉', 'boss_kills', 10],
        'boss_25'       => ['Dragon Bane', 'Help take down 25 bosses', '🐲', 'boss_kills', 25],
        'step_10k'      => ['Walker', 'Walk 10,000 steps in one day', '👟', 'steps_best', 10000],
        'step_20k'      => ['Trail Blazer', 'Walk 20,000 steps in one day', '🥾', 'steps_best', 20000],
        'step_100k'     => ['Long Way', 'Walk 100,000 steps in total', '🛣️', 'steps_total', 100000],
        'step_1m'       => ['Million Steps', 'Walk 1,000,000 steps in total', '🌍', 'steps_total', 1000000],
    ];
}

const QUEST_MULT = 3;   // quest and milestone rewards are paid at 3x so side goals matter next to the sets you log

function quest_defs(): array
{
    $out = [];
    foreach (quest_defs_base() as $code => $d) {
        $d[4] = (int)$d[4] * QUEST_MULT;
        $out[$code] = $d;
    }
    return $out;
}

function quest_defs_base(): array
{
    // code => [name, description, metric, target, reward]
    return [
        'first_steps'  => ['First Steps', 'Log your first workout', 'workouts', 1, 50],
        'three_in_week'=> ['Consistency', 'Log 3 workouts within 7 days', 'workouts_7d', 3, 100],
        'century_sets' => ['Century Club', 'Complete 100 sets', 'sets', 100, 150],
        'leg_day'      => ['Leg Day Initiation', 'Complete 30 sets of leg exercises', 'sets_legs', 30, 150],
        'push_it'      => ['Push It', 'Complete 30 sets of chest exercises', 'sets_chest', 30, 150],
        'back_basics'  => ['Back to Basics', 'Complete 30 sets of back exercises', 'sets_back', 30, 150],
        'shoulder_up'  => ['Boulder Shoulders', 'Complete 30 sets of shoulder exercises', 'sets_shoulders', 30, 150],
        'arm_day'      => ['Arm Day', 'Complete 40 sets for biceps and triceps', 'sets_arms', 40, 150],
        'core_strong'  => ['Core Strength', 'Complete 30 core sets', 'sets_core', 30, 120],
        'cardio_hour'  => ['Heart Starter', 'Log 60 minutes of cardio', 'cardio_min', 60, 100],
        'cardio_10h'   => ['Cardio Veteran', 'Log 10 hours of cardio', 'cardio_min', 600, 400],
        'ton_up'       => ['Ton Up', 'Lift 10,000 kg in total', 'volume_kg', 10000, 300],
        'pr_hunter'    => ['PR Hunter', 'Set 5 personal records', 'prs', 5, 250],
        'week_streak'  => ['Seven Day Streak', 'Work out 7 days in a row', 'streak', 7, 400],
        'social_3'     => ['Making Friends', 'Follow 3 members', 'following', 3, 60],
        'props_10'     => ['Good Vibes', 'Give props 10 times', 'props_given', 10, 80],
        'walker_1'     => ['Out for a Walk', 'Walk 10,000 steps in one day', 'steps_best', 10000, 100],
        'walker_5'     => ['Five Good Days', 'Reach 10,000 steps on 5 different days', 'steps_10k_days', 5, 250],
        'walker_20'    => ['Always Moving', 'Reach 10,000 steps on 20 different days', 'steps_10k_days', 20, 700],
        'steps_100k'   => ['Hundred Thousand', 'Walk 100,000 steps in total', 'steps_total', 100000, 300],
        'steps_500k'   => ['Half a Million', 'Walk 500,000 steps in total', 'steps_total', 500000, 1200],
        'join_team'    => ['Squad Up', 'Join a team', 'team_member', 1, 60],
        'found_team'   => ['Guild Master', 'Start your own team', 'team_founded', 1, 100],
        'first_boss'   => ['Monster Slayer', 'Help take down a boss', 'boss_kills', 1, 150],
        'boss_5'       => ['Boss Hunter', 'Help take down 5 bosses', 'boss_kills', 5, 400],
        'ten_workouts' => ['Getting Serious', 'Log 10 workouts', 'workouts', 10, 150],
        'fifty_workouts'=> ['Regular', 'Log 50 workouts', 'workouts', 50, 500],
        'volume_100k'  => ['Hundred Tonnes', 'Lift 100,000 kg in total', 'volume_kg', 100000, 1000],
        'hundred_workouts' => ['Centurion', 'Log 100 workouts', 'workouts', 100, 1000],
        'streak_30'    => ['Unbreakable', 'Work out 30 days in a row', 'streak', 30, 1500],
        'gym_10h'      => ['Regular at the Gym', 'Spend 10 hours training (timed workouts)', 'gym_hours', 10, 150],
        'gym_50h'      => ['Gym Resident', 'Spend 50 hours training (timed workouts)', 'gym_hours', 50, 600],
        'gym_100h'     => ['Iron Home', 'Spend 100 hours training (timed workouts)', 'gym_hours', 100, 1200],
        'bench_75'     => ['Bench: Three Quarters', 'Bench press an estimated 0.75x your bodyweight', 'bench_pct', 75, 150],
        'bench_100'    => ['Bench: Your Bodyweight', 'Bench press an estimated 1x your bodyweight', 'bench_pct', 100, 300],
        'bench_150'    => ['Bench: One and a Half', 'Bench press an estimated 1.5x your bodyweight', 'bench_pct', 150, 700],
        'squat_100'    => ['Squat: Your Bodyweight', 'Squat an estimated 1x your bodyweight', 'squat_pct', 100, 200],
        'squat_150'    => ['Squat: One and a Half', 'Squat an estimated 1.5x your bodyweight', 'squat_pct', 150, 450],
        'squat_200'    => ['Squat: Double', 'Squat an estimated 2x your bodyweight', 'squat_pct', 200, 900],
        'dead_150'     => ['Deadlift: One and a Half', 'Deadlift an estimated 1.5x your bodyweight', 'dead_pct', 150, 250],
        'dead_200'     => ['Deadlift: Double', 'Deadlift an estimated 2x your bodyweight', 'dead_pct', 200, 500],
        'dead_250'     => ['Deadlift: Two and a Half', 'Deadlift an estimated 2.5x your bodyweight', 'dead_pct', 250, 1000],
        'ohp_50'       => ['Press: Half Your Bodyweight', 'Overhead press an estimated 0.5x your bodyweight', 'ohp_pct', 50, 250],
        'ohp_75'       => ['Press: Three Quarters', 'Overhead press an estimated 0.75x your bodyweight', 'ohp_pct', 75, 600],
        'row_75'       => ['Row: Three Quarters', 'Barbell row an estimated 0.75x your bodyweight', 'row_pct', 75, 200],
        'row_100'      => ['Row: Your Bodyweight', 'Barbell row an estimated 1x your bodyweight', 'row_pct', 100, 400],
    ];
}

function metric_value(array $stats, string $metric): float
{
    return (float)($stats[$metric] ?? 0);
}

/**
 * Checks quests, then badges. Returns what was newly earned so the UI can celebrate.
 */
function check_awards(int $uid): array
{
    $out = ['quests' => [], 'badges' => [], 'level_up' => null, 'quest_steps' => []];
    $before = (int)val('SELECT level FROM users WHERE id = ?', [$uid]);

    $stats = user_stats($uid);
    $doneQ = array_column(rows('SELECT code FROM user_quests WHERE user_id = ?', [$uid]), 'code');
    foreach (quest_defs() as $code => [$name, $desc, $metric, $target, $reward]) {
        if (!in_array($code, $doneQ, true) && metric_value($stats, $metric) >= $target) {
            q('INSERT IGNORE INTO user_quests (user_id, code, reward, completed_at) VALUES (?,?,?,?)', [$uid, $code, $reward, now()]);
            q("INSERT INTO notifications (user_id, type, text, created_at) VALUES (?,?,?,?)",
              [$uid, 'quest', "Quest complete: $name (+$reward pts)", now()]);
            $out['quests'][] = ['name' => $name, 'reward' => $reward];
        }
    }

    $ql = ql_check($uid);
    $out['quest_steps'] = array_merge($ql['steps'], weekly_check($uid), event_goal_check($uid));
    $out['quest_done'] = $ql['quests'];
    challenges_settle();

    $res = recalc_xp($uid);
    $stats['level'] = $res['level'];

    $doneB = array_column(rows('SELECT code FROM user_badges WHERE user_id = ?', [$uid]), 'code');
    foreach (badge_defs() as $code => [$name, $desc, $icon, $metric, $target]) {
        if (!in_array($code, $doneB, true) && metric_value($stats, $metric) >= $target) {
            q('INSERT IGNORE INTO user_badges (user_id, code, earned_at) VALUES (?,?,?)', [$uid, $code, now()]);
            q("INSERT INTO notifications (user_id, type, text, created_at) VALUES (?,?,?,?)",
              [$uid, 'badge', "New achievement: $name", now()]);
            $out['badges'][] = ['name' => $name, 'icon' => $icon];
        }
    }

    if ($res['level'] > $before) {
        $out['level_up'] = $res['level'];
        q("INSERT INTO notifications (user_id, type, text, created_at) VALUES (?,?,?,?)",
          [$uid, 'level', 'You reached level ' . $res['level'] . '!', now()]);
    }
    return $out;
}

function notify(int $userId, ?int $actorId, string $type, ?int $workoutId = null, ?string $text = null): void
{
    if ($actorId !== null && $actorId === $userId) {
        return;
    }
    q('INSERT INTO notifications (user_id, actor_id, type, workout_id, text, created_at) VALUES (?,?,?,?,?,?)',
      [$userId, $actorId, $type, $workoutId, $text, now()]);
}

/* ================= Saving a workout ================= */

/**
 * $input: ['performed_at' => 'Y-m-d H:i', 'title' => '', 'notes' => '', 'ex' => [ ['id'=>1,'sets'=>[ ['w'=>..,'r'=>..,'t'=>..,'d'=>..] ]] ]]
 * Weights arrive in the user's own unit (kg or lb) and are stored in kg.
 * Returns ['ok'=>bool, 'error'=>string, 'workout_id'=>int, 'points'=>int, 'awards'=>array]
 */
function save_workout(array $user, array $input): array
{
    $uid = (int)$user['id'];
    $unit = $user['unit'];
    $bw = (float)($user['bodyweight_kg'] ?: 75);

    $tz = user_tz($user);
    $nowLocal = new DateTime('now', $tz);
    try {
        $dt = new DateTime((string)($input['performed_at'] ?? ''), $tz);
    } catch (Throwable $e) {
        $dt = clone $nowLocal;
    }
    if ($dt->getTimestamp() > time() + 3600 || $dt->getTimestamp() < strtotime('-5 years')) {
        $dt = clone $nowLocal;
    }
    $when = $dt->getTimestamp();
    $whenStr = $dt->format('Y-m-d H:i:s');   // the member's own wall-clock time
    $dur = (int)($input['duration'] ?? 0);
    $dur = ($dur >= 60 && $dur <= 86400) ? $dur : null;
    $title = clean_text((string)($input['title'] ?? ''), 80);
    $notes = clean_text((string)($input['notes'] ?? ''), 1000);

    $exercises = [];
    $flagList = [];
    foreach ((array)($input['ex'] ?? []) as $exIn) {
        $exId = (int)($exIn['id'] ?? 0);
        $ex = $exId ? row('SELECT * FROM exercises WHERE id = ? AND active = 1', [$exId]) : null;
        if (!$ex) {
            continue;
        }
        $sets = [];
        foreach ((array)($exIn['sets'] ?? []) as $sIn) {
            if (isset($sIn['k']) && (string)$sIn['k'] === '0') {
                continue;   // planned but not ticked
            }
            $wu = !empty($sIn['wu']) && (string)$sIn['wu'] !== '0' && in_array($ex['kind'], ['weight', 'bodyweight'], true) ? 1 : 0;
            $w = (float)str_replace(',', '.', (string)($sIn['w'] ?? '0'));
            $r = (int)($sIn['r'] ?? 0);
            $t = (int)($sIn['t'] ?? 0);   // seconds (cardio / duration)
            $d = (int)round((float)str_replace(',', '.', (string)($sIn['d'] ?? '0')) * 1000); // km -> m
            $kg = $unit === 'lb' ? $w / LB_PER_KG : $w;
            $kg = round(max(0, min(1000, $kg)), 2);

            if (in_array($ex['kind'], ['weight', 'bodyweight'], true)) {
                if ($r < 1) {
                    continue;
                }
                $loadChk = $kg * (((int)($ex['per_side'] ?? 0) === 1 || ($ex['equipment'] ?? '') === 'dumbbell') ? 2.0 : 1.0);
                [$rej, $fl] = staff_check_set($ex, $loadChk, $r);
                if ($rej !== null) {
                    return ['ok' => false, 'error' => $rej];
                }
                if ($fl !== null) {
                    $flagList[] = [(string)$ex['name'], $fl];
                }
                $sets[] = ['weight_kg' => $kg ?: null, 'reps' => min($r, 500), 'duration_sec' => null, 'distance_m' => null, 'wu' => $wu];
            } else {
                if ($t < 1) {
                    continue;
                }
                $sets[] = [
                    'weight_kg' => null, 'reps' => null,
                    'duration_sec' => min($t, 86400),
                    'distance_m' => $ex['kind'] === 'cardio' ? min($d, 500000) : null,
                    'wu' => 0,
                ];
            }
        }
        if ($sets) {
            $exercises[] = ['ex' => $ex, 'sets' => array_slice($sets, 0, 40), 'note' => clean_text((string)($exIn['note'] ?? ''), 500)];
        }
        if (count($exercises) >= 30) {
            break;
        }
    }
    if (!$exercises) {
        return ['ok' => false, 'error' => 'Tick at least one set before finishing.'];
    }

    staff_ensure();   // DDL must run before the transaction
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $gymId = (int)($input['gym_id'] ?? 0);
        if ($gymId) {
            $gr = row('SELECT id, owner_id, is_public FROM gyms WHERE id = ?', [$gymId]);
            if (!$gr || ((int)$gr['owner_id'] !== $uid && !(int)$gr['is_public'])) {
                $gymId = 0;
            }
        }
        $editId = (int)($input['edit_id'] ?? 0);
        $isEdit = $editId > 0 && row('SELECT id FROM workouts WHERE id = ? AND user_id = ?', [$editId, $uid]);
        if ($isEdit) {
            $wid = $editId;
            q('UPDATE workouts SET performed_at = ?, title = ?, notes = ?, unit = ?, gym_id = ? WHERE id = ?',
              [$whenStr, $title ?: null, $notes ?: null, ($user['unit'] ?? 'kg') === 'lb' ? 'lb' : 'kg', $gymId ?: null, $wid]);
            q('DELETE FROM workout_sets WHERE workout_id = ?', [$wid]);
            q('DELETE FROM workout_ex_notes WHERE workout_id = ?', [$wid]);
        } else {
            q('INSERT INTO workouts (user_id, performed_at, title, notes, unit, created_at, gym_id, duration_sec) VALUES (?,?,?,?,?,?,?,?)',
              [$uid, $whenStr, $title ?: null, $notes ?: null, ($user['unit'] ?? 'kg') === 'lb' ? 'lb' : 'kg', now(), $gymId ?: null, $dur]);
            $wid = (int)$pdo->lastInsertId();
        }
        foreach ($exercises as $exN) {
            if ($exN['note'] !== '') {
                q('INSERT INTO workout_ex_notes (workout_id, exercise_id, note) VALUES (?,?,?)', [$wid, $exN['ex']['id'], $exN['note']]);
            }
        }
        if (!$isEdit) {
            referral_reward($uid);
        }
        if ($gymId) {
            q('UPDATE users SET default_gym_id = ? WHERE id = ?', [$gymId, $uid]);
        }

        foreach ($flagList as [$fx, $fd]) {
            staff_flag($uid, $wid, $fx, $fd);
        }
        $total = 0;
        $volume = 0.0;
        $count = 0;
        foreach ($exercises as $item) {
            $ex = $item['ex'];
            $prior = val('SELECT MAX(score) FROM workout_sets WHERE user_id = ? AND exercise_id = ? AND workout_id <> ?', [$uid, $ex['id'], $wid]);
            $hasPrior = $prior !== null && (float)$prior > 0;

            $calc = [];
            $bestIdx = null;
            foreach ($item['sets'] as $i => $s) {
                $c = set_points($ex, $s, $bw);
                if (!empty($s['wu'])) {
                    $c = ['points' => (int)round($c['points'] * WARMUP_FACTOR), 'score' => 0.0];   // warm-ups earn half points, never PRs
                }
                $calc[$i] = $c;
                if (empty($s['wu']) && $hasPrior && $c['score'] > 60 && $c['score'] > 2 * (float)$prior && in_array($ex['kind'], ['weight', 'bodyweight'], true)) {
                    staff_flag($uid, $wid, (string)$ex['name'], 'estimated 1RM ' . round($c['score']) . ' kg is more than double their previous best (' . round((float)$prior) . ' kg)');
                }
                if (empty($s['wu']) && in_array($ex['kind'], ['weight', 'bodyweight'], true)
                    && ($bestIdx === null || $c['score'] > $calc[$bestIdx]['score'])) {
                    $bestIdx = $i;
                }
            }
            foreach ($item['sets'] as $i => $s) {
                $c = $calc[$i];
                $isPr = ($bestIdx === $i && $hasPrior && $c['score'] > (float)$prior) ? 1 : 0;
                $pts = $c['points'] + ($isPr ? PR_BONUS : 0);
                q('INSERT INTO workout_sets (workout_id, user_id, exercise_id, weight_kg, reps, duration_sec, distance_m, score, points, is_pr, is_warmup)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                  [$wid, $uid, $ex['id'], $s['weight_kg'], $s['reps'], $s['duration_sec'], $s['distance_m'], $c['score'], $pts, $isPr, (int)$s['wu']]);
                if (!empty($s['wu'])) {
                    $total += $pts;
                    continue;
                }
                $total += $pts;
                $count++;
                if ($s['reps'] && $s['weight_kg']) {
                    $volume += $s['weight_kg'] * $s['reps'];
                } elseif ($s['reps'] && $ex['kind'] === 'bodyweight') {
                    $volume += ($bw * (float)$ex['bw_pct']) * $s['reps'];
                }
            }
        }
        q('UPDATE workouts SET points = ?, volume_kg = ?, set_count = ? WHERE id = ?', [$total, round($volume, 1), $count, $wid]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // A workout logged for an earlier date can change which later sets count as PRs: re-derive everything.
    if (!empty($isEdit) || (int)val('SELECT COUNT(*) FROM workouts WHERE user_id = ? AND (performed_at > ? OR (performed_at = ? AND id > ?))', [$uid, date('Y-m-d H:i:s', $when), date('Y-m-d H:i:s', $when), $wid]) > 0) {
        recalc_user($uid);
    }
    recalc_xp($uid);
    $awards = check_awards($uid);
    if (!$isEdit) {
        foreach (event_boost_lines($when, (int)$total) as $l) {
            $awards['quest_steps'][] = $l;
        }
    }
    if (!$isEdit) {
        $fresh = row('SELECT * FROM users WHERE id = ?', [$uid]) ?: $user;
        $gameLine = game_on_workout($fresh, (int)$wid, (int)$total);
        if ($gameLine !== '' && me() && (int)me()['id'] === $uid) {
            flash($gameLine);
        }
    }
    webhook_fire($uid, (int)$wid, (int)$total, $awards);
    return ['ok' => true, 'workout_id' => $wid, 'points' => $total, 'awards' => $awards];
}

function delete_workout(int $workoutId, int $uid): void
{
    game_on_delete($workoutId);
    q('DELETE FROM workouts WHERE id = ? AND user_id = ?', [$workoutId, $uid]);
    recalc_user($uid);   // PR flags, badges and quests follow the deletion
}


/**
 * Last session per exercise for this user: [exercise_id => [sets...]] with values in the user's unit.
 * Used to pre-fill the logger ("same as last time").
 */
function last_sessions(int $uid, string $unit, int $limit = 250): array
{
    $latest = rows('SELECT exercise_id, MAX(workout_id) w FROM workout_sets WHERE user_id = ? GROUP BY exercise_id ORDER BY w DESC LIMIT ' . (int)$limit, [$uid]);
    if (!$latest) {
        return [];
    }
    $wids = array_values(array_unique(array_map(fn($r) => (int)$r['w'], $latest)));
    $want = [];
    foreach ($latest as $r) {
        $want[(int)$r['exercise_id']] = (int)$r['w'];
    }
    $sets = rows('SELECT * FROM workout_sets WHERE user_id = ? AND workout_id IN (' . in_clause($wids) . ') ORDER BY id', array_merge([$uid], $wids));
    $out = [];
    foreach ($sets as $s) {
        $ex = (int)$s['exercise_id'];
        if (($want[$ex] ?? 0) !== (int)$s['workout_id']) {
            continue;
        }
        $out[$ex][] = [
            'w' => $s['weight_kg'] !== null ? round($unit === 'lb' ? (float)$s['weight_kg'] * LB_PER_KG : (float)$s['weight_kg'], 1) : '',
            'r' => $s['reps'] ?? '',
            't' => $s['duration_sec'] ?? '',
            'd' => $s['distance_m'] ? round($s['distance_m'] / 1000, 2) : '',
            'wu' => (int)$s['is_warmup'],
        ];
    }
    return $out;
}
