<?php
declare(strict_types=1);

// Anonymous beacon: upgrades a page view (JS ran / human interaction / time on page) and records banner choices.
header('Cache-Control: no-store');
if (!is_post()) {
    http_response_code(405);
    exit;
}
try {
    if (!rate_ok('sb' . client_ip(), 90, 60)) {
        http_response_code(429);
        exit;
    }
    rate_hit('sb' . client_ip());
    stats_ensure();
    $ev = (string)($_POST['e'] ?? '');
    if (in_array($ev, ['shown', 'accept', 'reject'], true)) {
        $cs = stats_consent_state();
        if ($cs === 3) {
            http_response_code(204);
            exit;
        }
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
        if (stats_bot_name($ua) === '') {
            q('INSERT INTO stat_consent (ts, day, event, vid) VALUES (?,?,?,?)', [
                now(), date('Y-m-d'), $ev, stats_vid($ev === 'accept' ? 1 : 0, $ua),
            ]);
        }
        http_response_code(204);
        exit;
    }
    $id = (int)($_POST['i'] ?? 0);
    $tok = (string)($_POST['t'] ?? '');
    if ($id > 0 && hash_equals(stats_token($id), $tok)) {
        if ($ev === 'load') {
            q('UPDATE stat_visits SET js = 1 WHERE id = ?', [$id]);
        } elseif ($ev === 'human') {
            q('UPDATE stat_visits SET js = 1, human = 1 WHERE id = ?', [$id]);
        } elseif ($ev === 'dwell') {
            $d = max(0, min(3600, (int)($_POST['d'] ?? 0)));
            q('UPDATE stat_visits SET dwell = GREATEST(dwell, ?) WHERE id = ?', [$d, $id]);
        }
    }
} catch (Throwable $e) {
}
http_response_code(204);
