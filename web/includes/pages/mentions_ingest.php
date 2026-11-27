<?php
declare(strict_types=1);

// Receives found mentions from the owner's watcher script. Bearer key required.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once APP_ROOT . '/includes/mentions.php';
try {
    if (!rate_ok('mi' . client_ip(), 30, 600)) {
        http_response_code(429);
        echo '{"error":"rate_limit"}';
        exit;
    }
    rate_hit('mi' . client_ip());
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $key = preg_match('/^Bearer\s+(\S+)$/', $auth, $m) ? $m[1] : '';
    if ($key === '' || !hash_equals(mentions_key(), $key)) {
        http_response_code(401);
        echo '{"error":"bad_key"}';
        exit;
    }
    if (!is_post()) {   // the watcher asks what to search for and whether you pressed "Run now"
        echo json_encode(['queries' => mentions_queries(), 'run_now' => (int)setting('mentions_run_now', '0') > (int)setting('mentions_last_run', '0')]);
        exit;
    }
    mentions_ensure();
    $in = json_decode((string)file_get_contents('php://input'), true);
    $items = is_array($in['items'] ?? null) ? array_slice($in['items'], 0, 60) : [];
    $new = 0;
    $cut = static fn($v, int $n): string => mb_substr(trim((string)$v), 0, $n);
    foreach ($items as $it) {
        $url = $cut($it['url'] ?? '', 500);
        if (!preg_match('#^https?://#i', $url)) {
            continue;
        }
        $st = q('INSERT IGNORE INTO mentions (h, found_at, posted_at, source, place, author, title, snippet, url, score) VALUES (?,?,?,?,?,?,?,?,?,?)', [
            md5($url), now(), preg_match('/^\d{4}-\d\d-\d\d/', (string)($it['posted'] ?? '')) ? $cut($it['posted'], 19) : null,
            $cut($it['source'] ?? '', 40), $cut($it['place'] ?? '', 80), $cut($it['author'] ?? '', 60),
            $cut($it['title'] ?? '', 300), $cut($it['snippet'] ?? '', 600), $url, max(0, min(100, (int)($it['score'] ?? 0))),
        ]);
        $new += $st->rowCount();
    }
    if (!empty($in['finished'])) {
        set_setting('mentions_last_run', (string)time());
        set_setting('mentions_last_info', mb_substr(((int)($in['found'] ?? 0)) . ' relevant, ' . $new . ' new' . (!empty($in['errors']) ? ', problems: ' . implode('; ', array_map('strval', array_slice((array)$in['errors'], 0, 3))) : ''), 0, 250));
    }
    q('DELETE FROM mentions WHERE found_at < ? AND status <> ?', [date('Y-m-d H:i:s', time() - 120 * 86400), 'replied']);
    echo json_encode(['ok' => true, 'received' => count($items), 'new' => $new]);
} catch (Throwable $e) {
    http_response_code(500);
    echo '{"error":"server"}';
}
