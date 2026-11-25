<?php
declare(strict_types=1);

/* Save the weight step (+/- jump) for one exercise, e.g. 6 for a cable stack. JSON in, JSON out. */

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
$step = round((float)($j['step'] ?? 0), 2);
if ($step <= 0) {
    q('DELETE FROM user_exercise_prefs WHERE user_id = ? AND exercise_id = ?', [$uid, $eid]);
    json_out(['ok' => true, 'step' => null]);
}
$max = $u['unit'] === 'lb' ? 220 : 100;
$step = min($max, max(0.25, $step));
q('REPLACE INTO user_exercise_prefs (user_id, exercise_id, step, unit) VALUES (?,?,?,?)', [$uid, $eid, $step, $u['unit']]);
json_out(['ok' => true, 'step' => $step]);
