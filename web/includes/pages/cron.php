<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
$tok = (string)setting('cron_token', '');
if ($tok === '' || !hash_equals($tok, (string)$args[0])) {
    http_response_code(404);
    exit('not found');
}
echo 'miss-you mails sent: ' . miss_you_run(25) . "\n";
require_once APP_ROOT . '/includes/mentions.php';
echo (function () { try { return mentions_cron(); } catch (Throwable $e) { return 'mentions: failed'; } })() . "\n";
