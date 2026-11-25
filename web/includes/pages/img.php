<?php
declare(strict_types=1);

/* Serves a stored photo after a visibility check. */
$tok = (string)$args[0];
$thumb = !empty($args[1]);
$im = row('SELECT i.*, us.visibility, us.is_banned FROM images i JOIN users us ON us.id = i.user_id WHERE i.token = ?', [$tok]);
$ok = false;
if ($im && !$im['is_banned']) {
    if ($im['kind'] === 'avatar') {
        $ok = true;   // profile photos are public, like names
    } else {
        $ok = can_see_member(['id' => $im['user_id'], 'visibility' => $im['visibility']], me());
    }
}
$file = $ok ? img_path($tok, $thumb) : '';
if (!$ok || !is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: ' . ($im['visibility'] === 'public' || $im['kind'] === 'avatar' ? 'public' : 'private') . ', max-age=604800');
readfile($file);
exit;
