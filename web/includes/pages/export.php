<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];

$ws = rows('SELECT * FROM workouts WHERE user_id = ? ORDER BY performed_at', [$uid]);
$sets = rows('SELECT s.*, e.name, e.slug FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.user_id = ? ORDER BY s.id', [$uid]);
$by = [];
foreach ($sets as $s) {
    $by[$s['workout_id']][] = [
        'exercise' => $s['name'], 'slug' => $s['slug'],
        'weight_kg' => $s['weight_kg'] !== null ? (float)$s['weight_kg'] : null,
        'reps' => $s['reps'] !== null ? (int)$s['reps'] : null,
        'duration_sec' => $s['duration_sec'] !== null ? (int)$s['duration_sec'] : null,
        'distance_m' => $s['distance_m'] !== null ? (int)$s['distance_m'] : null,
        'points' => (int)$s['points'], 'is_pr' => (bool)$s['is_pr'], 'is_warmup' => (bool)$s['is_warmup'],
    ];
}
$out = [
    'exported_at' => date('c'),
    'user' => ['username' => $u['username'], 'display_name' => $u['display_name'], 'email' => $u['email'], 'xp' => (int)$u['xp'], 'level' => (int)$u['level'], 'created_at' => $u['created_at']],
    'workouts' => array_map(fn($w) => [
        'id' => (int)$w['id'], 'performed_at' => $w['performed_at'], 'title' => $w['title'], 'notes' => $w['notes'],
        'points' => (int)$w['points'], 'sets' => $by[$w['id']] ?? [],
    ], $ws),
    'badges' => rows('SELECT code, earned_at FROM user_badges WHERE user_id = ?', [$uid]),
    'quests' => rows('SELECT code, reward, completed_at FROM user_quests WHERE user_id = ?', [$uid]),
];
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="my-workouts-' . date('Ymd') . '.json"');
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
