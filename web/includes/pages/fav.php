<?php
declare(strict_types=1);

/* Toggle a favourite exercise. JSON in, JSON out. */

header('Cache-Control: no-store');
$u = me();
if (!$u) {
    json_out(['ok' => false, 'error' => 'Not logged in'], 401);
}
if (!is_post()) {
    json_out(['ok' => false, 'error' => 'POST only'], 405);
}
$j = json_decode((string)file_get_contents('php://input', false, null, 0, 2000), true);
if (!is_array($j)) {
    json_out(['ok' => false, 'error' => 'Bad request'], 400);
}
$tok = (string)($j['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!hash_equals(csrf_token(), $tok)) {
    json_out(['ok' => false, 'error' => 'Session expired, reload the page.'], 403);
}
$uid = (int)$u['id'];
$eid = (int)($j['id'] ?? 0);
if (!row('SELECT id FROM exercises WHERE id = ? AND active = 1', [$eid])) {
    json_out(['ok' => false, 'error' => 'Unknown exercise'], 404);
}
if (!empty($j['on'])) {
    q('INSERT IGNORE INTO favorite_exercises (user_id, exercise_id, created_at) VALUES (?,?,?)', [$uid, $eid, now()]);
} else {
    q('DELETE FROM favorite_exercises WHERE user_id = ? AND exercise_id = ?', [$uid, $eid]);
}
json_out(['ok' => true]);
