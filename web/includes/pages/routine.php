<?php
declare(strict_types=1);

/* Save / delete routines (reusable workouts). JSON in, JSON out. */

header('Cache-Control: no-store');
$u = me();
if (!$u) {
    json_out(['ok' => false, 'error' => 'Not logged in'], 401);
}
if (!is_post()) {
    json_out(['ok' => false, 'error' => 'POST only'], 405);
}
$j = json_decode((string)file_get_contents('php://input', false, null, 0, 20000), true);
if (!is_array($j)) {
    json_out(['ok' => false, 'error' => 'Bad request'], 400);
}
if (!hash_equals(csrf_token(), (string)($j['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')))) {
    json_out(['ok' => false, 'error' => 'Session expired, reload the page.'], 403);
}
$uid = (int)$u['id'];
$act = (string)($j['action'] ?? 'save');

if ($act === 'delete') {
    q('DELETE FROM routines WHERE id = ? AND user_id = ?', [(int)($j['id'] ?? 0), $uid]);
    json_out(['ok' => true]);
}

$name = clean_text((string)($j['name'] ?? ''), 60);
$items = [];
foreach ((array)($j['items'] ?? []) as $it) {
    $eid = (int)($it['id'] ?? 0);
    $n = max(1, min(20, (int)($it['n'] ?? 1)));
    if ($eid && row('SELECT id FROM exercises WHERE id = ? AND active = 1', [$eid])) {
        $items[] = ['id' => $eid, 'n' => $n];
    }
}
if ($name === '' || !$items || count($items) > 30) {
    json_out(['ok' => false, 'error' => 'Give the routine a name and add at least one exercise.'], 422);
}
if ((int)val('SELECT COUNT(*) FROM routines WHERE user_id = ?', [$uid]) >= 50) {
    json_out(['ok' => false, 'error' => 'You have 50 routines already. Delete one first.'], 422);
}
q('INSERT INTO routines (user_id, name, payload, created_at) VALUES (?,?,?,?)', [$uid, $name, json_encode($items), now()]);
json_out(['ok' => true, 'routine' => ['id' => (int)val('SELECT LAST_INSERT_ID()'), 'name' => $name, 'items' => $items]]);
