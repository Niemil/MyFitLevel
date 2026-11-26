<?php
declare(strict_types=1);

/**
 * Staff tools: plausibility limits and flags for logged sets, plus admin tools to give or take coins, XP and items.
 * Tables/columns are created here with IF NOT EXISTS so nothing has to be run by hand.
 */

function staff_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (setting('staff_v') === '3') {
        staff_once();
        return;
    }
    q('CREATE TABLE IF NOT EXISTS workout_flags (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        workout_id INT UNSIGNED NOT NULL,
        exercise VARCHAR(120) NOT NULL,
        detail VARCHAR(200) NOT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_wf_time (created_at),
        KEY idx_wf_workout (workout_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    q('CREATE TABLE IF NOT EXISTS admin_log (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        action VARCHAR(30) NOT NULL,
        amount INT NOT NULL DEFAULT 0,
        note VARCHAR(200) NOT NULL DEFAULT "",
        created_at DATETIME NOT NULL,
        KEY idx_al_time (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    q('CREATE TABLE IF NOT EXISTS daily_visits (
        user_id INT UNSIGNED NOT NULL, day DATE NOT NULL, PRIMARY KEY (user_id, day)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    q('CREATE TABLE IF NOT EXISTS char_look (
        user_id INT UNSIGNED NOT NULL PRIMARY KEY, species VARCHAR(10) NOT NULL DEFAULT "wolf", eye VARCHAR(10) NOT NULL DEFAULT "ice",
        picks INT NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    if (!row("SHOW COLUMNS FROM users LIKE 'xp_adjust'")) {
        q('ALTER TABLE users ADD COLUMN xp_adjust INT NOT NULL DEFAULT 0');
    }
    set_setting('staff_v', '3');
    staff_once();
}

/** One-time balance changes, applied automatically. */
function staff_once(): void
{
    if (setting('bal_v2') !== '1') {
        set_setting('bal_v2', '1');
        set_setting('level_scale', '1');             // the new curve is set directly in level_anchors()
        set_setting('game_steps_per_point', '80');   // 3,000 steps is about one solid set, 10,000 about 125 points
        // Members who already finished an achievement get its item too
        game_ensure();
        $cat = game_items();
        foreach (quest_item_rewards() as $code => $item) {
            if (isset($cat[$item])) {
                q("INSERT IGNORE INTO game_items (user_id, item, source, got_at, equipped) SELECT user_id, ?, 'quest', ?, 0 FROM user_quests WHERE code = ?", [$item, now(), $code]);
            }
        }
        rules_mark();
    }
    if (setting('bal_v1') !== '1') {
        set_setting('bal_v1', '1');
        set_setting('level_scale', '0.5');   // levels came too slowly: level 50 needed 210,000 points
        rules_mark();   // everyone's levels are recalculated by themselves
    }
}

/**
 * Most weight (kg, both hands counted for dumbbells) a single set could plausibly use. Roughly the world records plus a margin.
 * Anything above this is refused when logging.
 */
function plausible_limit(array $ex): float
{
    $n = strtolower((string)($ex['name'] ?? ''));
    $has = static function (array $w) use ($n): bool {
        foreach ($w as $x) {
            if (strpos($n, $x) !== false) {
                return true;
            }
        }
        return false;
    };
    if ($has(['leg press', 'hack squat', 'belt squat', 'calf'])) {
        return 1800;
    }
    if ($has(['deadlift', 'rack pull', 'farmer', 'carry', 'shrug'])) {
        return 550;
    }
    if ($has(['squat', 'hip thrust', 'good morning'])) {
        return 550;
    }
    if ($has(['bench', 'chest press', 'dip'])) {
        return 400;
    }
    if ($has(['curl', 'extension', 'raise', 'fly', 'flye', 'kickback', 'pushdown', 'pressdown', 'face pull', 'wrist', 'crunch'])) {
        return 160;
    }
    if ($has(['press', 'row', 'pull', 'chin', 'clean', 'snatch', 'jerk', 'lunge', 'step-up'])) {
        return 320;
    }
    return 600;
}

/** Plausibility check for one set. Returns [reject message|null, flag detail|null]. */
function staff_check_set(array $ex, float $loadKg, int $reps): array
{
    $lim = plausible_limit($ex);
    if ($loadKg > $lim) {
        return ['That weight on ' . $ex['name'] . ' looks like a typo (more than any person has lifted). Check the number and try again.', null];
    }
    if ($reps > 300) {
        return ['That many reps on ' . $ex['name'] . ' looks like a typo. Check the number and try again.', null];
    }
    if ($loadKg > $lim * 0.7) {
        return [null, round($loadKg, 1) . ' kg x ' . $reps . ' is above 70% of the plausible limit (' . (int)$lim . ' kg)'];
    }
    return [null, null];
}

function staff_flag(int $uid, int $wid, string $exercise, string $detail): void
{
    q('INSERT INTO workout_flags (user_id, workout_id, exercise, detail, created_at) VALUES (?,?,?,?,?)',
      [$uid, $wid, mb_substr($exercise, 0, 120), mb_substr($detail, 0, 200), now()]);
}

function staff_log(int $adminId, int $uid, string $action, int $amount, string $note): void
{
    q('INSERT INTO admin_log (admin_id, user_id, action, amount, note, created_at) VALUES (?,?,?,?,?,?)',
      [$adminId, $uid, $action, $amount, mb_substr($note, 0, 200), now()]);
}

function staff_admin_post(string $do, array $admin): bool
{
    staff_ensure();
    if ($do === 'flag_clear') {
        q('DELETE FROM workout_flags WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        return true;
    }
    if ($do !== 'staff_give') {
        return false;
    }
    $name = trim((string)($_POST['username'] ?? ''));
    $t = row('SELECT id, username FROM users WHERE username = ?', [ltrim($name, '@')]);
    if (!$t) {
        flash('No member with that username.', 'err');
        return true;
    }
    $uid = (int)$t['id'];
    $kind = (string)($_POST['kind'] ?? '');
    $amt = max(-1000000, min(1000000, (int)($_POST['amount'] ?? 0)));
    $note = trim((string)($_POST['note'] ?? ''));
    if ($kind === 'coins' && $amt !== 0) {
        game_ensure();
        game_coins_add($uid, $amt, 'admin', $note !== '' ? $note : 'From the team');
        game_notify($uid, 'gift', ($amt > 0 ? 'You received ' : 'The team removed ') . abs($amt) . ' coins' . ($note !== '' ? ': ' . $note : '.'));
    } elseif ($kind === 'xp' && $amt !== 0) {
        q('UPDATE users SET xp_adjust = xp_adjust + ? WHERE id = ?', [$amt, $uid]);
        recalc_xp($uid);
        game_notify($uid, 'gift', ($amt > 0 ? 'You received ' : 'The team removed ') . abs($amt) . ' XP' . ($note !== '' ? ': ' . $note : '.'));
    } elseif ($kind === 'item') {
        $key = (string)($_POST['item'] ?? '');
        $cat = game_items();
        if (!isset($cat[$key])) {
            flash('Unknown item.', 'err');
            return true;
        }
        game_ensure();
        game_give_item($uid, $key, 'admin');
        $amt = 0;
        $note = trim($cat[$key][0] . ($note !== '' ? ' - ' . $note : ''));
        game_notify($uid, 'gift', 'You received a gift from the team: ' . $cat[$key][0] . '.');
    } else {
        flash('Pick a type and a non-zero amount.', 'err');
        return true;
    }
    staff_log((int)$admin['id'], $uid, $kind, $amt, $note);
    flash('Done for @' . $t['username'] . '.');
    return true;
}

function staff_admin_view(): void
{
    staff_ensure();
    $flags = rows('SELECT f.*, u.username FROM workout_flags f JOIN users u ON u.id = f.user_id ORDER BY f.id DESC LIMIT 40');
    $log = rows('SELECT l.*, u.username FROM admin_log l JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 25');
    $items = game_items();
    ?>
<div class="card">
  <h3>Give or take</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="staff_give">
    <label>Username <input name="username" required autocomplete="off"></label>
    <label>Type <select name="kind"><option value="coins">Coins</option><option value="xp">XP</option><option value="item">Item (gift)</option></select></label>
    <label>Amount (negative takes away; not used for items) <input name="amount" type="number" value="0"></label>
    <label>Item (only for type Item) <select name="item"><option value="">-</option>
      <?php foreach ($items as $k => $i): ?><option value="<?= e($k) ?>"><?= e($i[0]) ?></option><?php endforeach; ?></select></label>
    <label>Message (shown to the member) <input name="note" maxlength="120"></label>
    <button class="btn" type="submit">Apply</button>
  </form>
  <p class="muted small">Everything here is logged below. XP changes are kept as an adjustment, so they survive a recalculation.</p>
</div>
<div class="card">
  <h3>Flagged sets</h3>
  <p class="muted small">Sets that were saved but look unusually heavy, or far above that member's own previous best. Nothing is shown to other members.</p>
  <?php if (!$flags): ?><p class="muted">Nothing flagged.</p><?php endif; ?>
  <?php foreach ($flags as $f): ?>
    <div class="gb-row"><span class="grow small"><a href="<?= e(url('u/' . $f['username'])) ?>">@<?= e($f['username']) ?></a> - <a href="<?= e(url('workout/' . (int)$f['workout_id'])) ?>"><?= e($f['exercise']) ?></a>: <?= e($f['detail']) ?></span>
      <span class="muted small"><?= e(rel_time((string)$f['created_at'])) ?></span>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="flag_clear"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn ghost small" type="submit">Dismiss</button></form></div>
  <?php endforeach; ?>
</div>
<div class="card">
  <h3>Recent changes</h3>
  <?php if (!$log): ?><p class="muted">No changes yet.</p><?php endif; ?>
  <?php foreach ($log as $l): ?>
    <div class="gb-row"><span class="grow small">@<?= e($l['username']) ?> - <?= e($l['action']) ?> <?= (int)$l['amount'] ? ((int)$l['amount'] > 0 ? '+' : '') . (int)$l['amount'] : '' ?> <?= e($l['note']) ?></span><span class="muted small"><?= e(rel_time((string)$l['created_at'])) ?></span></div>
  <?php endforeach; ?>
</div>
<?php
}

/** Counts a visit once per day per member (used for consistency achievements). One cheap insert, remembered in the session. */
function staff_visit(int $uid): void
{
    $d = date('Y-m-d');
    if (($_SESSION['visit_day'] ?? '') === $d) {
        return;
    }
    $_SESSION['visit_day'] = $d;
    staff_ensure();
    q('INSERT IGNORE INTO daily_visits (user_id, day) VALUES (?,?)', [$uid, $d]);
}
