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

function webhook_log_ensure(): void
{
    static $done = false;
    if (!$done) {
        $done = true;
        q('CREATE TABLE IF NOT EXISTS webhook_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            method VARCHAR(8) NOT NULL DEFAULT \'POST\',
            url VARCHAR(300) NOT NULL,
            status SMALLINT NOT NULL DEFAULT 0,
            response VARCHAR(400) NOT NULL DEFAULT \'\',
            KEY idx_user (user_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}

/** Keeps the last 20 outgoing calls per member (never the key). */
function webhook_log_add(int $uid, string $url, int $status, string $resp, string $method = 'POST'): void
{
    try {
        // Also a plain file outside public/ (config/webhook.log, rotated at 512 KB), readable over FTP/SSH. Never contains keys.
        $lf = APP_ROOT . '/config/webhook.log';
        if (is_dir(APP_ROOT . '/config')) {
            if (is_file($lf) && filesize($lf) > 524288) {
                @rename($lf, $lf . '.1');
            }
            @file_put_contents($lf, date('Y-m-d H:i:s') . ' | u' . $uid . ' | ' . ($method === 'IN' ? 'IN ' : 'OUT') . ' | ' . $url . ' | ' . $status . ' | ' . str_replace(["\r", "\n"], ' ', mb_substr($resp, 0, 300)) . "\n", FILE_APPEND | LOCK_EX);
        }
        if ($method !== 'IN' && ($status < 200 || $status >= 300)) {
            AppLog::warn('webhook', 'Call failed: ' . mb_substr($url, 0, 120) . ' -> ' . $status);
        }
        webhook_log_ensure();
        q('INSERT INTO webhook_log (user_id, created_at, method, url, status, response) VALUES (?,?,?,?,?,?)', [$uid, date('Y-m-d H:i:s'), $method, mb_substr($url, 0, 300), $status, mb_substr($resp, 0, 300)]);
        q('DELETE FROM webhook_log WHERE user_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM webhook_log WHERE user_id = ? ORDER BY id DESC LIMIT 20) t)', [$uid, $uid]);
    } catch (Throwable $e) {
    }
}

function webhook_post(string $url, array $payload, string $key = '', int $timeout = 3, int $logUid = 0): bool
{
    $r = webhook_resolve($url);
    if (!$r || !function_exists('curl_init')) {
        $GLOBALS['webhook_last'] = ['code' => 0, 'url' => $url, 'raw' => '', 'error' => 'address not reachable or not allowed', 'body' => []];
        if ($logUid > 0) {
            webhook_log_add($logUid, $url, 0, 'not sent: address could not be resolved or is not allowed');
        }
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
    $cerr = curl_error($ch);
    curl_close($ch);
    $raw = is_string($body) ? trim($body) : '';
    $GLOBALS['webhook_last'] = ['code' => $code, 'url' => $url, 'raw' => $raw, 'error' => $cerr, 'body' => $raw !== '' ? (json_decode($raw, true) ?: []) : []];
    if ($logUid > 0) {
        webhook_log_add($logUid, $url, $code, $raw !== '' ? $raw : ($cerr !== '' ? 'curl: ' . $cerr : ''));
    }
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

function webhook_snakediet_workout(int $uid, int $wid, array $w, array $u, string $url, string $key, int $timeout = 3, string $event = 'workout.logged'): string
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
    if (strtotime((string)$w['performed_at']) < time() - 58 * 86400) {
        return 'skipped';   // SnakeDiet accepts workouts up to 60 days old
    }
    $start = min(strtotime((string)$w['performed_at']), time() - 60);
    $payload = ['id' => 'mfl-' . $uid . '-' . $wid, 'source' => 'myfitlevel.com', 'event' => $event,
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
    $ok = webhook_post('https://snakediet.org/api/v1/workouts', $payload, $key, $timeout, $uid);
    $GLOBALS['webhook_err'] = $ok ? '' : ('HTTP ' . (int)($GLOBALS['webhook_last']['code'] ?? 0) . ' ' . mb_substr((string)($GLOBALS['webhook_last']['raw'] ?? '') ?: (string)($GLOBALS['webhook_last']['error'] ?? ''), 0, 300) . ' [' . (string)($GLOBALS['webhook_last']['url'] ?? '') . ']');
    if (!$ok && (string)($GLOBALS['webhook_last']['body']['error'] ?? '') === 'too_old') {
        return 'skipped';   // not an error
    }
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
    inbound_ensure();
    $ws = rows('SELECT id, title, performed_at, set_count, volume_kg, duration_sec FROM workouts WHERE user_id = ? AND performed_at >= ? AND id NOT IN (SELECT workout_id FROM inbound_workouts WHERE user_id = ?) ORDER BY performed_at LIMIT 25', [$uid, date('Y-m-d H:i:s', time() - $days * 86400), $uid]);
    $res = ['sent' => 0, 'duplicate' => 0, 'failed' => 0, 'skipped' => 0];
    foreach ($ws as $w) {
        $r = webhook_snakediet_workout($uid, (int)$w['id'], $w, $u, $url, $key, 8);
        $res[$r]++;
        if ($r === 'failed' && empty($GLOBALS['webhook_resend_err'])) {
            $GLOBALS['webhook_resend_err'] = (string)($GLOBALS['webhook_err'] ?? '');
        }
    }
    return [$res['sent'], $res['duplicate'], $res['failed']];
}

/* ---------- Incoming workouts from other sites (SnakeDiet) ---------- */

function inbound_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    q('CREATE TABLE IF NOT EXISTS inbound_workouts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        ext_id VARCHAR(80) NOT NULL,
        workout_id INT UNSIGNED NOT NULL,
        source VARCHAR(60) NOT NULL DEFAULT \'\',
        minutes INT UNSIGNED NULL,
        kcal INT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_ext (user_id, ext_id),
        KEY idx_w (workout_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    q("INSERT IGNORE INTO exercises (slug, name, muscle, equipment, kind, coef, bw_pct, dist_coef, active) VALUES ('workout-from-snakediet', 'Workout from SnakeDiet', 'fullbody', 'any', 'cardio', 0.90, 0, 0, 1)");
}

/** True for workouts that came in from another site: they are never sent back out (loop protection). */
function webhook_is_inbound(int $wid): bool
{
    try {
        inbound_ensure();
        return (bool)val('SELECT 1 FROM inbound_workouts WHERE workout_id = ?', [$wid]);
    } catch (Throwable $e) {
        return false;
    }
}

/** Lowercase words of an exercise name without grip/position noise, so "Barbell Bench Press - Medium Grip" ~ "Barbell Bench Press". */
function inbound_tokens(string $name): array
{
    $n = mb_strtolower($name);
    $n = preg_replace('/\s+[-–—]\s+.*$/u', '', $n) ?? $n;          // "... - Medium Grip" -> "..."
    $n = preg_replace('/\([^)]*\)/u', ' ', $n) ?? $n;              // "(machine)"
    $n = str_replace(['-', '_', '/', ','], ' ', $n);
    $n = preg_replace('/[^a-z0-9åäö ]+/u', '', $n) ?? $n;
    $syn = ['biceps' => 'bicep', 'triceps' => 'tricep', 'pushups' => 'push up', 'pushup' => 'push up', 'pullups' => 'pull up', 'pullup' => 'pull up',
            'chinups' => 'chin up', 'chinup' => 'chin up', 'situps' => 'sit up', 'situp' => 'sit up', 'dumbbells' => 'dumbbell', 'barbells' => 'barbell',
            'db' => 'dumbbell', 'bb' => 'barbell', 'kb' => 'kettlebell', 'ez' => 'ez', 'lunges' => 'lunge', 'squats' => 'squat', 'rows' => 'row',
            'curls' => 'curl', 'presses' => 'press', 'raises' => 'raise', 'extensions' => 'extension', 'flyes' => 'fly', 'flys' => 'fly', 'dips' => 'dip'];
    $n = str_replace(['shoulder press', 'military press'], 'overhead press', $n);
    $stop = ['bicep', 'alternate', 'bent', 'over', 'medium', 'wide', 'narrow', 'grip', 'with', 'the', 'a', 'on', 'to', 'of', 'and', 'alternating', 'one', 'arm', 'single', 'exercise'];
    $out = [];
    foreach (preg_split('/\s+/', trim($n)) ?: [] as $w) {
        if ($w === '') {
            continue;
        }
        foreach (explode(' ', $syn[$w] ?? $w) as $x) {
            if (!in_array($x, $stop, true) && $x !== '') {
                $out[$x] = true;
            }
        }
    }
    return array_keys($out);
}

/** Known SnakeDiet (free-exercise-db) names that need a manual link to our exercise slugs. '' = deliberately not matched. */
function inbound_aliases(): array
{
    return [
        'stiff-legged barbell deadlift' => 'stiff-leg-deadlift',
        'reverse flyes' => 'rear-delt-fly',
        'hyperextensions (back extensions)' => 'back-extension',
        'dumbbell alternate bicep curl' => 'dumbbell-curl',
        'high cable curls' => 'cable-curl',
        'lying triceps press' => 'skull-crusher',
        'kneeling cable triceps extension' => 'cable-overhead-triceps-extension',
        'seated triceps press' => 'overhead-triceps-extension',
        'dips - triceps version' => 'triceps-dip',
        'crunches' => 'crunch',
        'ab roller' => 'ab-wheel-rollout',
        'mountain climbers' => 'mountain-climber',
        'elliptical / cross trainer' => 'elliptical',
        'one-arm kettlebell swings' => 'kettlebell-swing',
        "farmer's walk" => 'farmer-s-carry',
        'butterfly' => 'pec-deck',
        'dumbbell step ups' => 'step-up',
        'side lateral raise' => 'dumbbell-lateral-raise',
        'dumbbell shoulder press' => 'seated-dumbbell-shoulder-press',
        'front raise and pullover' => '',
    ];
}

/** Finds an exercise from a free-text name ("Bench press", "Barbell Bench Press - Medium Grip"), or null. */
function inbound_find_exercise(string $name): ?array
{
    static $idx = null;
    $q = strtolower(trim($name));
    if ($q === '') {
        return null;
    }
    $r = row('SELECT id, name FROM exercises WHERE active = 1 AND LOWER(name) = ?', [$q]);
    if ($r) {
        return $r;
    }
    $al = inbound_aliases();
    $qa = preg_replace('/\s+/', ' ', str_replace(['’', '`'], "'", $q));
    if (array_key_exists($qa, $al)) {
        return $al[$qa] === '' ? null : (row('SELECT id, name FROM exercises WHERE active = 1 AND slug = ?', [$al[$qa]]) ?: null);
    }
    if ($idx === null) {
        $idx = [];
        foreach (rows("SELECT id, name FROM exercises WHERE active = 1 AND slug <> 'workout-from-snakediet'") as $e) {
            $idx[] = ['id' => (int)$e['id'], 'name' => (string)$e['name'], 't' => inbound_tokens((string)$e['name'])];
        }
    }
    $eqWords = ['barbell', 'dumbbell', 'kettlebell', 'cable', 'machine', 'smith', 'ez', 'band', 'bodyweight'];
    $t = inbound_tokens($name);
    $tc = array_values(array_diff($t, $eqWords));   // the movement itself
    $te = array_values(array_intersect($t, $eqWords));
    if (!$tc) {
        return null;
    }
    $best = null;
    $bestScore = 0.0;
    foreach ($idx as $e) {
        $ec = array_values(array_diff($e['t'], $eqWords));
        $ee = array_values(array_intersect($e['t'], $eqWords));
        if ($te && $ee && !array_intersect($te, $ee)) {
            continue;   // barbell vs dumbbell: not the same exercise
        }
        $inter = count(array_intersect($tc, $ec));
        if ($inter === 0) {
            continue;
        }
        $score = $inter / count(array_unique(array_merge($tc, $ec)));
        if ($inter === count($tc) && count($ec) - $inter <= 1) {
            $score = max($score, 0.6);   // "Squat" ~ "Back Squat"
        }
        if ($te && array_intersect($te, $ee)) {
            $score += 0.05;   // same equipment is a tie-breaker
        }
        if ($score > $bestScore + 0.0001 || (abs($score - $bestScore) < 0.0001 && $best && mb_strlen($e['name']) < mb_strlen($best['name']))) {
            $bestScore = $score;
            $best = $e;
        }
    }
    return ($best && $bestScore >= 0.6) ? ['id' => $best['id'], 'name' => $best['name']] : null;
}

/** Turns the "sets" list [{exercise, reps|seconds, kg}] into save_workout exercises. Names that match nothing are returned too. */
function inbound_parse_sets(array $sets): array
{
    $by = [];
    $missed = [];
    foreach (array_slice($sets, 0, 300) as $s) {
        if (!is_array($s)) {
            continue;
        }
        $name = trim((string)($s['exercise'] ?? ''));
        if ($name === '') {
            continue;
        }
        $key = mb_strtolower($name);
        if (!isset($by[$key])) {
            $by[$key] = ['name' => $name, 'ex' => inbound_find_exercise($name), 'sets' => []];
        }
        $ex = $by[$key]['ex'];
        if (!$ex) {
            $missed[$name] = true;
            continue;
        }
        $kind = (string)val('SELECT kind FROM exercises WHERE id = ?', [$ex['id']]);
        $reps = (int)round((float)($s['reps'] ?? 0));
        $sec = (int)round((float)($s['seconds'] ?? 0));
        $kg = max(0.0, (float)($s['kg'] ?? 0));
        if (in_array($kind, ['weight', 'bodyweight'], true)) {
            if ($reps < 1) {
                continue;
            }
            $by[$key]['sets'][] = ['w' => round($kg, 2), 'r' => $reps, 't' => 0, 'd' => 0, 'wu' => 0];
        } else {
            if ($sec < 1) {
                continue;
            }
            $by[$key]['sets'][] = ['w' => 0, 'r' => 0, 't' => $sec, 'd' => 0, 'wu' => 0];
        }
    }
    $out = [];
    foreach ($by as $g) {
        if ($g['ex'] && $g['sets']) {
            $out[] = ['id' => $g['ex']['id'], 'sets' => array_slice($g['sets'], 0, 40)];
        }
    }
    return [$out, array_keys($missed)];
}

/** Turns lines like "Bench press — 3×8 @ 80 kg, 1×6 @ 85 kg" into save_workout exercises. Unknown exercises are skipped. */
function inbound_parse_details(array $details, string $unit): array
{
    $out = [];
    foreach (array_slice($details, 0, 60) as $line) {
        $line = (string)$line;
        if (!preg_match('/^(.+?)\s+[—–-]\s+(.+)$/u', $line, $m)) {
            continue;
        }
        $ex = inbound_find_exercise($m[1]);
        if (!$ex) {
            continue;
        }
        $sets = [];
        foreach (explode(',', $m[2]) as $part) {
            if (preg_match('/(\d{1,2})\s*[×x]\s*(\d{1,3})(?:\s*@\s*([\d.,]+)\s*(kg|lb)?)?/iu', $part, $p)) {
                $w = (float)str_replace(',', '.', $p[3] ?? '0');
                $lb = strtolower((string)($p[4] ?? '')) === 'lb';
                for ($i = 0; $i < min(20, (int)$p[1]); $i++) {
                    $sets[] = ['w' => $lb ? round($w * 0.45359237, 2) : $w, 'r' => (int)$p[2], 't' => 0, 'd' => 0, 'wu' => 0];
                }
            }
        }
        if ($sets) {
            $out[] = ['id' => $ex['id'], 'sets' => $sets];
        }
    }
    return $out;
}

/**
 * Receives one workout from another site. Returns [http status, json body].
 * Body: {id, source, title, type, minutes, kcal, start|date, details[]}
 */
function inbound_workout(array $user, array $b): array
{
    $uid = (int)$user['id'];
    $src = strtolower(preg_replace('/[^a-z0-9.\-]/i', '', (string)($b['source'] ?? '')));
    $ext = trim((string)($b['id'] ?? ''));
    $log = static function (int $status, array $body) use ($uid, $src, $ext): array {
        webhook_log_add($uid, '/api/v1/workouts' . ($src !== '' ? ' (from ' . $src . ')' : '') . ($ext !== '' ? ' ' . mb_substr($ext, 0, 60) : ''), $status, (string)json_encode($body, JSON_UNESCAPED_UNICODE), 'IN');
        return [$status, $body];
    };
    if ($ext === '' || !preg_match('/^[A-Za-z0-9._:\-]{3,80}$/', $ext)) {
        return $log(422, ['ok' => false, 'error' => 'invalid_id']);
    }
    if ($src !== '' && strcasecmp($src, (string)parse_url(site_url(''), PHP_URL_HOST)) === 0) {
        return $log(422, ['ok' => false, 'error' => 'own_source']);   // loop protection
    }
    $types = ['gym', 'run', 'walk', 'cardio', 'cycle', 'hiit', 'sport', 'yoga', 'other'];
    $type = strtolower((string)($b['type'] ?? 'other'));
    if (!in_array($type, $types, true)) {
        return $log(422, ['ok' => false, 'error' => 'invalid_type']);
    }
    $minutes = (int)round((float)($b['minutes'] ?? 0));
    if ($minutes < 1 || $minutes > 1440) {
        return $log(422, ['ok' => false, 'error' => 'invalid_minutes']);
    }
    $kcal = isset($b['kcal']) && is_numeric($b['kcal']) ? max(0, min(20000, (int)round((float)$b['kcal']))) : null;

    $tz = user_tz($user);
    try {
        if (!empty($b['start'])) {
            $d = new DateTime((string)$b['start']);
            $d->setTimezone($tz);
        } elseif (!empty($b['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$b['date'])) {
            $d = new DateTime((string)$b['date'] . ' 12:00:00', $tz);
        } else {
            return $log(422, ['ok' => false, 'error' => 'invalid_date']);
        }
    } catch (Throwable $e) {
        return $log(422, ['ok' => false, 'error' => 'invalid_date']);
    }
    if ($d->getTimestamp() > time() + 3600) {
        return $log(422, ['ok' => false, 'error' => 'date_in_future']);
    }
    if ($d->getTimestamp() < strtotime('-3 years')) {
        return $log(422, ['ok' => false, 'error' => 'too_old']);
    }

    inbound_ensure();
    $dup = row('SELECT workout_id FROM inbound_workouts WHERE user_id = ? AND ext_id = ?', [$uid, $ext]);
    $isUpdate = strtolower((string)($b['event'] ?? '')) === 'workout.updated';
    if ($dup && !$isUpdate) {
        return $log(200, ['ok' => true, 'duplicate' => true, 'id' => (int)$dup['workout_id']]);
    }
    $editId = ($dup && $isUpdate) ? (int)$dup['workout_id'] : 0;
    if (!rate_ok('sdin:' . $uid, 60, 600)) {
        return $log(429, ['ok' => false, 'error' => 'rate_limit']);
    }

    $title = clean_text((string)($b['title'] ?? ''), 80);
    $details = is_array($b['details'] ?? null) ? array_map('strval', $b['details']) : [];
    $apiUser = $user;
    $apiUser['unit'] = 'kg';
    $notesBits = ['From ' . ($src !== '' ? $src : 'another site'), $minutes . ' min'];
    if ($kcal !== null) {
        $notesBits[] = $kcal . ' kcal';
    }
    $notes = implode(' · ', $notesBits);
    $input = ['performed_at' => $d->format('Y-m-d H:i:s'), 'title' => $title, 'notes' => $notes, 'duration' => $minutes * 60];
    if ($editId) {
        $input['edit_id'] = $editId;   // an update replaces the sets of the same workout, it does not add a new one
    }

    $GLOBALS['inbound_saving'] = true;   // webhook_fire must not send this workout back out
    try {
    $res = null;
    $parsed = [];
    $missed = [];
    if (is_array($b['sets'] ?? null) && $b['sets']) {
        [$parsed, $missed] = inbound_parse_sets($b['sets']);   // exact sets win over the text lines
    } elseif ($type === 'gym' || $type === 'hiit' || $type === 'sport' || $type === 'other') {
        $parsed = inbound_parse_details($details, 'kg');
    }
    if ($missed) {
        // so SnakeDiet and Emil can build a name mapping: shown in the Connect log
        webhook_log_add($uid, 'exercise names not matched (' . ($src !== '' ? $src : 'other site') . ')', 200, 'Not matched: ' . implode('; ', array_slice($missed, 0, 20)), 'IN');
        $input['notes'] = mb_substr($input['notes'] . ' · Also: ' . implode(', ', array_slice($missed, 0, 8)), 0, 1000);
    }
    if ($parsed) {
        $res = save_workout($apiUser, $input + ['ex' => $parsed]);
        if (!$res['ok']) {
            $res = null;   // e.g. an absurd weight: fall back to a plain timed workout
        }
    }
    if ($res === null) {
        $names = ['run' => 'Running', 'walk' => 'Walking', 'cycle' => 'Cycling', 'cardio' => 'Elliptical', 'yoga' => 'Yoga'];
        $ex = isset($names[$type]) ? row('SELECT id FROM exercises WHERE active = 1 AND name = ?', [$names[$type]]) : null;
        if (!$ex) {
            $ex = row("SELECT id FROM exercises WHERE slug = 'workout-from-snakediet'");
        }
        if (!$ex) {
            return $log(500, ['ok' => false, 'error' => 'no_exercise']);
        }
        $res = save_workout($apiUser, $input + ['ex' => [['id' => $ex['id'], 'sets' => [['w' => 0, 'r' => 0, 't' => $minutes * 60, 'd' => 0, 'wu' => 0]]]]]);
    }
    } finally {
        $GLOBALS['inbound_saving'] = false;
    }
    if (!$res['ok']) {
        return $log(422, ['ok' => false, 'error' => 'save_failed', 'detail' => (string)$res['error']]);
    }
    $wid = (int)$res['workout_id'];
    if ($editId) {
        q('UPDATE inbound_workouts SET minutes = ?, kcal = ? WHERE id = ?', [$minutes, $kcal, (int)val('SELECT id FROM inbound_workouts WHERE workout_id = ?', [$wid])]);
        rate_hit('sdin:' . $uid);
        return $log(200, ['ok' => true, 'updated' => true, 'id' => $wid, 'points' => (int)$res['points']]);
    }
    try {
        q('INSERT INTO inbound_workouts (user_id, ext_id, workout_id, source, minutes, kcal, created_at) VALUES (?,?,?,?,?,?,?)', [$uid, $ext, $wid, $src, $minutes, $kcal, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        // a parallel request with the same id won; keep ours (rare)
    }
    rate_hit('sdin:' . $uid);
    return $log(201, ['ok' => true, 'id' => $wid, 'points' => (int)$res['points'], 'level_up' => (bool)($res['awards']['level_up'] ?? false)]);
}

/** Deletes a workout that came in from another site and takes its points back. Returns [status, body]. */
function inbound_delete(array $user, array $b): array
{
    $uid = (int)$user['id'];
    $src = strtolower(preg_replace('/[^a-z0-9.\-]/i', '', (string)($b['source'] ?? '')));
    $ext = trim((string)($b['id'] ?? ''));
    $log = static function (int $status, array $body) use ($uid, $src, $ext): array {
        webhook_log_add($uid, '/api/v1/workouts/delete' . ($src !== '' ? ' (from ' . $src . ')' : '') . ' ' . mb_substr($ext, 0, 60), $status, (string)json_encode($body, JSON_UNESCAPED_UNICODE), 'IN');
        return [$status, $body];
    };
    if ($ext === '' || !preg_match('/^[A-Za-z0-9._:\-]{3,80}$/', $ext)) {
        return $log(422, ['ok' => false, 'error' => 'invalid_id']);
    }
    if ($src !== '' && strcasecmp($src, (string)parse_url(site_url(''), PHP_URL_HOST)) === 0) {
        return $log(422, ['ok' => false, 'error' => 'own_source']);
    }
    if (!rate_ok('sdin:' . $uid, 60, 600)) {
        return $log(429, ['ok' => false, 'error' => 'rate_limit']);
    }
    inbound_ensure();
    // Only workouts that arrived through this API can be removed this way, never the member's own.
    $r = row('SELECT id, workout_id FROM inbound_workouts WHERE user_id = ? AND ext_id = ?', [$uid, $ext]);
    if (!$r) {
        return $log(200, ['ok' => true, 'deleted' => false]);
    }
    $GLOBALS['inbound_saving'] = true;   // a deletion that came from the other site is never sent back
    try {
        delete_workout((int)$r['workout_id'], $uid);
    } finally {
        $GLOBALS['inbound_saving'] = false;
    }
    q('DELETE FROM inbound_workouts WHERE id = ?', [(int)$r['id']]);
    rate_hit('sdin:' . $uid);
    return $log(200, ['ok' => true, 'deleted' => true]);
}

/** Tells SnakeDiet a workout was deleted here (never for workouts that came from SnakeDiet). */
function webhook_snakediet_delete(int $uid, int $wid): void
{
    try {
        $url = (string)setting('webhook:' . $uid, '');
        $key = (string)setting('webhook_key:' . $uid, '');
        if (!webhook_is_snakediet($url) || $key === '' || !empty($GLOBALS['inbound_saving']) || webhook_is_inbound($wid)) {
            return;
        }
        webhook_post('https://snakediet.org/api/v1/workouts/delete', ['id' => 'mfl-' . $uid . '-' . $wid, 'source' => 'myfitlevel.com', 'event' => 'workout.deleted'], $key, 3, $uid);
    } catch (Throwable $e) {
        // never block a delete
    }
}

function webhook_fire(int $uid, int $wid, int $points, array $awards, bool $isEdit = false): void
{
    try {
        $url = (string)setting('webhook:' . $uid, '');
        if ($url === '') {
            return;
        }
        if (!empty($GLOBALS['inbound_saving']) || webhook_is_inbound($wid)) {
            return;   // came from another site: never send it back
        }
        $w = row('SELECT title, performed_at, set_count, volume_kg, duration_sec FROM workouts WHERE id = ? AND user_id = ?', [$wid, $uid]);
        $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$w || !$u || (strtotime((string)$w['performed_at']) < time() - 36 * 3600 && !($isEdit && webhook_is_snakediet($url)))) {
            return;   // old workouts added afterwards do not notify
        }
        if (webhook_is_snakediet($url)) {
            webhook_snakediet_workout($uid, $wid, $w, $u, $url, (string)setting('webhook_key:' . $uid, ''), 3, $isEdit ? 'workout.updated' : 'workout.logged');
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
        webhook_post($url, $payload, (string)setting('webhook_key:' . $uid, ''), 3, $uid);
    } catch (Throwable $e) {
        // never let a broken webhook break saving a workout
    }
}
