<?php
declare(strict_types=1);

/*
 * REST API v1. Auth: "Authorization: Bearer <api key>" (create it under Settings > API access).
 *   GET  /api/v1/me
 *   GET  /api/v1/exercises
 *   GET  /api/v1/workouts?after_id=0&since=2026-01-01T00:00:00&limit=50
 *   POST /api/v1/workouts   JSON body (see README)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Api-Key');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$route = trim((string)($args[0] ?? ''), '/');

// Raspberry Pi research upload (admin generates a token under Admin > Fitocracy data)
if ($route === 'fito/upload') {
    $tok = (string)($_SERVER['HTTP_X_FITO_TOKEN'] ?? '');
    $good = (string)setting('fito_token', '', true);
    if (!is_post() || $good === '' || !hash_equals($good, $tok)) {
        rate_hit('apifail:' . client_ip());
        json_out(['ok' => false, 'error' => 'Bad token'], 403);
    }
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($_GET['name'] ?? ''));
    if (!in_array($name, ['digest.txt', 'urls.tsv', 'images.zip', 'status.json', 'calib.tsv', 'calib_blocks.txt', 'calib_images.zip', 'ocr.tsv', 'levels_found.tsv', 'sets.tsv'], true)) {
        json_out(['ok' => false, 'error' => 'Unknown file'], 400);
    }
    $data = (string)file_get_contents('php://input');
    if ($data === '' || strlen($data) > 12 * 1024 * 1024) {
        json_out(['ok' => false, 'error' => 'Empty or too large'], 413);
    }
    $dir = APP_ROOT . '/config/fito';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    file_put_contents($dir . '/' . $name, $data, LOCK_EX);
    if ($name === 'images.zip' && class_exists('ZipArchive')) {
        $z = new ZipArchive();
        if ($z->open($dir . '/images.zip') === true) {
            @mkdir($dir . '/images', 0750, true);
            for ($i = 0; $i < min($z->numFiles, 400); $i++) {
                $n = basename((string)$z->getNameIndex($i));
                if (preg_match('/^[A-Za-z0-9_-]+\.(png|jpe?g|gif|webp)$/i', $n)) {
                    file_put_contents($dir . '/images/' . $n, (string)$z->getFromIndex($i));
                }
            }
            $z->close();
        }
    }
    json_out(['ok' => true, 'bytes' => strlen($data)]);
}


// Public stats card for forum signatures, Discord, READMEs: /api/v1/card/USERNAME.svg  (shows only level, points, streak, workouts)
if (preg_match('#^card/([A-Za-z0-9_.-]{1,40})\.svg$#', $route, $cm)) {
    $cu = row('SELECT * FROM users WHERE username = ? AND is_banned = 0', [$cm[1]]);
    if (!$cu) {
        http_response_code(404);
        exit;
    }
    $pr = level_progress((int)$cu['xp']);
    $st = user_stats((int)$cu['id']);
    $x = static fn(string $t): string => htmlspecialchars($t, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $w = (int)round(360 * max(0, min(100, $pr['pct'])) / 100);
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="130" viewBox="0 0 420 130" role="img" aria-label="' . $x(display_name($cu)) . ' on MyFitLevel">'
        . '<rect width="420" height="130" rx="14" fill="#14181f"/><rect x="0" y="0" width="8" height="130" rx="4" fill="#36a85c"/>'
        . '<text x="28" y="40" fill="#fff" font-family="Arial,sans-serif" font-size="22" font-weight="700">' . $x(display_name($cu)) . '</text>'
        . '<text x="392" y="40" text-anchor="end" fill="#36a85c" font-family="Arial,sans-serif" font-size="22" font-weight="700">Level ' . (int)$pr['level'] . '</text>'
        . '<rect x="28" y="54" width="360" height="10" rx="5" fill="#2a313c"/><rect x="28" y="54" width="' . $w . '" height="10" rx="5" fill="#36a85c"/>'
        . '<text x="28" y="96" fill="#cfd6df" font-family="Arial,sans-serif" font-size="15">🔥 ' . (int)$st['streak'] . '-day streak   ·   🏋 ' . (int)$st['workouts'] . ' workouts   ·   🏆 ' . (int)$st['prs'] . ' PRs</text>'
        . '<text x="28" y="118" fill="#7f8a99" font-family="Arial,sans-serif" font-size="11">' . number_format((int)$cu['xp']) . ' points · myfitlevel.com</text></svg>';
    exit;
}

$user = api_user(($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && in_array($route, ['widget', 'me', 'stats', 'quests', 'today', 'summary', 'prs', 'week', 'export.csv', 'calendar.ics'], true));
if (!$user) {
    if (!rate_ok('apifail:' . client_ip(), 30, 600)) {
        json_out(['ok' => false, 'error' => 'Too many failed requests'], 429);
    }
    rate_hit('apifail:' . client_ip());
    json_out(['ok' => false, 'error' => 'Missing or invalid API key'], 401);
}
$uid = (int)$user['id'];

function api_workouts(int $uid, array $where, array $params, int $limit): array
{
    $ws = rows('SELECT * FROM workouts WHERE user_id = ? ' . $where[0] . ' ORDER BY id ASC LIMIT ' . $limit, array_merge([$uid], $params));
    if (!$ws) {
        return [];
    }
    $ids = array_map(fn($w) => (int)$w['id'], $ws);
    $sets = rows('SELECT s.*, e.name, e.slug, e.muscle, e.kind FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id
                  WHERE s.workout_id IN (' . in_clause($ids) . ') ORDER BY s.id', $ids);
    $by = [];
    foreach ($sets as $s) {
        $by[$s['workout_id']][] = [
            'exercise' => $s['name'], 'slug' => $s['slug'], 'muscle' => $s['muscle'], 'kind' => $s['kind'],
            'weight_kg' => $s['weight_kg'] !== null ? (float)$s['weight_kg'] : null,
            'reps' => $s['reps'] !== null ? (int)$s['reps'] : null,
            'duration_sec' => $s['duration_sec'] !== null ? (int)$s['duration_sec'] : null,
            'distance_m' => $s['distance_m'] !== null ? (int)$s['distance_m'] : null,
            'points' => (int)$s['points'], 'is_pr' => (bool)$s['is_pr'], 'is_warmup' => (bool)$s['is_warmup'],
        ];
    }
    return array_map(fn($w) => [
        'id' => (int)$w['id'], 'performed_at' => $w['performed_at'], 'created_at' => $w['created_at'],
        'title' => $w['title'], 'notes' => $w['notes'],
        'points' => (int)$w['points'], 'volume_kg' => (float)$w['volume_kg'], 'set_count' => (int)$w['set_count'],
        'sets' => $by[$w['id']] ?? [],
    ], $ws);
}

switch ($route) {
    case 'me':
        json_out(['ok' => true, 'user' => [
            'username' => $user['username'], 'display_name' => $user['display_name'],
            'level' => (int)$user['level'], 'xp' => (int)$user['xp'], 'unit' => $user['unit'],
        ]]);

    case 'widget':   // one flat object, easy to read from Rainmeter, Lively, Scriptable and similar
        $pr = level_progress((int)$user['xp']);
        $st = user_stats($uid);
        $dow = today_dow($user);
        $tr = row('SELECT r.name FROM routine_schedule s JOIN routines r ON r.id = s.routine_id WHERE s.user_id = ? AND s.dow = ?', [$uid, $dow]);
        $wk = date('Y-m-d H:i:s', strtotime('monday this week'));
        json_out(['ok' => true, 'name' => display_name($user), 'username' => $user['username'], 'level' => $pr['level'], 'xp' => (int)$user['xp'],
            'level_xp' => $pr['into'], 'level_need' => $pr['need'], 'level_pct' => $pr['pct'], 'next_level_xp' => $pr['next'],
            'streak' => $st['streak'], 'workouts' => $st['workouts'], 'prs' => $st['prs'], 'followers' => $st['followers'],
            'week_workouts' => (int)val('SELECT COUNT(*) FROM workouts WHERE user_id = ? AND performed_at >= ?', [$uid, $wk]),
            'week_points' => (int)val('SELECT COALESCE(SUM(points),0) FROM workouts WHERE user_id = ? AND performed_at >= ?', [$uid, $wk]),
            'week_goal' => weekly_goal($uid), 'week_days' => week_days_done($uid, bonus_week_start()), 'week_streak' => weekly_streak($uid, bonus_week_start()),
            'today_routine' => $tr ? $tr['name'] : 'Rest day', 'today_label' => $tr ? $tr['name'] : 'Rest day']);

    case 'widgets':   // everything the Android home-screen widgets need, in one small call
        game_ensure();
        $pr = level_progress((int)$user['xp']);
        $st = user_stats($uid);
        $wl = game_wallet($uid);
        $ch = game_chest_state($uid);
        $goal = max(1000, (int)($wl['steps_goal'] ?? 10000));
        $stepRow = row('SELECT steps, distance_m, updated_at FROM daily_steps WHERE user_id = ? AND day = ?', [$uid, date('Y-m-d')]);
        $yest = (int)val('SELECT COALESCE(steps,0) FROM daily_steps WHERE user_id = ? AND day = ?', [$uid, date('Y-m-d', strtotime('yesterday'))]);
        $wk = date('Y-m-d H:i:s', strtotime('monday this week'));
        $bossOut = function (?array $b) use ($uid): ?array {
            if (!$b) {
                return null;
            }
            $r = game_roster_by_key((string)$b['boss_key']);
            return ['id' => (int)$b['id'], 'name' => $b['name'], 'emoji' => $r[2], 'tier' => (int)$b['tier'], 'hp' => (int)$b['hp'], 'max_hp' => (int)$b['max_hp'],
                'pct' => (int)max(0, min(100, round((int)$b['hp'] / max(1, (int)$b['max_hp']) * 100))), 'left' => game_fmt_left((string)$b['ends_at']),
                'ends_at' => $b['ends_at'], 'my_damage' => (int)val('SELECT COALESCE(SUM(damage),0) FROM boss_log WHERE boss_id = ? AND user_id = ?', [$b['id'], $uid]),
                'raid' => $b['team_id'] !== null];
        };
        $team = game_team_of($uid);
        $nm = fn(array $r): string => ($r['display_name'] ?? '') !== '' ? (string)$r['display_name'] : (string)$r['username'];
        $mk = function (string $t, string $who, string $text) {
            return ['at' => $t, 'who' => $who, 'text' => $text];
        };
        $all = [];
        foreach (rows("SELECT w.performed_at t, w.title, w.points, w.set_count, us.username, us.display_name FROM workouts w JOIN users us ON us.id = w.user_id WHERE us.is_banned = 0 ORDER BY w.performed_at DESC LIMIT 8") as $r) {
            $all[] = $mk($r['t'], $nm($r), '🏋️ ' . ($r['title'] !== null && $r['title'] !== '' ? $r['title'] : 'a workout') . ' · ' . (int)$r['points'] . ' pts');
        }
        foreach (rows("SELECT p.created_at t, p.body, us.username, us.display_name FROM posts p JOIN users us ON us.id = p.user_id WHERE us.is_banned = 0 ORDER BY p.created_at DESC LIMIT 5") as $r) {
            $all[] = $mk($r['t'], $nm($r), '💬 ' . mb_substr(trim(preg_replace('/\s+/', ' ', (string)$r['body'])), 0, 90));
        }
        usort($all, fn($a, $b) => strcmp($b['at'], $a['at']));
        $mine = [];
        foreach (rows('SELECT performed_at t, title, points FROM workouts WHERE user_id = ? ORDER BY performed_at DESC LIMIT 6', [$uid]) as $r) {
            $mine[] = $mk($r['t'], 'You', '🏋️ ' . ($r['title'] !== null && $r['title'] !== '' ? $r['title'] : 'Workout') . ' · +' . (int)$r['points'] . ' pts');
        }
        foreach (rows('SELECT updated_at t, steps FROM daily_steps WHERE user_id = ? AND steps >= 1000 ORDER BY day DESC LIMIT 3', [$uid]) as $r) {
            $mine[] = $mk($r['t'], 'You', '👟 ' . number_format((int)$r['steps']) . ' steps');
        }
        foreach (rows('SELECT created_at t, body FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 3', [$uid]) as $r) {
            $mine[] = $mk($r['t'], 'You', '💬 ' . mb_substr(trim(preg_replace('/\s+/', ' ', (string)$r['body'])), 0, 90));
        }
        usort($mine, fn($a, $b) => strcmp($b['at'], $a['at']));
        json_out(['ok' => true, 'now' => now(), 'name' => display_name($user), 'username' => $user['username'],
            'level' => $pr['level'], 'xp' => (int)$user['xp'], 'level_xp' => $pr['into'], 'level_need' => $pr['need'], 'level_pct' => $pr['pct'],
            'streak' => $st['streak'], 'workouts' => $st['workouts'], 'prs' => $st['prs'],
            'week_workouts' => (int)val('SELECT COUNT(*) FROM workouts WHERE user_id = ? AND performed_at >= ?', [$uid, $wk]),
            'week_points' => (int)val('SELECT COALESCE(SUM(points),0) FROM workouts WHERE user_id = ? AND performed_at >= ?', [$uid, $wk]),
            'week_goal' => weekly_goal($uid), 'week_days' => week_days_done($uid, bonus_week_start()),
            'steps_today' => (int)($stepRow['steps'] ?? 0), 'distance_m' => (int)($stepRow['distance_m'] ?? 0), 'steps_goal' => $goal,
            'steps_yesterday' => $yest, 'steps_updated' => $stepRow['updated_at'] ?? null,
            'coins' => (int)($wl['coins'] ?? 0), 'chest_ready' => (bool)$ch['can'], 'chest_reward' => (int)$ch['reward'], 'chest_streak' => (int)$ch['streak'],
            'boss' => $bossOut(game_boss_public()), 'raid' => $team ? $bossOut(game_boss_team((int)$team['id'])) : null,
            'unread' => (int)val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid]),
            'feed_all' => array_slice($all, 0, 6), 'feed_mine' => array_slice($mine, 0, 6)]);

    case 'stats':
        $st = user_stats($uid);
        json_out(['ok' => true, 'stats' => [
            'workouts' => $st['workouts'], 'sets' => $st['sets'], 'volume_kg' => round($st['volume_kg'], 1), 'prs' => $st['prs'], 'streak' => $st['streak'],
            'cardio_minutes' => $st['cardio_min'], 'distinct_exercises' => $st['distinct_exercises'], 'following' => $st['following'], 'followers' => $st['followers'],
            'sets_by_group' => ['chest' => $st['sets_chest'], 'back' => $st['sets_back'], 'shoulders' => $st['sets_shoulders'], 'arms' => $st['sets_arms'], 'legs' => $st['sets_legs'], 'core' => $st['sets_core']],
        ]]);

    case 'quests':
        $out = [];
        $lines = quest_lines();
        foreach (rows('SELECT * FROM quest_runs WHERE user_id = ?', [$uid]) as $run) {
            if (!isset($lines[$run['quest']])) { continue; }
            [$qn, , , , $steps, $bonus] = $lines[$run['quest']];
            $done = (int)val('SELECT COUNT(*) FROM quest_steps WHERE user_id = ? AND quest = ? AND step < 99', [$uid, $run['quest']]);
            $cur = null;
            if ($run['finished_at'] === null && isset($steps[$done])) {
                [$val, $need] = ql_value($uid, $steps[$done][1], $run['started_at'], $user['bodyweight_kg'] !== null ? (float)$user['bodyweight_kg'] : null);
                $cur = ['task' => $steps[$done][0], 'progress' => round($val, 2), 'target' => $need];
            }
            $out[] = ['code' => $run['quest'], 'name' => $qn, 'steps_total' => count($steps), 'steps_done' => $done, 'finished' => $run['finished_at'] !== null, 'current_step' => $cur, 'bonus' => $bonus];
        }
        json_out(['ok' => true, 'quests' => $out]);

    case 'badges':
        $defs = badge_defs();
        $out = [];
        foreach (rows('SELECT code, earned_at FROM user_badges WHERE user_id = ? ORDER BY earned_at DESC', [$uid]) as $b) {
            if (isset($defs[$b['code']])) { $out[] = ['code' => $b['code'], 'name' => $defs[$b['code']][0], 'description' => $defs[$b['code']][1], 'earned_at' => $b['earned_at']]; }
        }
        json_out(['ok' => true, 'badges' => $out, 'total_available' => count($defs)]);

    case 'routines':
        $names = array_column(rows('SELECT id, name FROM exercises'), 'name', 'id');
        $out = [];
        foreach (rows('SELECT * FROM routines WHERE user_id = ? ORDER BY name', [$uid]) as $r) {
            $out[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'exercises' => array_map(static fn($it) => ['exercise' => $names[(int)$it['id']] ?? '?', 'sets' => (int)$it['n'], 'reps' => $it['reps'] ?? null, 'weight_kg' => $it['w'] ?? null, 'rest_sec' => $it['rest'] ?? null, 'note' => $it['note'] ?? null], routine_items($r))];
        }
        $sched = [];
        foreach (rows('SELECT dow, routine_id FROM routine_schedule WHERE user_id = ?', [$uid]) as $s) { $sched[(string)$s['dow']] = (int)$s['routine_id']; }
        json_out(['ok' => true, 'routines' => $out, 'schedule' => $sched]);

    case 'today':
        $tr = row('SELECT r.id, r.name, r.payload FROM routine_schedule s JOIN routines r ON r.id = s.routine_id WHERE s.user_id = ? AND s.dow = ?', [$uid, today_dow($user)]);
        json_out(['ok' => true, 'rest_day' => !$tr, 'routine' => $tr ? ['id' => (int)$tr['id'], 'name' => $tr['name'], 'exercise_count' => count(routine_items($tr))] : null]);

    case 'leaders':
        $top = rows('SELECT username, display_name, level, xp FROM users WHERE is_banned = 0 ORDER BY xp DESC LIMIT 20');
        json_out(['ok' => true, 'leaders' => array_map(static fn($t, $i) => ['rank' => $i + 1, 'username' => $t['username'], 'name' => display_name($t), 'level' => (int)$t['level'], 'xp' => (int)$t['xp']], $top, array_keys($top))]);


    case 'summary':   // one sentence, handy for Siri/Shortcuts/notifications. Add ?format=text for plain text.
        $pr = level_progress((int)$user['xp']);
        $st = user_stats($uid);
        $wk = date('Y-m-d H:i:s', strtotime('monday this week'));
        $wn = (int)val('SELECT COUNT(*) FROM workouts WHERE user_id = ? AND performed_at >= ?', [$uid, $wk]);
        $wp = (int)val('SELECT COALESCE(SUM(points),0) FROM workouts WHERE user_id = ? AND performed_at >= ?', [$uid, $wk]);
        $tr = row('SELECT r.name FROM routine_schedule s JOIN routines r ON r.id = s.routine_id WHERE s.user_id = ? AND s.dow = ?', [$uid, today_dow($user)]);
        $txt = 'Level ' . $pr['level'] . ' (' . $pr['pct'] . '% to the next), ' . $st['streak'] . '-day streak, ' . $wn . ' workout' . ($wn === 1 ? '' : 's') . ' and ' . $wp . ' points this week.' . ($tr ? ' Today: ' . $tr['name'] . '.' : '');
        if (($_GET['format'] ?? '') === 'text') {
            header('Content-Type: text/plain; charset=utf-8');
            echo $txt;
            exit;
        }
        json_out(['ok' => true, 'text' => $txt, 'level' => $pr['level'], 'streak' => $st['streak'], 'week_workouts' => $wn, 'week_points' => $wp]);

    case 'prs':       // best lift per exercise (estimated one-rep max)
        $out = [];
        foreach (rows('SELECT e.name, e.slug, MAX(s.weight_kg) best_kg, MAX(s.weight_kg * (1 + s.reps / 30)) e1rm FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id
                       WHERE s.user_id = ? AND s.is_warmup = 0 AND s.weight_kg > 0 AND s.reps > 0 GROUP BY e.id ORDER BY e1rm DESC LIMIT 100', [$uid]) as $r) {
            $out[] = ['exercise' => $r['name'], 'slug' => $r['slug'], 'heaviest_kg' => (float)$r['best_kg'], 'est_1rm_kg' => round((float)$r['e1rm'], 1)];
        }
        json_out(['ok' => true, 'prs' => $out]);

    case 'week':      // last 7 days, one entry per day (good for small charts)
        $from = date('Y-m-d', strtotime('-6 days'));
        $by = [];
        foreach (rows('SELECT DATE(performed_at) d, COUNT(*) n, COALESCE(SUM(points),0) p, COALESCE(SUM(set_count),0) s FROM workouts WHERE user_id = ? AND performed_at >= ? GROUP BY DATE(performed_at)', [$uid, $from . ' 00:00:00']) as $r) {
            $by[$r['d']] = $r;
        }
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $days[] = ['date' => $d, 'workouts' => (int)($by[$d]['n'] ?? 0), 'points' => (int)($by[$d]['p'] ?? 0), 'sets' => (int)($by[$d]['s'] ?? 0)];
        }
        json_out(['ok' => true, 'days' => $days]);

    case 'export.csv':   // every set you have logged; opens in Excel, Numbers, Google Sheets (=IMPORTDATA)
        $cell = static function ($v): string {
            $v = (string)$v;
            if ($v !== '' && strpos('=+-@', $v[0]) !== false && !is_numeric($v)) { $v = "'" . $v; }
            return '"' . str_replace('"', '""', $v) . '"';
        };
        header('Content-Type: text/csv; charset=utf-8');
        if (empty($_GET['inline'])) { header('Content-Disposition: attachment; filename="myfitlevel-sets.csv"'); }
        echo "date,workout_id,title,exercise,muscle,weight_kg,reps,duration_sec,distance_m,points,is_pr,is_warmup\n";
        foreach (rows('SELECT w.performed_at, w.id wid, w.title, e.name, e.muscle, s.weight_kg, s.reps, s.duration_sec, s.distance_m, s.points, s.is_pr, s.is_warmup
                       FROM workout_sets s JOIN workouts w ON w.id = s.workout_id JOIN exercises e ON e.id = s.exercise_id WHERE w.user_id = ? ORDER BY w.performed_at, s.id LIMIT 50000', [$uid]) as $r) {
            echo implode(',', [$cell($r['performed_at']), (int)$r['wid'], $cell($r['title']), $cell($r['name']), $cell($r['muscle']), $r['weight_kg'] ?? '', $r['reps'] ?? '', $r['duration_sec'] ?? '', $r['distance_m'] ?? '', (int)$r['points'], (int)$r['is_pr'], (int)$r['is_warmup']]), "\n";
        }
        exit;

    case 'calendar.ics':   // subscribe in Google/Apple/Outlook calendar to see your workouts as events
        $esc = static fn(string $t): string => str_replace(["\\", ';', ',', "\r", "\n"], ['\\\\', '\;', '\,', '', '\n'], $t);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Cache-Control: public, max-age=900');
        echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//MyFitLevel//EN\r\nCALSCALE:GREGORIAN\r\nX-WR-CALNAME:MyFitLevel workouts\r\n";
        foreach (rows('SELECT id, title, performed_at, points, set_count FROM workouts WHERE user_id = ? AND performed_at >= ? ORDER BY performed_at DESC LIMIT 800', [$uid, date('Y-m-d', strtotime('-400 days'))]) as $w) {
            $t = strtotime((string)$w['performed_at']);
            echo "BEGIN:VEVENT\r\nUID:mfl-" . (int)$w['id'] . '@' . ($_SERVER['HTTP_HOST'] ?? 'myfitlevel') . "\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART:" . date('Ymd\THis', $t) . "\r\nDTEND:" . date('Ymd\THis', $t + 3600)
                . "\r\nSUMMARY:" . $esc('💪 ' . (trim((string)$w['title']) !== '' ? $w['title'] : 'Workout') . ' (' . (int)$w['points'] . ' pts)') . "\r\nDESCRIPTION:" . $esc((int)$w['set_count'] . ' sets, ' . (int)$w['points'] . ' points') . "\r\nEND:VEVENT\r\n";
        }
        echo "END:VCALENDAR\r\n";
        exit;

    case 'quicklog':   // the easy way to log: exercises by name, no slugs. POST {"items":[{"exercise":"db bench","sets":3,"reps":8,"weight":30}], "unit":"kg"}
        if (!is_post()) {
            json_out(['ok' => false, 'error' => 'Use POST'], 405);
        }
        if (!rate_ok('apipost:' . $uid, 120, 3600)) {
            json_out(['ok' => false, 'error' => 'Rate limit'], 429);
        }
        $body = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($body)) {
            json_out(['ok' => false, 'error' => 'Send a JSON body'], 400);
        }
        $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : (isset($body['exercise']) ? [$body] : []);
        $lb = strtolower((string)($body['unit'] ?? 'kg')) === 'lb' ? 0.45359237 : 1.0;
        $abbr = ['db' => 'dumbbell', 'bb' => 'barbell', 'kb' => 'kettlebell', 'bw' => 'bodyweight', 'ohp' => 'overhead press', 'bp' => 'bench press', 'rdl' => 'romanian deadlift', 'dl' => 'deadlift', 'sq' => 'squat'];
        $ex = [];
        $matched = [];
        foreach ($items as $it) {
            $q = strtolower(trim((string)($it['exercise'] ?? '')));
            $found = $q === '' ? null : row('SELECT id, name FROM exercises WHERE active = 1 AND (slug = ? OR LOWER(name) = ?)', [$q, $q]);
            if (!$found && $q !== '') {
                $words = [];
                foreach (preg_split('/\s+/', $q) as $wd) { foreach (explode(' ', $abbr[$wd] ?? $wd) as $w2) { $words[] = $w2; } }
                $sql = 'SELECT id, name FROM exercises WHERE active = 1';
                $par = [];
                foreach ($words as $wd) { $sql .= " AND CONCAT(name, ' ', equipment) LIKE ?"; $par[] = '%' . str_replace(['%', '_'], '', $wd) . '%'; }
                $found = row($sql . ' ORDER BY (LOWER(name) LIKE ?) DESC, LENGTH(name) LIMIT 1', array_merge($par, [$q . '%']));
            }
            if (!$found) {
                json_out(['ok' => false, 'error' => 'Could not find an exercise called "' . ($it['exercise'] ?? '') . '". Try a fuller name, or look in /exercises.'], 422);
            }
            $n = max(1, min(20, (int)($it['sets'] ?? 1)));
            $sets = [];
            for ($i = 0; $i < $n; $i++) {
                $sets[] = ['w' => round((float)($it['weight'] ?? 0) * $lb, 2), 'r' => (int)($it['reps'] ?? 0), 't' => (int)round((float)($it['duration_min'] ?? 0) * 60), 'd' => (float)($it['distance_km'] ?? 0), 'wu' => 0];
            }
            $ex[] = ['id' => $found['id'], 'sets' => $sets];
            $matched[] = $found['name'];
        }
        if (!$ex) {
            json_out(['ok' => false, 'error' => 'Send {"exercise":"bench press","sets":3,"reps":8,"weight":60} or {"items":[...]}'], 400);
        }
        $apiUser = $user;
        $apiUser['unit'] = 'kg';
        $res = save_workout($apiUser, ['performed_at' => $body['performed_at'] ?? '', 'title' => $body['title'] ?? '', 'notes' => $body['notes'] ?? '', 'ex' => $ex]);
        rate_hit('apipost:' . $uid);
        if (!$res['ok']) {
            json_out(['ok' => false, 'error' => $res['error']], 422);
        }
        json_out(['ok' => true, 'workout_id' => $res['workout_id'], 'points' => $res['points'], 'level_up' => $res['awards']['level_up'], 'logged' => $matched], 201);

    case 'posts':   // another site (or you) can post into your feed here. POST {"text":"...","source":"snakediet.org","url":"https://..."}
        if (!is_post()) {
            json_out(['ok' => false, 'error' => 'Use POST'], 405);
        }
        if (!rate_ok('post:' . $uid, 20, 600)) {
            json_out(['ok' => false, 'error' => 'Too many posts, try again in a few minutes'], 429);
        }
        $body = json_decode((string)file_get_contents('php://input'), true);
        $txt = is_array($body) ? clean_text((string)($body['text'] ?? $body['content'] ?? ''), 900) : '';
        $src = is_array($body) ? preg_replace('/[^a-z0-9.\-]/i', '', (string)($body['source'] ?? '')) : '';
        $lnk = is_array($body) ? trim((string)($body['url'] ?? '')) : '';
        if ($txt === '') {
            json_out(['ok' => false, 'error' => 'Send {"text":"..."}'], 400);
        }
        if ($src !== '' && strcasecmp($src, (string)parse_url(site_url(''), PHP_URL_HOST)) === 0) {
            json_out(['ok' => false, 'error' => 'Posts from this site itself are ignored (loop protection)'], 422);
        }
        if (preg_match('#^https?://[^\s]{4,300}$#i', $lnk)) {
            $txt .= "\n" . $lnk;
        }
        if ($src !== '') {
            $txt .= "\n(via " . $src . ')';
        }
        q('INSERT INTO posts (user_id, body, created_at) VALUES (?,?,?)', [$uid, clean_text($txt, 1000), now()]);
        rate_hit('post:' . $uid);
        json_out(['ok' => true, 'post_id' => (int)db()->lastInsertId()], 201);

    case 'steps':
        if (is_post()) {
            if (!rate_ok('apipost:' . $uid, 240, 3600)) {
                json_out(['ok' => false, 'error' => 'Rate limit'], 429);
            }
            $body = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($body) || !isset($body['steps'])) {
                json_out(['ok' => false, 'error' => 'Send JSON like {"steps": 8123, "date": "2026-11-24", "distance_m": 6100}'], 400);
            }
            $res = game_steps_set($uid, (string)($body['date'] ?? date('Y-m-d')), (int)round((float)$body['steps']), (int)round((float)($body['distance_m'] ?? 0)), 'api');
            json_out($res, $res['ok'] ? 200 : 422);
        }
        game_ensure();
        json_out(['ok' => true, 'days' => array_map(fn($r) => ['date' => $r['day'], 'steps' => (int)$r['steps'], 'distance_m' => (int)$r['distance_m']], rows('SELECT day, steps, distance_m FROM daily_steps WHERE user_id = ? ORDER BY day DESC LIMIT 30', [$uid]))]);

    case 'exercises':
        $ex = rows('SELECT slug, name, muscle, equipment, kind FROM exercises WHERE active = 1 ORDER BY name');
        json_out(['ok' => true, 'exercises' => $ex]);

    case 'workouts':
        if (is_post()) {
            $peek = json_decode((string)file_get_contents('php://input'), true);
            if (is_array($peek) && !isset($peek['exercises']) && (isset($peek['id']) || isset($peek['minutes']) || isset($peek['type']))) {
                // a finished workout from another site (SnakeDiet): see inbound_workout()
                [$st, $out] = inbound_workout($user, $peek);
                json_out($out, $st);
            }
            if (!rate_ok('apipost:' . $uid, 120, 3600)) {
                json_out(['ok' => false, 'error' => 'Rate limit'], 429);
            }
            $body = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($body)) {
                json_out(['ok' => false, 'error' => 'Send a JSON body'], 400);
            }
            // Accept weights in kg for API clients.
            $apiUser = $user;
            $apiUser['unit'] = 'kg';
            $ex = [];
            foreach ((array)($body['exercises'] ?? []) as $e1) {
                $slug = (string)($e1['slug'] ?? '');
                $row = row('SELECT id FROM exercises WHERE slug = ? AND active = 1', [$slug]);
                if (!$row) {
                    continue;
                }
                $sets = [];
                foreach ((array)($e1['sets'] ?? []) as $s) {
                    $sets[] = [
                        'w' => $s['weight_kg'] ?? 0, 'r' => $s['reps'] ?? 0,
                        't' => $s['duration_sec'] ?? 0, 'd' => isset($s['distance_m']) ? (float)$s['distance_m'] / 1000 : 0,
                        'wu' => !empty($s['is_warmup']) ? 1 : 0,
                    ];
                }
                $ex[] = ['id' => $row['id'], 'sets' => $sets];
            }
            $res = save_workout($apiUser, [
                'performed_at' => $body['performed_at'] ?? '', 'title' => $body['title'] ?? '',
                'notes' => $body['notes'] ?? '', 'ex' => $ex,
            ]);
            rate_hit('apipost:' . $uid);
            if (!$res['ok']) {
                json_out(['ok' => false, 'error' => $res['error']], 422);
            }
            json_out(['ok' => true, 'workout_id' => $res['workout_id'], 'points' => $res['points'], 'level_up' => $res['awards']['level_up']], 201);
        }
        $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
        $where = [''];
        $params = [];
        if (isset($_GET['after_id'])) {
            $where[0] .= ' AND id > ?';
            $params[] = (int)$_GET['after_id'];
        }
        if (!empty($_GET['since']) && strtotime((string)$_GET['since'])) {
            $where[0] .= ' AND performed_at >= ?';
            $params[] = date('Y-m-d H:i:s', strtotime((string)$_GET['since']));
        }
        $list = api_workouts($uid, $where, $params, $limit);
        $last = $list ? end($list)['id'] : (int)($_GET['after_id'] ?? 0);
        json_out(['ok' => true, 'workouts' => $list, 'next_after_id' => $last, 'has_more' => count($list) === $limit]);

    default:
        json_out(['ok' => false, 'error' => 'Unknown endpoint'], 404);
}
