<?php
declare(strict_types=1);

/*
 * Quest lines: multi-step quests you start yourself. Each step is a task counted from the moment you
 * start the quest. Finishing a step pays points; finishing the whole quest pays a bonus.
 * (The old counters such as "Century Club" are now called Milestones and live under Achievements.)
 */

function ql_ensure_tables(): void
{
    if (setting('ql_tables_ok4') === '1') {
        return;
    }
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS quest_runs (
            user_id INT UNSIGNED NOT NULL, quest VARCHAR(30) NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME NULL,
            PRIMARY KEY (user_id, quest),
            CONSTRAINT fk_qr_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        db()->exec("CREATE TABLE IF NOT EXISTS quest_steps (
            user_id INT UNSIGNED NOT NULL, quest VARCHAR(30) NOT NULL, step INT UNSIGNED NOT NULL, reward INT UNSIGNED NOT NULL DEFAULT 0, done_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, quest, step),
            CONSTRAINT fk_qs_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        db()->exec("CREATE TABLE IF NOT EXISTS challenges (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            challenger_id INT UNSIGNED NOT NULL, opponent_id INT UNSIGNED NOT NULL, days TINYINT UNSIGNED NOT NULL DEFAULT 7,
            status VARCHAR(10) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL, starts_at DATETIME NULL, ends_at DATETIME NULL,
            winner_id INT UNSIGNED NULL, KEY idx_c (challenger_id), KEY idx_o (opponent_id), KEY idx_s (status, ends_at),
            CONSTRAINT fk_ch_a FOREIGN KEY (challenger_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_ch_b FOREIGN KEY (opponent_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        db()->exec("CREATE TABLE IF NOT EXISTS feedback (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NULL, kind VARCHAR(12) NOT NULL DEFAULT 'idea',
            message TEXT NOT NULL, page VARCHAR(200) NULL, agent VARCHAR(200) NULL, contact VARCHAR(120) NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'new', note VARCHAR(500) NULL, created_at DATETIME NOT NULL,
            KEY idx_st (status, id)) $t");
        db()->exec("CREATE TABLE IF NOT EXISTS ex_lists (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, name VARCHAR(40) NOT NULL, color VARCHAR(7) NOT NULL DEFAULT '#36a85c',
            payload TEXT NOT NULL, pos INT UNSIGNED NOT NULL DEFAULT 0, KEY idx_u (user_id),
            CONSTRAINT fk_el_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        set_setting('ql_tables_ok4', '1');
    } catch (Throwable $e) {
        error_log('quest tables: ' . $e->getMessage());
    }
}

/** code => [name, story, min level, icon, steps [[task, condition, reward]], completion bonus] */
function quest_lines(): array
{
    $out = [];
    foreach (quest_lines_base() as $code => $l) {
        foreach ($l[4] as $i => $st) {
            $l[4][$i][2] = (int)$st[2] * QUEST_MULT;
        }
        $l[5] = (int)$l[5] * QUEST_MULT;
        $out[$code] = $l;
    }
    return $out;
}

function quest_lines_base(): array
{
    $sq = ['%squat%'];
    return [
        'first_day' => ['Rookie Orientation', 'Everyone starts somewhere. Find your feet in the gym.', 1, '🎒', [
            ['Log your first workout', ['t' => 'workouts', 'n' => 1], 30],
            ['Complete 10 working sets', ['t' => 'sets', 'n' => 10], 40],
            ['Train on 3 different days', ['t' => 'days', 'n' => 3], 60],
        ], 120],
        'iron_path' => ['The Iron Path', 'The big three build the base of every strong lifter.', 2, '🏋️', [
            ['Do 5 squat sets', ['t' => 'sets', 'like' => $sq, 'n' => 5], 50],
            ['Do 5 bench press sets', ['t' => 'sets', 'like' => ['%bench press%'], 'n' => 5], 50],
            ['Do 5 deadlift sets', ['t' => 'sets', 'like' => ['%deadlift%'], 'n' => 5], 50],
            ['Beat a personal record', ['t' => 'prs', 'n' => 1], 100],
        ], 200],
        'pull_weight' => ['Pull Your Weight', 'Build a back that does the talking.', 3, '🧗', [
            ['Do 5 pull-up or chin-up sets', ['t' => 'sets', 'like' => ['%pull-up%', '%chin-up%'], 'n' => 5], 60],
            ['Do 8 rowing sets', ['t' => 'sets', 'like' => ['%row%'], 'n' => 8], 70],
            ['Do 8 biceps curl sets', ['t' => 'sets', 'like' => ['%curl%'], 'n' => 8], 70],
        ], 200],
        'heart_lungs' => ['Heart & Lungs', 'Get your engine running.', 3, '🫁', [
            ['Do 20 minutes of cardio', ['t' => 'cardio', 'n' => 20], 50],
            ['Do 60 minutes of cardio in total', ['t' => 'cardio', 'n' => 60], 80],
            ['Do 150 minutes of cardio in total', ['t' => 'cardio', 'n' => 150], 150],
        ], 250],
        'leg_day' => ['Leg Day Every Day', 'Never skip it. Your future self says thanks.', 5, '🦵', [
            ['Do 9 squat sets', ['t' => 'sets', 'like' => $sq, 'n' => 9], 80],
            ['Do 6 lunge sets', ['t' => 'sets', 'like' => ['%lunge%', '%split squat%'], 'n' => 6], 80],
            ['Do 6 calf raise sets', ['t' => 'sets', 'like' => ['%calf%'], 'n' => 6], 60],
            ['Do 6 hip hinge sets (deadlift, hip thrust)', ['t' => 'sets', 'like' => ['%deadlift%', '%hip thrust%'], 'n' => 6], 80],
        ], 300],
        'core_values' => ['Core Values', 'A strong middle holds everything together.', 5, '🎯', [
            ['Do 10 core sets', ['t' => 'sets', 'muscle' => 'core', 'n' => 10], 70],
            ['Do 25 core sets', ['t' => 'sets', 'muscle' => 'core', 'n' => 25], 100],
            ['Train core on 4 different days', ['t' => 'days', 'muscle' => 'core', 'n' => 4], 130],
        ], 250],
        'press_on' => ['Pressing Matters', 'Push heavy things away from you.', 8, '🛡️', [
            ['Do 6 overhead press sets', ['t' => 'sets', 'like' => ['%overhead press%', '%shoulder press%'], 'n' => 6], 100],
            ['Do 9 bench press sets', ['t' => 'sets', 'like' => ['%bench press%'], 'n' => 9], 100],
            ['Do 6 dip sets', ['t' => 'sets', 'like' => ['%dip%'], 'n' => 6], 100],
            ['Set 2 personal records', ['t' => 'prs', 'n' => 2], 150],
        ], 400],
        'standards' => ['Strength Standards', 'Lift your own bodyweight and more (needs your bodyweight in Settings).', 10, '⚖️', [
            ['Squat 1× your bodyweight (estimated max)', ['t' => 'ratio', 'like' => ['%back squat%'], 'x' => 1.0], 200],
            ['Bench press 0.75× your bodyweight', ['t' => 'ratio', 'like' => ['%barbell bench press%'], 'x' => 0.75], 200],
            ['Deadlift 1.25× your bodyweight', ['t' => 'ratio', 'like' => ['deadlift'], 'x' => 1.25], 300],
        ], 600],
        'long_game' => ['The Long Game', 'Consistency beats intensity. Show up.', 12, '🐢', [
            ['Train on 12 different days', ['t' => 'days', 'n' => 12], 150],
            ['Train on 30 different days', ['t' => 'days', 'n' => 30], 300],
            ['Train on 60 different days', ['t' => 'days', 'n' => 60], 500],
        ], 1000],
    ];
}

function ql_like_sql(array $c, array &$p): string
{
    $sql = '';
    if (!empty($c['like'])) {
        $parts = [];
        foreach ($c['like'] as $l) {
            $parts[] = 'e.name LIKE ?';
            $p[] = $l;
        }
        $sql .= ' AND (' . implode(' OR ', $parts) . ')';
    }
    if (!empty($c['muscle'])) {
        $sql .= ' AND e.muscle = ?';
        $p[] = $c['muscle'];
    }
    return $sql;
}

/** [current value, target] for one condition. */
function ql_value(int $uid, array $c, string $since, ?float $bw): array
{
    $base = 'FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id JOIN workouts w ON w.id = s.workout_id WHERE s.user_id = ? AND s.is_warmup = 0';
    switch ($c['t']) {
        case 'workouts':
            return [(float)val('SELECT COUNT(*) FROM workouts WHERE user_id = ? AND performed_at >= ?', [$uid, $since]), (float)$c['n']];
        case 'prs':
            return [(float)val('SELECT COUNT(*) FROM workout_sets s JOIN workouts w ON w.id = s.workout_id WHERE s.user_id = ? AND s.is_pr = 1 AND w.performed_at >= ?', [$uid, $since]), (float)$c['n']];
        case 'cardio':
            return [(float)val("SELECT COALESCE(SUM(s.duration_sec),0)/60 $base AND e.kind = 'cardio' AND w.performed_at >= ?", [$uid, $since]), (float)$c['n']];
        case 'days':
            $p = [$uid, $since];
            $sql = ql_like_sql($c, $p);
            return [(float)val("SELECT COUNT(DISTINCT DATE(w.performed_at)) $base AND w.performed_at >= ?$sql", $p), (float)$c['n']];
        case 'sets':
            $p = [$uid, $since];
            $sql = ql_like_sql($c, $p);
            $kind = empty($c['like']) ? " AND e.kind <> 'cardio'" : '';
            return [(float)val("SELECT COUNT(*) $base AND w.performed_at >= ?$kind$sql", $p), (float)$c['n']];
        case 'ratio':
            if (!$bw || $bw <= 0) {
                return [0.0, (float)$c['x']];
            }
            $p = [$uid];
            $sql = ql_like_sql($c, $p);
            $best = (float)val("SELECT COALESCE(MAX(s.weight_kg * (1 + LEAST(s.reps, 12) / 30)),0) $base AND s.weight_kg > 0 AND s.reps > 0$sql", $p);
            return [round($best / $bw, 2), (float)$c['x']];
    }
    return [0.0, 1.0];
}

/** Advances all running quests for a member. Returns what was completed (steps and whole quests). */
function ql_check(int $uid): array
{
    ql_ensure_tables();
    $out = ['steps' => [], 'quests' => []];
    $bw = val('SELECT bodyweight_kg FROM users WHERE id = ?', [$uid]);
    $bw = $bw !== null && $bw !== false ? (float)$bw : null;
    $lines = quest_lines();
    foreach (rows('SELECT * FROM quest_runs WHERE user_id = ? AND finished_at IS NULL', [$uid]) as $run) {
        $code = $run['quest'];
        if (!isset($lines[$code])) {
            continue;
        }
        [$name, , , , $steps, $bonus] = $lines[$code];
        $done = (int)val('SELECT COUNT(*) FROM quest_steps WHERE user_id = ? AND quest = ? AND step < 99', [$uid, $code]);
        while ($done < count($steps)) {
            [$task, $cond, $reward] = $steps[$done];
            [$cur, $need] = ql_value($uid, $cond, $run['started_at'], $bw);
            if ($cur < $need) {
                break;
            }
            q('INSERT IGNORE INTO quest_steps (user_id, quest, step, reward, done_at) VALUES (?,?,?,?,?)', [$uid, $code, $done, $reward, now()]);
            q('INSERT INTO notifications (user_id, type, text, created_at) VALUES (?,?,?,?)', [$uid, 'quest', "Quest step done: $task (+$reward pts)", now()]);
            $out['steps'][] = ['quest' => $name, 'task' => $task, 'reward' => $reward];
            $done++;
        }
        if ($done >= count($steps)) {
            q('INSERT IGNORE INTO quest_steps (user_id, quest, step, reward, done_at) VALUES (?,?,?,?,?)', [$uid, $code, 99, $bonus, now()]);
            q('UPDATE quest_runs SET finished_at = ? WHERE user_id = ? AND quest = ?', [now(), $uid, $code]);
            q('INSERT INTO notifications (user_id, type, text, created_at) VALUES (?,?,?,?)', [$uid, 'quest', "Quest complete: $name (+$bonus pts bonus)", now()]);
            $out['quests'][] = ['name' => $name, 'reward' => $bonus];
        }
    }
    return $out;
}
