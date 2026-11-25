<?php
declare(strict_types=1);

/* Starter programs: ready-made routines plus a suggested weekly schedule. Exercise names must match the library. */

function starter_programs(): array
{
    // code => [name, level, days/week, description, routines [name => [[exercise, sets, reps]]], schedule [dow => routine name]]
    return [
        'full_body_3' => ['Full Body 3×', 'beginner', 3, 'Three simple full-body sessions a week, with a rest day between each. The classic first programme: learn the big movements and add weight slowly.', [
            'Full Body A' => [['Back Squat', 3, '8'], ['Barbell Bench Press', 3, '8'], ['Barbell Row', 3, '8'], ['Plank', 3, '30s']],
            'Full Body B' => [['Deadlift', 3, '5'], ['Overhead Press', 3, '8'], ['Lat Pulldown', 3, '10'], ['Glute Bridge', 3, '12']],
        ], [1 => 'Full Body A', 3 => 'Full Body B', 5 => 'Full Body A']],
        'machines_start' => ['Gym Starter (machines)', 'beginner', 3, 'New to the gym? Machines and cables guide the movement so you can focus on effort and feel safe.', [
            'Machine Day A' => [['Leg Press', 3, '10'], ['Machine Chest Press', 3, '10'], ['Lat Pulldown', 3, '10'], ['Machine Shoulder Press', 2, '12'], ['Plank', 2, '30s']],
            'Machine Day B' => [['Hack Squat', 3, '10'], ['Seated Cable Row', 3, '10'], ['Pec Deck', 2, '12'], ['Seated Leg Curl', 3, '12'], ['Rope Triceps Pushdown', 2, '12'], ['Cable Curl', 2, '12']],
        ], [1 => 'Machine Day A', 3 => 'Machine Day B', 5 => 'Machine Day A']],
        'home_bodyweight' => ['Home Bodyweight', 'beginner', 3, 'No equipment needed. Push, pull-ish, legs and core using only your bodyweight.', [
            'Home Workout A' => [['Push-Up', 3, '8-12'], ['Bodyweight Squat', 3, '15'], ['Glute Bridge', 3, '15'], ['Plank', 3, '30s']],
            'Home Workout B' => [['Incline Push-Up', 3, '10'], ['Reverse Lunge', 3, '10'], ['Superman', 3, '12'], ['Bird Dog', 3, '10']],
        ], [1 => 'Home Workout A', 3 => 'Home Workout B', 5 => 'Home Workout A']],
        'ul_4' => ['Upper / Lower 4×', 'intermediate', 4, 'Two upper and two lower days. Each muscle gets trained twice a week, a great step up from full body.', [
            'Upper' => [['Barbell Bench Press', 4, '6-8'], ['Barbell Row', 4, '6-8'], ['Overhead Press', 3, '8-10'], ['Lat Pulldown', 3, '10'], ['Dumbbell Curl', 2, '12'], ['Rope Triceps Pushdown', 2, '12']],
            'Lower' => [['Back Squat', 4, '6-8'], ['Romanian Deadlift', 3, '8'], ['Leg Press', 3, '10'], ['Seated Leg Curl', 3, '12'], ['Standing Calf Raise', 3, '12']],
        ], [1 => 'Upper', 2 => 'Lower', 4 => 'Upper', 5 => 'Lower']],
        'ppl_6' => ['Push / Pull / Legs', 'intermediate', 6, 'Push, pull and leg days, repeated twice a week. High volume for people who like to train a lot.', [
            'Push' => [['Barbell Bench Press', 4, '6-8'], ['Seated Dumbbell Shoulder Press', 3, '8-10'], ['Incline Dumbbell Press', 3, '10'], ['Dumbbell Lateral Raise', 3, '12-15'], ['Rope Triceps Pushdown', 3, '12']],
            'Pull' => [['Deadlift', 3, '5'], ['Pull-Up', 3, '6-10'], ['Barbell Row', 3, '8'], ['Face Pull', 3, '15'], ['Dumbbell Curl', 3, '12']],
            'Legs' => [['Back Squat', 4, '6-8'], ['Romanian Deadlift', 3, '8-10'], ['Leg Press', 3, '10-12'], ['Lying Leg Curl', 3, '12'], ['Standing Calf Raise', 4, '12-15']],
        ], [1 => 'Push', 2 => 'Pull', 3 => 'Legs', 4 => 'Push', 5 => 'Pull', 6 => 'Legs']],
        'strength_5x5' => ['Simple Strength 5×5', 'intermediate', 3, 'Heavy sets of five on the big lifts, alternating two workouts. Add a little weight every session.', [
            'Strength A' => [['Back Squat', 5, '5'], ['Barbell Bench Press', 5, '5'], ['Barbell Row', 5, '5']],
            'Strength B' => [['Back Squat', 5, '5'], ['Overhead Press', 5, '5'], ['Deadlift', 1, '5']],
        ], [1 => 'Strength A', 3 => 'Strength B', 5 => 'Strength A']],
        'power_advanced' => ['Heavy Four-Day (advanced)', 'advanced', 4, 'Two heavy lower and two heavy upper days built around low reps and long rests. For lifters who already know their numbers.', [
            'Heavy Squat' => [['Back Squat', 5, '3-5'], ['Front Squat', 3, '5'], ['Romanian Deadlift', 3, '6'], ['Hanging Leg Raise', 3, '10']],
            'Heavy Bench' => [['Barbell Bench Press', 5, '3-5'], ['Barbell Row', 4, '5'], ['Overhead Press', 3, '5'], ['Pull-Up', 3, 'max']],
            'Heavy Deadlift' => [['Deadlift', 5, '3'], ['Deficit Deadlift', 2, '5'], ['Bulgarian Split Squat', 3, '8'], ['Ab Wheel Rollout', 3, '10']],
            'Volume Upper' => [['Incline Barbell Bench Press', 4, '6-8'], ['Pendlay Row', 4, '6-8'], ['Chest Dip', 3, '8-10'], ['Chin-Up', 3, '8']],
        ], [1 => 'Heavy Squat', 2 => 'Heavy Bench', 4 => 'Heavy Deadlift', 6 => 'Volume Upper']],
    ];
}

/** Adds a programme's routines for a member. Returns [routines created, exercises skipped, schedule days set]. */
function program_install(int $uid, string $code, bool $withSchedule): array
{
    $progs = starter_programs();
    if (!isset($progs[$code])) {
        return [0, 0, 0];
    }
    [$name, , , , $routines, $sched] = $progs[$code];
    $ids = [];
    $skipped = 0;
    foreach ($routines as $rname => $items) {
        $payload = [];
        foreach ($items as [$exName, $sets, $reps]) {
            $ex = row('SELECT id FROM exercises WHERE name = ? AND active = 1', [$exName]);
            if (!$ex) {
                $skipped++;
                continue;
            }
            $payload[] = ['id' => (int)$ex['id'], 'n' => $sets, 'reps' => $reps];
        }
        $existing = (int)val('SELECT COUNT(*) FROM routines WHERE user_id = ?', [$uid]);
        if ($existing >= 50 || !$payload) {
            continue;
        }
        q('INSERT INTO routines (user_id, name, payload, created_at) VALUES (?,?,?,?)', [$uid, mb_substr($rname, 0, 60), json_encode($payload), now()]);
        $ids[$rname] = (int)val('SELECT LAST_INSERT_ID()');
    }
    $days = 0;
    if ($withSchedule) {
        q('DELETE FROM routine_schedule WHERE user_id = ?', [$uid]);
        foreach ($sched as $dow => $rname) {
            if (isset($ids[$rname])) {
                q('INSERT INTO routine_schedule (user_id, dow, routine_id) VALUES (?,?,?)', [$uid, $dow, $ids[$rname]]);
                $days++;
            }
        }
    }
    return [count($ids), $skipped, $days];
}
