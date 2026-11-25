<?php
declare(strict_types=1);

/*
 * Real Fitocracy examples collected so far, used by the Fitocracy archive page (Calibration tab).
 * Each row: [group, exercise (Fitocracy name), our exercise name, weight lb (0 if n/a), reps, minutes, km, real points, confidence, source]
 * group: compound | light | isolation | bodyweight | cardio.  confidence: high (read from a screenshot), medium (quoted in a post), low (from memory).
 */
function fito_examples(): array
{
    return [
        // Screenshots of old Fitocracy workout logs (points as shown on the page)
        ['compound',  'Barbell Deadlift',            'Deadlift',               285, 5, 0, 0, 181, 'high', 'screenshot, 2014 log'],
        ['compound',  'Barbell Squat',               'Back Squat',             215, 5, 0, 0, 113, 'high', 'screenshot, 2014 log'],
        ['compound',  'Standing Barbell OHP',        'Overhead Press',          90, 5, 0, 0,  67, 'high', 'screenshot, 2014 log'],
        ['compound',  'Barbell Bench Press',         'Barbell Bench Press',    155, 5, 0, 0,  77, 'high', 'screenshot, 2014 log'],
        ['compound',  'Barbell Deadlift',            'Deadlift',               205, 7, 0, 0, 102, 'high', 'screenshot, year unknown'],
        ['compound',  'Barbell Deadlift',            'Deadlift',               215, 6, 0, 0, 105, 'high', 'screenshot, year unknown'],
        ['light',     'Barbell Squat',               'Back Squat',              45, 5, 0, 0,  36, 'high', 'screenshot, empty bar'],
        ['compound',  'Barbell Bench Press',         'Barbell Bench Press',    135, 5, 0, 0,  47, 'high', 'screenshot, 2010-11 log'],
        ['compound',  'Barbell Bench Press',         'Barbell Bench Press',    155, 3, 0, 0,  51, 'high', 'screenshot, 2010-11 log'],
        ['compound',  'Barbell Bench Press',         'Barbell Bench Press',    185, 7, 0, 0,  68, 'high', 'screenshot, 2010-11 log'],
        ['compound',  'Barbell Bench Press',         'Barbell Bench Press',    185, 4, 0, 0,  62, 'high', 'screenshot, 2010-11 log'],
        ['isolation', 'Side Lateral Raise (dumbbell)', 'Dumbbell Lateral Raise', 35, 10, 0, 0,  5, 'high', 'screenshot, 2010-11 log'],
        ['isolation', 'Side Lateral Raise (dumbbell)', 'Dumbbell Lateral Raise', 35,  5, 0, 0,  5, 'high', 'screenshot, 2010-11 log'],
        // Quoted in forum and blog posts
        ['compound',  'Barbell Bench Press',         'Barbell Bench Press',    100, 1, 0, 0,  31, 'medium', 'Octopus Overlords forum'],
        ['compound',  'Barbell Bench Press',         'Barbell Bench Press',    100, 2, 0, 0,  32, 'medium', 'Octopus Overlords forum (5 sets = 160)'],
        ['compound',  'Barbell Bench Press',         'Barbell Bench Press',    100, 10, 0, 0, 40, 'medium', 'Octopus Overlords forum'],
        ['compound',  'Machine Chest Press',         'Machine Chest Press',    100, 1, 0, 0,  25, 'medium', 'Octopus Overlords forum'],
        ['compound',  'Barbell Bench Press',         'Barbell Bench Press',    160, 6, 0, 0,  85, 'medium', 'shrinkinguy blog'],
        ['compound',  'Barbell Squat',               'Back Squat',             135, 5, 0, 0,  62, 'low',    'remembered / site example'],
        ['cardio',    'Running, 1 mile at 10:00',    'Running',                  0, 0, 10, 1.609, 82,  'medium', 'fellrnr table'],
        ['cardio',    'Running, 1 mile at 7:00',     'Running',                  0, 0, 7, 1.609, 130, 'medium', 'fellrnr table'],
        ['cardio',    'Running, 8 miles at 10:00',   'Running',                  0, 0, 80, 12.875, 999, 'medium', 'fellrnr table'],
        ['cardio',    'Running, 32 miles at 7:00',   'Running',                  0, 0, 224, 51.499, 8383, 'medium', 'fellrnr table'],
        ['cardio',    'Treadmill walk, 2 miles, 6% incline', 'Treadmill Walk', 0, 0, 30, 3.219, 101, 'medium', 'Octopus Overlords forum'],
    ];
}

/** What we still lack, shown as a wish list. */
function fito_wanted(): array
{
    return [
        'Light and medium barbell sets (empty bar up to about 100 lb), and overhead press at several weights',
        'Dumbbell, cable and machine exercises with weight, reps and the points shown',
        'Bodyweight sets (push-ups, pull-ups, dips) together with the person\'s body weight',
        'Cardio with distance, time and points (cycling, rowing, swimming, walking)',
        'Level thresholds: total points needed for each level, from any source',
        'Any screenshot of a Fitocracy workout page where the points per set are visible',
    ];
}

/**
 * Real Fitocracy level data. Each row: [level, total points, kind, confidence, source]
 * kind: 'threshold' = total points needed to REACH that level; 'inside' = a member had this many points while at that level
 * (so the threshold of that level is at or below it).
 */
function fito_levels(): array
{
    return [
        [38, 439309, 'inside',    'high', 'a real profile screenshot: level 38, 439,309 total points'],
        [39, 452350, 'threshold', 'high', 'same screenshot: "13,041 pts to Level 39" (439,309 + 13,041)'],
    ];
}

function fito_contrib_ensure(): void
{
    q("CREATE TABLE IF NOT EXISTS fito_contrib (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(8) NOT NULL,
        a VARCHAR(80) NOT NULL DEFAULT '',
        n1 DECIMAL(12,2) NOT NULL DEFAULT 0,
        n2 DECIMAL(12,2) NOT NULL DEFAULT 0,
        n3 DECIMAL(12,2) NOT NULL DEFAULT 0,
        unit VARCHAR(3) NOT NULL DEFAULT '',
        note VARCHAR(160) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
