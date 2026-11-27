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

function webhook_post(string $url, array $payload, string $key = '', int $timeout = 3): bool
{
    $r = webhook_resolve($url);
    if (!$r || !function_exists('curl_init')) {
        return false;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => array_filter(['Content-Type: application/json', 'User-Agent: MyFitLevel-webhook', $key !== '' ? 'Authorization: Bearer ' . $key : '']),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$r[0] . ':443:' . $r[1]],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $GLOBALS['webhook_last'] = ['code' => $code, 'body' => is_string($body) ? (json_decode($body, true) ?: []) : []];
    return $code >= 200 && $code < 300;
}

/** One object per working set, weight always in kg: [{"exercise","reps","kg"}] or [{"exercise","seconds","kg"}]. */
function webhook_sets(int $wid): array
{
    $out = [];
    $rs = rows('SELECT s.weight_kg, s.reps, s.duration_sec, s.distance_m, e.name FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.workout_id = ? AND s.is_warmup = 0 ORDER BY s.id', [$wid]);
    foreach ($rs as $r) {
        $o = ['exercise' => mb_substr((string)$r['name'], 0, 80)];
        if ((int)$r['reps'] > 0) {
            $o['reps'] = (int)$r['reps'];
        } elseif ((int)$r['duration_sec'] > 0) {
            $o['seconds'] = (int)$r['duration_sec'];
        } else {
            $o['reps'] = 1;
        }
        $o['kg'] = $r['weight_kg'] !== null ? round((float)$r['weight_kg'], 2) : 0;
        $out[] = $o;
        if (count($out) >= 300) {
            break;
        }
    }
    return $out;
}

/** One short line per exercise, e.g. "Bench press — 4×8 @ 80 kg" (max 40 lines, 140 chars each). */
function webhook_details(int $wid, string $unit): array
{
    $sets = rows('SELECT s.weight_kg, s.reps, s.duration_sec, s.distance_m, e.name FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.workout_id = ? AND s.is_warmup = 0 ORDER BY s.id', [$wid]);
    $by = [];
    foreach ($sets as $r) {
        $by[(string)$r['name']][] = $r;
    }
    $out = [];
    foreach ($by as $name => $list) {
        $groups = [];
        foreach ($list as $r) {
            $w = $r['weight_kg'] !== null && (float)$r['weight_kg'] > 0 ? fmt_weight((float)$r['weight_kg'], $unit) : '';
            $reps = (int)$r['reps'];
            if ($reps > 0) {
                $desc = $reps;
            } elseif ((int)$r['duration_sec'] > 0) {
                $desc = fmt_duration((int)$r['duration_sec']);
            } elseif ((int)$r['distance_m'] > 0) {
                $desc = fmt_num((int)$r['distance_m'] / 1000, 2) . ' km';
            } else {
                $desc = '1';
            }
            $key = $desc . '|' . $w;
            $last = count($groups) - 1;
            if ($last >= 0 && $groups[$last]['key'] === $key) {
                $groups[$last]['n']++;
            } else {
                $groups[] = ['key' => $key, 'n' => 1, 'desc' => (string)$desc, 'w' => $w, 'time' => $reps <= 0];
            }
        }
        $parts = [];
        foreach ($groups as $g) {
            $parts[] = $g['time'] ? ($g['n'] > 1 ? $g['n'] . '× ' : '') . $g['desc'] . ($g['w'] !== '' ? ' @ ' . $g['w'] : '') : $g['n'] . '×' . $g['desc'] . ($g['w'] !== '' ? ' @ ' . $g['w'] : '');
        }
        $line = $name . ' — ' . implode(', ', $parts);
        $out[] = mb_strlen($line) > 140 ? mb_substr($line, 0, 139) . '…' : $line;
        if (count($out) >= 40) {
            break;
        }
    }
    return $out;
}

/** snakediet.org gets the finished workout through its workouts API (it counts kcal and KTN itself). */
function webhook_is_snakediet(string $url): bool
{
    $p = parse_url($url);
    $h = strtolower((string)($p['host'] ?? ''));
    return $h === 'snakediet.org' || $h === 'www.snakediet.org';
}

function webhook_snakediet_workout(int $uid, int $wid, array $w, array $u, string $url, string $key, int $timeout = 3): string
{
    $dur = (int)($w['duration_sec'] ?? 0);
    $minutes = $dur > 0 ? (int)round($dur / 60) : (int)round(max(10, (int)$w['set_count'] * 2.5));
    $minutes = max(1, min(600, $minutes));
    $kinds = rows('SELECT DISTINCT e.kind, e.name FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.workout_id = ?', [$wid]);
    $type = 'gym';
    if ($kinds) {
        $allCardio = true;
        $names = '';
        foreach ($kinds as $k) {
            $allCardio = $allCardio && $k['kind'] === 'cardio';
            $names .= ' ' . strtolower((string)$k['name']);
        }
        if ($allCardio) {
            $type = str_contains($names, 'run') || str_contains($names, 'jog') ? 'run'
                : (str_contains($names, 'walk') || str_contains($names, 'hike') ? 'walk'
                : (str_contains($names, 'cycl') || str_contains($names, 'bike') ? 'cycle' : 'cardio'));
        }
    }
    $start = min(strtotime((string)$w['performed_at']), time() - 60);
    $payload = ['id' => 'mfl-' . $uid . '-' . $wid, 'source' => 'myfitlevel.com',
        'title' => trim((string)$w['title']) !== '' ? mb_substr((string)$w['title'], 0, 80) : 'Workout',
        'type' => $type, 'start' => date('c', $start)];
    $sets = webhook_sets($wid);
    if ($dur > 0 || !$sets) {
        $payload['minutes'] = $minutes;   // the real length when we have it
    }
    if ($sets) {
        $payload['sets'] = $sets;   // no kcal: SnakeDiet counts it from the sets
    } else {
        $details = webhook_details($wid, (string)($u['unit'] ?? 'kg'));
        if ($details) {
            $payload['details'] = array_slice($details, 0, 60);
        }
    }
    $ok = webhook_post('https://snakediet.org/api/v1/workouts', $payload, $key, $timeout);
    return $ok ? (!empty($GLOBALS['webhook_last']['body']['duplicate']) ? 'duplicate' : 'sent') : 'failed';
}

/** Sends the last N days of workouts again (same ids, SnakeDiet skips ones it already has). Returns [sent, duplicate, failed]. */
function webhook_resend_snakediet(int $uid, int $days = 7): array
{
    $url = (string)setting('webhook:' . $uid, '');
    $key = (string)setting('webhook_key:' . $uid, '');
    $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
    if (!$u || !webhook_is_snakediet($url) || $key === '') {
        return [0, 0, 0];
    }
    $ws = rows('SELECT id, title, performed_at, set_count, volume_kg, duration_sec FROM workouts WHERE user_id = ? AND performed_at >= ? ORDER BY performed_at LIMIT 25', [$uid, date('Y-m-d H:i:s', time() - $days * 86400)]);
    $res = ['sent' => 0, 'duplicate' => 0, 'failed' => 0];
    foreach ($ws as $w) {
        $res[webhook_snakediet_workout($uid, (int)$w['id'], $w, $u, $url, $key, 8)]++;
    }
    return [$res['sent'], $res['duplicate'], $res['failed']];
}

function webhook_fire(int $uid, int $wid, int $points, array $awards): void
{
    try {
        $url = (string)setting('webhook:' . $uid, '');
        if ($url === '') {
            return;
        }
        $w = row('SELECT title, performed_at, set_count, volume_kg, duration_sec FROM workouts WHERE id = ? AND user_id = ?', [$wid, $uid]);
        $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$w || !$u || strtotime((string)$w['performed_at']) < time() - 36 * 3600) {
            return;   // old workouts added afterwards do not notify
        }
        if (webhook_is_snakediet($url)) {
            webhook_snakediet_workout($uid, $wid, $w, $u, $url, (string)setting('webhook_key:' . $uid, ''));
            return;   // SnakeDiet gets the workout itself, not also a feed post
        }
        $msg = '💪 ' . display_name($u) . ' finished ' . (trim((string)$w['title']) !== '' ? '“' . $w['title'] . '”' : 'a workout') . ': ' . (int)$w['set_count'] . ' sets, ' . $points . ' points.';
        if (!empty($awards['level_up'])) {
            $msg .= ' 🎉 Level up!';
        }
        $payload = ['content' => $msg, 'text' => $msg, 'username' => 'MyFitLevel', 'source' => (string)parse_url(site_url(''), PHP_URL_HOST), 'url' => site_url('u/' . $u['username']), 'event' => 'workout.saved',
            'workout' => ['id' => $wid, 'title' => $w['title'], 'sets' => (int)$w['set_count'], 'points' => $points, 'volume_kg' => (float)$w['volume_kg'], 'performed_at' => $w['performed_at']],
            'user' => ['username' => $u['username'], 'level' => (int)$u['level']]];
        $details = webhook_details($wid, (string)($u['unit'] ?? 'kg'));
        if ($details) {
            $payload['details'] = $details;
        }
        webhook_post($url, $payload, (string)setting('webhook_key:' . $uid, ''));
    } catch (Throwable $e) {
        // never let a broken webhook break saving a workout
    }
}
