<?php
declare(strict_types=1);

/*
 * Bonus points on top of the sets you log: a weekly goal with a streak, and admin-made events
 * (bonus-XP periods and "do N workouts" goal events).
 *  - Weekly goal rewards are stored as quest_steps rows (quest = 'weekly', step = ISO year+week), like challenge wins.
 *  - Boost events are never stored: their bonus is derived from the workouts inside the event window, so edits
 *    and deletions always stay correct (recalc_xp adds event_bonus_total()).
 *  - Goal-event rewards are stored as quest_steps rows (quest = 'event', step = event id).
 */

const WEEKLY_DEFAULT = 3;

function bonus_week_start(?int $ts = null): int
{
    return (int)strtotime('monday this week 00:00', $ts ?? time());
}

function bonus_week_key(int $monTs): int
{
    return (int)date('oW', $monTs);
}

function weekly_goal(int $uid): int
{
    return max(1, min(7, (int)setting('wgoal:' . $uid, (string)WEEKLY_DEFAULT)));
}

function week_days_done(int $uid, int $monTs): int
{
    return (int)val('SELECT COUNT(DISTINCT DATE(performed_at)) FROM workouts WHERE user_id = ? AND performed_at >= ? AND performed_at < ?',
        [$uid, date('Y-m-d H:i:s', $monTs), date('Y-m-d H:i:s', strtotime('+7 days', $monTs))]);
}

/** Consecutive earlier weeks (ending last week) in which the goal was reached. */
function weekly_streak(int $uid, int $monTs): int
{
    $keys = [];
    foreach (rows("SELECT step FROM quest_steps WHERE user_id = ? AND quest = 'weekly' ORDER BY step DESC LIMIT 120", [$uid]) as $r) {
        $keys[(int)$r['step']] = true;
    }
    $n = 0;
    $t = strtotime('-7 days', $monTs);
    while ($n < 104 && isset($keys[bonus_week_key($t)])) {
        $n++;
        $t = strtotime('-7 days', $t);
    }
    return $n;
}

function weekly_bonus_for(int $target, int $streak): int
{
    return 100 + 60 * $target + 20 * min(12, $streak);
}

/** Pays this week's goal bonus the moment the goal is reached. Returns UI lines for the "workout complete" card. */
function weekly_check(int $uid): array
{
    ql_ensure_tables();
    $mon = bonus_week_start();
    $key = bonus_week_key($mon);
    $target = weekly_goal($uid);
    if (week_days_done($uid, $mon) < $target) {
        return [];
    }
    if ((int)val("SELECT COUNT(*) FROM quest_steps WHERE user_id = ? AND quest = 'weekly' AND step = ?", [$uid, $key]) > 0) {
        return [];
    }
    $streak = weekly_streak($uid, $mon);
    $bonus = weekly_bonus_for($target, $streak);
    q("INSERT IGNORE INTO quest_steps (user_id, quest, step, reward, done_at) VALUES (?,?,?,?,?)", [$uid, 'weekly', $key, $bonus, now()]);
    $txt = 'Weekly goal reached' . ($streak > 0 ? ' · ' . ($streak + 1) . ' weeks in a row' : '');
    q('INSERT INTO notifications (user_id, type, text, created_at) VALUES (?,?,?,?)', [$uid, 'quest', $txt . " (+$bonus pts)", now()]);
    return [['quest' => 'Weekly goal', 'task' => $txt, 'reward' => $bonus]];
}

/* ---------------- events ---------------- */

function events_ensure(): void
{
    if (setting('events_ok1') === '1') {
        return;
    }
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS events (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(80) NOT NULL, description VARCHAR(400) NOT NULL DEFAULT '',
            kind VARCHAR(8) NOT NULL DEFAULT 'boost',
            pct INT UNSIGNED NOT NULL DEFAULT 0, target INT UNSIGNED NOT NULL DEFAULT 0, reward INT UNSIGNED NOT NULL DEFAULT 0,
            starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, created_at DATETIME NOT NULL,
            KEY idx_t (starts_at, ends_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        set_setting('events_ok1', '1');
    } catch (Throwable $e) {
        error_log('events table: ' . $e->getMessage());
    }
}

function event_rows(string $where = '1=1', array $p = []): array
{
    events_ensure();
    return rows('SELECT * FROM events WHERE ' . $where . ' ORDER BY starts_at', $p);
}

function events_fingerprint(): string
{
    return json_encode(array_map(fn($e) => [$e['id'], $e['kind'], $e['pct'], $e['target'], $e['reward'], $e['starts_at'], $e['ends_at']], event_rows()));
}

/** Total bonus from boost events for a member, derived from the workouts inside each window. */
function event_bonus_total(int $uid): int
{
    $sum = 0;
    foreach (event_rows("kind = 'boost' AND pct > 0") as $e) {
        $pts = (int)val('SELECT COALESCE(SUM(points),0) FROM workouts WHERE user_id = ? AND performed_at >= ? AND performed_at < ?', [$uid, $e['starts_at'], $e['ends_at']]);
        $sum += (int)round($pts * (int)$e['pct'] / 100);
    }
    return $sum;
}

/** Lines for a workout just saved at $whenTs worth $points. */
function event_boost_lines(int $whenTs, int $points): array
{
    $out = [];
    $d = date('Y-m-d H:i:s', $whenTs);
    foreach (event_rows("kind = 'boost' AND pct > 0 AND starts_at <= ? AND ends_at > ?", [$d, $d]) as $e) {
        $b = (int)round($points * (int)$e['pct'] / 100);
        if ($b > 0) {
            $out[] = ['quest' => $e['title'], 'task' => '+' . (int)$e['pct'] . '% bonus points', 'reward' => $b];
        }
    }
    return $out;
}

/** Pays goal events ("do N workout days during the event"). */
function event_goal_check(int $uid): array
{
    ql_ensure_tables();
    $out = [];
    $now = now();
    foreach (event_rows("kind = 'goal' AND target > 0 AND reward > 0 AND starts_at <= ? AND ends_at > ?", [$now, $now]) as $e) {
        if ((int)val("SELECT COUNT(*) FROM quest_steps WHERE user_id = ? AND quest = 'event' AND step = ?", [$uid, $e['id']]) > 0) {
            continue;
        }
        $days = (int)val('SELECT COUNT(DISTINCT DATE(performed_at)) FROM workouts WHERE user_id = ? AND performed_at >= ? AND performed_at < ?', [$uid, $e['starts_at'], $e['ends_at']]);
        if ($days >= (int)$e['target']) {
            q("INSERT IGNORE INTO quest_steps (user_id, quest, step, reward, done_at) VALUES (?,?,?,?,?)", [$uid, 'event', $e['id'], $e['reward'], now()]);
            q('INSERT INTO notifications (user_id, type, text, created_at) VALUES (?,?,?,?)', [$uid, 'quest', 'Event goal done: ' . $e['title'] . ' (+' . (int)$e['reward'] . ' pts)', now()]);
            $out[] = ['quest' => $e['title'], 'task' => 'Event goal reached', 'reward' => (int)$e['reward']];
        }
    }
    return $out;
}

function event_label(array $e): string
{
    return $e['kind'] === 'goal'
        ? 'Train on ' . (int)$e['target'] . ' different days → +' . number_format((int)$e['reward']) . ' pts'
        : trim(((int)$e['pct'] ? '+' . (int)$e['pct'] . '% bonus points on every workout' : '') . ((int)($e['game_coin_pct'] ?? 0) ? ' · +' . (int)$e['game_coin_pct'] . '% coins' : '') . ((int)($e['game_dmg_pct'] ?? 0) ? ' · +' . (int)$e['game_dmg_pct'] . '% boss damage' : ''), ' ·');
}

/** Compact card for the feed: weekly goal progress and any live event. */
function week_card(array $u): void
{
    $uid = (int)$u['id'];
    $mon = bonus_week_start();
    $target = weekly_goal($uid);
    $done = week_days_done($uid, $mon);
    $streak = weekly_streak($uid, $mon);
    $got = (int)val("SELECT COUNT(*) FROM quest_steps WHERE user_id = ? AND quest = 'weekly' AND step = ?", [$uid, bonus_week_key($mon)]) > 0;
    $bonus = weekly_bonus_for($target, $streak);
    $pct = (int)min(100, round($done / max(1, $target) * 100));
    $live = event_rows('starts_at <= ? AND ends_at > ?', [now(), now()]);
    ?>
<section class="card weekcard">
  <div class="rowline"><b>🎯 Weekly goal</b> <a class="small" href="<?= e(url('goals')) ?>">Details</a></div>
  <div class="xpbar"><i style="width:<?= $pct ?>%"></i></div>
  <div class="xpnote"><?= min($done, $target) ?>/<?= $target ?> training days this week · <?= $got ? 'bonus collected ✔' : '+' . number_format($bonus) . ' pts when you get there' ?><?= $streak > 0 ? ' · 🔥 ' . $streak . ' week' . ($streak > 1 ? 's' : '') . ' in a row' : '' ?></div>
  <?php foreach ($live as $e): ?>
    <p class="small" style="margin:.6rem 0 0">⚡ <b><?= e($e['title']) ?></b> – <?= e(event_label($e)) ?> <span class="muted">(until <?= e(date('M j H:i', strtotime($e['ends_at']))) ?>)</span></p>
  <?php endforeach; ?>
</section>
<?php
}

/* ---------------- seasons: a calendar of events that creates itself all year ---------------- */

/** Easter Sunday (Gregorian), as a Y-m-d string. Works without the PHP calendar extension. */
function season_easter(int $y): string
{
    $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100; $d = intdiv($b, 4); $e = $b % 4;
    $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $mo = intdiv($h + $l - 7 * $m + 114, 31); $da = ($h + $l - 7 * $m + 114) % 31 + 1;
    return sprintf('%04d-%02d-%02d', $y, $mo, $da);
}

/**
 * Seasonal plan for a year: key => [icon, title, description, start ts, end ts, points %, coins %, boss damage %].
 * Kept modest on purpose: the point is a reason to come back, not to break the economy.
 */
function seasons_plan(int $y): array
{
    $t = fn(string $d) => (int)strtotime($d . ' 00:00');
    $easter = $t(season_easter($y));
    $thanks = (int)strtotime('fourth thursday of november ' . $y . ' 00:00');
    $plan = [
        'newyear'   => ['🎆', 'New Year Kickoff', 'Two weeks to start the year strong: bonus points and extra coins.', $t("$y-01-01"), $t("$y-01-15"), 20, 30, 20],
        'spring'    => ['🌱', 'Spring Awakening', 'The days get longer. A week of extra points and coins as spring starts.', $t("$y-03-20"), $t("$y-03-27"), 15, 20, 10],
        'easter'    => ['🐣', 'Easter Egg Hunt', 'Good Friday to Easter Monday: a long weekend of bonus points, coins and boss damage.', $easter - 2 * 86400, $easter + 2 * 86400, 15, 25, 15],
        'summer'    => ['☀️', 'Summer Holiday Boost', 'Holiday season around the world: a small boost to points and coins all summer.', $t("$y-06-15"), $t("$y-09-01"), 10, 10, 0],
        'backgym'   => ['🏋️', 'Back to the Gym', 'Holidays over, routines back. Two weeks of extra points and coins.', $t("$y-09-01"), $t("$y-09-15"), 15, 20, 10],
        'halloween' => ['🎃', 'Halloween Boss Bash', 'Spooky week: the bosses are out and your hits land harder.', $t("$y-10-25"), $t("$y-11-02"), 0, 25, 40],
        'gainsfri'  => ['🔥', 'Gains Friday', 'Friday to Monday after Thanksgiving: burn it off with big bonuses.', $thanks + 86400, $thanks + 5 * 86400, 25, 40, 20],
        'xmas'      => ['🎄', 'Christmas Games', 'A week of festive training: bonus points, extra coins and stronger hits on the boss. The advent calendar runs all December.', $t("$y-12-20"), $t("$y-12-27"), 25, 50, 25],
    ];
    // First full weekend of every month is a Boss Weekend.
    for ($m = 1; $m <= 12; $m++) {
        $sat = (int)strtotime('first saturday of ' . date('F', mktime(0, 0, 0, $m, 1, $y)) . ' ' . $y . ' 00:00');
        $plan['boss' . sprintf('%02d', $m)] = ['⚔️', 'Boss Weekend', 'Saturday and Sunday: coins and boss damage boosted.', $sat, $sat + 2 * 86400, 0, 25, 25];
    }
    return $plan;
}

function seasons_done(): array
{
    $j = json_decode((string)setting('seasons_done', '[]'), true);
    return is_array($j) ? $j : [];
}

function seasons_insert(string $key, array $s): void
{
    q('INSERT INTO events (title, description, kind, pct, target, reward, starts_at, ends_at, created_at, game_coin_pct, game_dmg_pct) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
      [$s[1], $s[2], 'boost', $s[5], 0, 0, date('Y-m-d H:i:s', $s[3]), date('Y-m-d H:i:s', $s[4]), now(), $s[6], $s[7]]);
    $done = seasons_done();
    $done[] = $key;
    set_setting('seasons_done', json_encode(array_values(array_unique($done))));
}

/** Creates this year's and next year's seasons as real events, once each. A deleted season is not recreated. Runs at most once a day. */
function seasons_sync(bool $force = false): int
{
    if (setting('game_tables_ok9') !== '1' || setting('seasons_auto', '1') === '0') {
        return 0;
    }
    if (!$force && setting('seasons_day', '') === date('Y-m-d')) {
        return 0;
    }
    events_ensure();
    set_setting('seasons_day', date('Y-m-d'));
    $done = seasons_done();
    $n = 0;
    foreach ([(int)date('Y'), (int)date('Y') + 1] as $y) {
        foreach (seasons_plan($y) as $k => $s) {
            if ($s[4] <= time() || in_array($y . ':' . $k, $done, true)) {
                continue;
            }
            seasons_insert($y . ':' . $k, $s);
            $n++;
        }
    }
    if ($n) {
        rules_mark();
    }
    return $n;
}

/** Upcoming seasons for display: [[icon, title, start ts, end ts, label]], soonest first, next ~14 months. */
function seasons_upcoming(): array
{
    $out = [];
    foreach ([(int)date('Y'), (int)date('Y') + 1] as $y) {
        foreach (seasons_plan($y) as $k => $s) {
            if ($s[4] > time() && $s[3] < time() + 400 * 86400) {
                $lab = [];
                if ($s[5]) { $lab[] = '+' . $s[5] . '% points'; }
                if ($s[6]) { $lab[] = '+' . $s[6] . '% coins'; }
                if ($s[7]) { $lab[] = '+' . $s[7] . '% boss damage'; }
                $out[] = ['key' => $y . ':' . $k, 'icon' => $s[0], 'title' => $s[1], 'desc' => $s[2], 'start' => $s[3], 'end' => $s[4], 'label' => implode(' · ', $lab)];
            }
        }
    }
    usort($out, fn($a, $b) => $a['start'] <=> $b['start']);
    return $out;
}
