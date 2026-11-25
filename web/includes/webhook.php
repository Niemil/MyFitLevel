<?php
declare(strict_types=1);

/* Outgoing webhook: after a workout is saved, post a short message to the member's own URL (Discord, Slack, Zapier, Make, IFTTT, Home Assistant...). */

/** Returns the resolved public IP when the URL is allowed (https, port 443, public address), otherwise null. */
function webhook_resolve(string $url): ?array
{
    $p = parse_url($url);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || (isset($p['port']) && (int)$p['port'] !== 443)) {
        return null;
    }
    $host = (string)$p['host'];
    $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
    if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
        return null;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return null;
    }
    return [$host, $ip];
}

function webhook_post(string $url, array $payload, string $key = ''): bool
{
    $r = webhook_resolve($url);
    if (!$r || !function_exists('curl_init')) {
        return false;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => array_filter(['Content-Type: application/json', 'User-Agent: MyFitLevel-webhook', $key !== '' ? 'Authorization: Bearer ' . $key : '']),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$r[0] . ':443:' . $r[1]],
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

function webhook_fire(int $uid, int $wid, int $points, array $awards): void
{
    try {
        $url = (string)setting('webhook:' . $uid, '');
        if ($url === '') {
            return;
        }
        $w = row('SELECT title, performed_at, set_count, volume_kg FROM workouts WHERE id = ? AND user_id = ?', [$wid, $uid]);
        $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$w || !$u || strtotime((string)$w['performed_at']) < time() - 36 * 3600) {
            return;   // old workouts added afterwards do not notify
        }
        $msg = '💪 ' . display_name($u) . ' finished ' . (trim((string)$w['title']) !== '' ? '“' . $w['title'] . '”' : 'a workout') . ': ' . (int)$w['set_count'] . ' sets, ' . $points . ' points.';
        if (!empty($awards['level_up'])) {
            $msg .= ' 🎉 Level up!';
        }
        webhook_post($url, ['content' => $msg, 'text' => $msg, 'username' => 'MyFitLevel', 'source' => (string)parse_url(site_url(''), PHP_URL_HOST), 'url' => site_url('u/' . $u['username']), 'event' => 'workout.saved',
            'workout' => ['id' => $wid, 'title' => $w['title'], 'sets' => (int)$w['set_count'], 'points' => $points, 'volume_kg' => (float)$w['volume_kg'], 'performed_at' => $w['performed_at']],
            'user' => ['username' => $u['username'], 'level' => (int)$u['level']]], (string)setting('webhook_key:' . $uid, ''));
    } catch (Throwable $e) {
        // never let a broken webhook break saving a workout
    }
}
