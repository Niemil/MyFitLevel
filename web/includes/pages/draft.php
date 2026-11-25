<?php
declare(strict_types=1);

/* Autosave endpoint for the workout logger. JSON in, JSON out. */

header('Cache-Control: no-store');
$u = me();
if (!$u) {
    json_out(['ok' => false, 'error' => 'Not logged in'], 401);
}
if (!is_post()) {
    json_out(['ok' => false, 'error' => 'POST only'], 405);
}
$raw = (string)file_get_contents('php://input', false, null, 0, 120000);
$j = json_decode($raw, true);
if (!is_array($j)) {
    json_out(['ok' => false, 'error' => 'Bad request'], 400);
}
$tok = (string)($j['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!hash_equals(csrf_token(), $tok)) {
    json_out(['ok' => false, 'error' => 'Session expired, reload the page.'], 403);
}
$uid = (int)$u['id'];

if (!empty($j['clear'])) {
    q('DELETE FROM workout_drafts WHERE user_id = ?', [$uid]);
    json_out(['ok' => true]);
}

$d = $j['payload'] ?? null;
if (!is_array($d)) {
    json_out(['ok' => false, 'error' => 'No payload'], 400);
}
$str = fn($v, int $max) => mb_substr(is_scalar($v) ? (string)$v : '', 0, $max);
$clean = [
    'v' => 1,
    'ts' => (int)($d['ts'] ?? 0),
    'title' => $str($d['title'] ?? '', 80),
    'notes' => $str($d['notes'] ?? '', 1000),
    'when' => $str($d['when'] ?? '', 20),
    'gym' => (string)(int)($d['gym'] ?? 0),
    'began' => max(0, (int)($d['began'] ?? 0)),
    'started' => !empty($d['started']) ? 1 : 0,
    'items' => [],
];
foreach (array_slice((array)($d['items'] ?? []), 0, 30) as $it) {
    $sets = [];
    foreach (array_slice((array)($it['sets'] ?? []), 0, 40) as $s) {
        $sets[] = [
            'w' => $str($s['w'] ?? '', 12), 'r' => $str($s['r'] ?? '', 6),
            't' => $str($s['t'] ?? '', 8), 'd' => $str($s['d'] ?? '', 8),
            'wu' => !empty($s['wu']) ? 1 : 0, 'k' => !empty($s['k']) ? 1 : 0,
        ];
    }
    if ((int)($it['id'] ?? 0) > 0 && $sets) {
        $clean['items'][] = ['id' => (int)$it['id'], 'sets' => $sets, 'note' => $str($it['note'] ?? '', 500)];
    }
}
if (!$clean['items'] && $clean['title'] === '' && $clean['notes'] === '' && !$clean['started']) {
    q('DELETE FROM workout_drafts WHERE user_id = ?', [$uid]);
    json_out(['ok' => true, 'empty' => true]);
}
q('REPLACE INTO workout_drafts (user_id, payload, updated_at) VALUES (?,?,?)',
  [$uid, json_encode($clean, JSON_UNESCAPED_UNICODE), now()]);
json_out(['ok' => true, 'saved_at' => date('H:i:s')]);
