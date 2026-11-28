<?php
declare(strict_types=1);

// A member's hero as a round-friendly head picture. Cached by the browser for a few minutes.
$id = (int)$args[0];
prof_look_ensure();
$ok = row("SELECT id FROM users WHERE id = ? AND is_banned = 0 AND avatar_mode = 'hero'", [$id]);
if (!$ok) {
    http_response_code(404);
    exit;
}
$svg = fito_dressed(fito_look($id), 120);
$svg = preg_replace('/ width="\d+" height="\d+" viewBox="[^"]*"/', ' width="120" height="120" viewBox="40 14 160 160"', $svg, 1);
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo preg_replace('/<svg/', '<svg xmlns="http://www.w3.org/2000/svg"', $svg, 1);
