<?php
declare(strict_types=1);

/* How-to guide for one exercise (JSON), used by the logger's "How to" button. */

header('Cache-Control: private, max-age=3600');
if (!me()) {
    json_out(['ok' => false, 'error' => 'Not logged in'], 401);
}
$ex = row('SELECT id, name, slug, subs, per_side FROM exercises WHERE id = ? AND active = 1', [(int)$args[0]]);
if (!$ex) {
    json_out(['ok' => false, 'error' => 'Unknown exercise'], 404);
}
$regions = muscle_regions();
json_out([
    'ok' => true,
    'name' => $ex['name'],
    'steps' => exercise_guide($ex['name']) ?? [],
    'muscles' => array_values(array_filter(array_map(fn($m) => $regions[$m] ?? null, $ex['subs'] === '' ? [] : explode(',', $ex['subs'])))),
    'video' => exercise_video_url($ex['name']),
    'page' => url('exercises/' . $ex['slug']),
    'side' => (int)$ex['per_side'],
]);
