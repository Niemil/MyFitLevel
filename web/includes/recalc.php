<?php
declare(strict_types=1);

/*
 * Self-correcting scoring.
 *
 * Everything a member earns (set points, PR bonuses, workout totals, quest XP, total XP, level, badges)
 * can be re-derived from the raw data they logged (exercise, weight, reps, time, distance). So whenever the
 * rules change (formula, coefficients, level curve, quests, badges) the whole site is recalculated and the
 * stored numbers follow suit, and members who logged earlier never keep outdated points.
 *
 * How it triggers: a "fingerprint" of all rules is stored in settings.recalc_target. When it differs from
 * settings.recalc_done, every page request does a small time-boxed slice of recalculation until it matches.
 * The fingerprint is refreshed after each app update, after admin edits and when the admin changes the level speed.
 */

const RULES_VERSION = 5;   // bump when you change scoring CODE that the fingerprint can't see (e.g. the formula shape)

function rules_fingerprint(): string
{
    $parts = [
        RULES_VERSION, POINTS_SCALE, PR_BONUS, WARMUP_FACTOR, level_scale(),
        json_encode(level_table()), events_fingerprint(), json_encode(quest_defs()), json_encode(badge_defs()),
        json_encode(rows('SELECT id, kind, coef, bw_pct, dist_coef FROM exercises ORDER BY id')),
    ];
    return md5(implode('|', array_map('strval', $parts)));
}

/** Call after anything that changes the rules. Recalculation then runs by itself. */
function rules_mark(): void
{
    set_setting('recalc_target', rules_fingerprint());
}

function recalc_pending(): bool
{
    return (string)setting('recalc_target', '') !== (string)setting('recalc_done', '');
}

function recalc_status(): array
{
    $users = (int)val('SELECT COUNT(*) FROM users');
    $cursor = (int)setting('recalc_cursor', 0);
    $left = (int)val('SELECT COUNT(*) FROM users WHERE id > ?', [$cursor]);
    return [
        'pending' => recalc_pending(),
        'users' => $users,
        'left' => recalc_pending() ? $left : 0,
        'last' => (string)setting('recalc_last', ''),
        'changed' => (string)setting('recalc_changed', ''),
    ];
}

/** Picks up any pending recalculation in a short slice per page view. */
function rules_boot(): void
{
    if (is_post() || !recalc_pending()) {
        return;
    }
    recalc_step(1.0);
}

/** Recalculates users in id order for up to $seconds. Returns true when everything is up to date. */
function recalc_step(float $seconds = 1.0): bool
{
    if (!recalc_pending()) {
        return true;
    }
    if ((int)val("SELECT GET_LOCK('mfl_recalc', 0)") !== 1) {
        return false;   // another request is already working on it
    }
    try {
        $target = (string)setting('recalc_target', '');
        // restart from the beginning when the rules changed again while a run was in progress
        if ((string)setting('recalc_cursor_for', '') !== $target) {
            set_setting('recalc_cursor', '0');
            set_setting('recalc_cursor_for', $target);
            set_setting('recalc_changed', '0');
        }
        $cursor = (int)setting('recalc_cursor', 0);
        $changed = (int)setting('recalc_changed', 0);
        $t0 = microtime(true);
        $done = true;
        foreach (rows('SELECT id FROM users WHERE id > ? ORDER BY id LIMIT 500', [$cursor]) as $u) {
            $r = recalc_user((int)$u['id'], true);
            if (!empty($r['changed'])) {
                $changed++;
            }
            $cursor = (int)$u['id'];
            if (microtime(true) - $t0 > $seconds) {
                $done = false;
                break;
            }
        }
        if ($done && (int)val('SELECT COUNT(*) FROM users WHERE id > ?', [$cursor]) > 0) {
            $done = false;
        }
        set_setting('recalc_cursor', (string)$cursor);
        set_setting('recalc_changed', (string)$changed);
        if ($done) {
            set_setting('recalc_done', $target);
            set_setting('recalc_cursor', '0');
            set_setting('recalc_last', date('Y-m-d H:i'));
        }
        return $done;
    } finally {
        q("DO RELEASE_LOCK('mfl_recalc')");
    }
}

/** Metrics that describe cumulative history; awards on these are withdrawn if the member no longer qualifies. */
function award_is_revocable(string $metric): bool
{
    return in_array($metric, ['workouts', 'prs', 'volume_kg', 'level', 'distinct_exercises', 'cardio_min', 'sets'], true)
        || strpos($metric, 'sets_') === 0;
}

/**
 * Re-derives all numbers for one member from their raw sets.
 * $notify = tell the member (once) if their XP or level changed.
 */
function recalc_user(int $uid, bool $notify = false): array
{
    $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
    if (!$u) {
        return [];
    }
    static $exMap = null;
    if ($exMap === null) {
        $exMap = [];
        foreach (rows('SELECT * FROM exercises') as $e) {
            $exMap[(int)$e['id']] = $e;
        }
    }
    $bw = (float)($u['bodyweight_kg'] ?: 75);
    $before = ['xp' => (int)$u['xp'], 'level' => (int)$u['level']];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $upSet = $pdo->prepare('UPDATE workout_sets SET score = ?, points = ?, is_pr = ? WHERE id = ?');
        $best = [];   // exercise_id => best score in earlier workouts
        foreach (rows('SELECT id, points, volume_kg, set_count FROM workouts WHERE user_id = ? ORDER BY performed_at, id', [$uid]) as $w) {
            $groups = [];
            foreach (rows('SELECT * FROM workout_sets WHERE workout_id = ? ORDER BY id', [$w['id']]) as $s) {
                $groups[(int)$s['exercise_id']][] = $s;
            }
            $total = 0;
            $volume = 0.0;
            $count = 0;
            foreach ($groups as $exId => $list) {
                $ex = $exMap[$exId] ?? null;
                if (!$ex) {   // exercise no longer exists: keep what was stored
                    foreach ($list as $s) {
                        if (!(int)$s['is_warmup']) {
                            $total += (int)$s['points'];
                            $count++;
                        }
                    }
                    continue;
                }
                $isStrength = in_array($ex['kind'], ['weight', 'bodyweight'], true);
                $prior = $best[$exId] ?? null;
                $hasPrior = $prior !== null && $prior > 0;
                $calc = [];
                $bestIdx = null;
                foreach ($list as $i => $s) {
                    $c = set_points($ex, $s, $bw);
                    if ((int)$s['is_warmup']) {
                        $c = ['points' => (int)round($c['points'] * WARMUP_FACTOR), 'score' => 0.0];
                    }
                    $calc[$i] = $c;
                    if (!(int)$s['is_warmup'] && $isStrength && ($bestIdx === null || $c['score'] > $calc[$bestIdx]['score'])) {
                        $bestIdx = $i;
                    }
                }
                foreach ($list as $i => $s) {
                    $c = $calc[$i];
                    $isPr = ($bestIdx === $i && $hasPrior && $c['score'] > (float)$prior) ? 1 : 0;
                    $pts = $c['points'] + ($isPr ? PR_BONUS : 0);
                    if ((float)$s['score'] !== (float)$c['score'] || (int)$s['points'] !== $pts || (int)$s['is_pr'] !== $isPr) {
                        $upSet->execute([$c['score'], $pts, $isPr, $s['id']]);
                    }
                    if ((int)$s['is_warmup']) {
                        $total += $pts;   // warm-ups earn half points but are not counted as working sets or volume
                        continue;
                    }
                    $total += $pts;
                    $count++;
                    if ($s['reps'] && $s['weight_kg']) {
                        $volume += (float)$s['weight_kg'] * (int)$s['reps'];
                    } elseif ($s['reps'] && $ex['kind'] === 'bodyweight') {
                        $volume += ($bw * (float)$ex['bw_pct']) * (int)$s['reps'];
                    }
                }
                if ($isStrength && $bestIdx !== null) {
                    $best[$exId] = max((float)($best[$exId] ?? 0), (float)$calc[$bestIdx]['score']);
                }
            }
            if ((int)$w['points'] !== $total || (int)$w['set_count'] !== $count || abs((float)$w['volume_kg'] - round($volume, 1)) > 0.05) {
                q('UPDATE workouts SET points = ?, volume_kg = ?, set_count = ? WHERE id = ?', [$total, round($volume, 1), $count, $w['id']]);
            }
        }

        // quests (their XP is part of the total), then XP + level, then badges (some depend on level)
        $stats = user_stats($uid);
        $have = [];
        foreach (rows('SELECT code, reward FROM user_quests WHERE user_id = ?', [$uid]) as $r) {
            $have[$r['code']] = (int)$r['reward'];
        }
        foreach (quest_defs() as $code => [$name, $desc, $metric, $target, $reward]) {
            $met = metric_value($stats, $metric) >= $target;
            if (isset($have[$code])) {
                if (!$met && award_is_revocable($metric)) {
                    q('DELETE FROM user_quests WHERE user_id = ? AND code = ?', [$uid, $code]);
                } elseif ($have[$code] !== $reward) {
                    q('UPDATE user_quests SET reward = ? WHERE user_id = ? AND code = ?', [$reward, $uid, $code]);
                }
            } elseif ($met) {
                q('INSERT IGNORE INTO user_quests (user_id, code, reward, completed_at) VALUES (?,?,?,?)', [$uid, $code, $reward, now()]);
            }
        }
        $res = recalc_xp($uid);
        $stats['level'] = $res['level'];

        $haveB = array_column(rows('SELECT code FROM user_badges WHERE user_id = ?', [$uid]), 'code');
        foreach (badge_defs() as $code => [$name, $desc, $icon, $metric, $target]) {
            $met = metric_value($stats, $metric) >= $target;
            if (in_array($code, $haveB, true)) {
                if (!$met && award_is_revocable($metric)) {
                    q('DELETE FROM user_badges WHERE user_id = ? AND code = ?', [$uid, $code]);
                }
            } elseif ($met) {
                q('INSERT IGNORE INTO user_badges (user_id, code, earned_at) VALUES (?,?,?)', [$uid, $code, now()]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $after = ['xp' => $res['xp'], 'level' => $res['level']];
    $changed = $after !== $before;
    if ($changed && $notify && $before['xp'] > 0) {
        q('INSERT INTO notifications (user_id, type, text, created_at) VALUES (?,?,?,?)',
          [$uid, 'level', 'We fine-tuned how points are calculated, so your totals were updated: ' . number_format($after['xp']) . ' points, level ' . $after['level'] . '.', now()]);
    }
    return ['changed' => $changed, 'before' => $before, 'after' => $after];
}
