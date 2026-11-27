<?php
declare(strict_types=1);

$admin = require_admin();
$aid = (int)$admin['id'];
$calibFile = __DIR__ . '/admin_calib.php';
if (is_file($calibFile)) {
    require_once $calibFile;
}
require_once __DIR__ . '/admin_stats.php';
$tab = in_array($_GET['tab'] ?? '', ['users', 'exercises', 'site', 'fito', 'email', 'feedback', 'events', 'stats', 'game', 'tools'] + (function_exists('calib_view') ? [9 => 'calib'] : []), true) ? $_GET['tab'] : 'users';
$newPass = null;
$fitoDir = APP_ROOT . '/config/fito';
if (($_GET['tab'] ?? '') === 'fito' && isset($_GET['dl'])) {
    $n = (string)$_GET['dl'];
    $f = $fitoDir . '/' . $n;
    if (in_array($n, ['digest.txt', 'urls.tsv', 'images.zip', 'calib.tsv', 'calib_blocks.txt', 'calib_images.zip'], true) && is_file($f)) {
        header('Content-Type: ' . (substr($n, -4) === '.zip' ? 'application/zip' : 'text/plain; charset=utf-8'));
        header('Content-Disposition: attachment; filename="' . $n . '"');
        header('Content-Length: ' . filesize($f));
        readfile($f);
        exit;
    }
}

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'geo_import') {
        $f = $_FILES['geo']['tmp_name'] ?? '';
        if ($f && is_uploaded_file($f)) {
            $n = stats_geo_import($f);
            flash($n > 0 ? 'Imported ' . number_format($n) . ' IP ranges.' : 'Could not read that file (expected start,end,country CSV).', $n > 0 ? 'ok' : 'err');
        }
        redirect('admin?tab=stats');
    }
    if ($do === 'fb_set') {
        ql_ensure_tables();
        $st = in_array($_POST['status'] ?? '', ['new', 'read', 'done'], true) ? (string)$_POST['status'] : 'read';
        q('UPDATE feedback SET status = ?, note = ? WHERE id = ?', [$st, clean_text((string)($_POST['note'] ?? ''), 500) ?: null, (int)($_POST['id'] ?? 0)]);
        redirect('admin?tab=feedback' . (isset($_GET['all']) ? '&all=1' : ''));
    }
    if ($do === 'fb_delete') {
        q('DELETE FROM feedback WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        redirect('admin?tab=feedback');
    }
    if ($do === 'clear_errors') {
        @unlink(APP_ROOT . '/config/errors.log');
        redirect('admin?tab=site');
    }
    if (function_exists('calib_handle') && calib_handle($do)) {
        redirect('admin?tab=calib');
    }
    if ($do === 'fito_share') {
        set_setting('fito_share', ($_POST['on'] ?? '') === '1' ? bin2hex(random_bytes(10)) : '');
        flash(($_POST['on'] ?? '') === '1' ? 'Share link created.' : 'Share link turned off.');
        redirect('admin?tab=fito');
    }
    if (staff_admin_post($do, $admin)) {
        redirect('admin?tab=tools');
    }
    if (game_admin_post($do)) {
        redirect('admin?tab=game');
    }
    if ($do === 'fito_token') {
        set_setting('fito_token', bin2hex(random_bytes(12)));
        flash('New Pi token created.');
        redirect('admin?tab=fito');
    }
    if ($do === 'mail_save') {
        $old = mail_conf();
        $new = [
            'host' => clean_text((string)($_POST['host'] ?? ''), 120),
            'port' => max(1, (int)($_POST['port'] ?? 465)),
            'secure' => in_array($_POST['secure'] ?? '', ['ssl', 'tls', 'none'], true) ? $_POST['secure'] : 'ssl',
            'user' => clean_text((string)($_POST['user'] ?? ''), 190),
            'pass' => (string)($_POST['pass'] ?? '') !== '' ? (string)$_POST['pass'] : $old['pass'],
            'from' => clean_text((string)($_POST['from'] ?? ''), 190),
            'name' => clean_text((string)($_POST['name'] ?? ''), 60),
            'verify' => empty($_POST['verify']) ? 0 : 1,
        ];
        if (mail_conf_save($new)) {
            flash('Email settings saved.');
        } else {
            flash('Could not write config/mail.json. Make the config folder writable (chmod 775 config) and try again.', 'err');
        }
        redirect('admin?tab=email');
    }
    if ($do === 'mail_test') {
        $to = (string)($admin['email'] ?? '');
        if ($to === '') {
            flash('Add an email address to your own account first (Settings).', 'err');
        } else {
            [$ok, $err] = send_mail($to, 'Test mail', mail_html('It works!', '<p>This is a test mail from your site.</p>'), 'Test mail');
            flash($ok ? 'Test mail sent to ' . $to . '.' : 'Failed: ' . $err, $ok ? 'ok' : 'err');
        }
        redirect('admin?tab=email');
    }
    if ($do === 'cron_token') {
        set_setting('cron_token', bin2hex(random_bytes(12)));
        flash('New cron token created.');
        redirect('admin?tab=email');
    }
    $uidT = (int)($_POST['user_id'] ?? 0);

    if (in_array($do, ['ban', 'unban', 'make_admin', 'remove_admin', 'make_mod', 'remove_mod', 'reset_pw', 'recalc', 'delete_user'], true) && $uidT) {
        $t = row('SELECT * FROM users WHERE id = ?', [$uidT]);
        if ($t && ($uidT !== $aid || $do === 'recalc')) {
            switch ($do) {
                case 'ban': $err = ban_user($admin, $uidT, null, 'Suspended by admin'); flash($err ?: 'User suspended.', $err ? 'err' : 'ok'); break;
                case 'unban': q('UPDATE users SET is_banned = 0, banned_until = NULL, ban_reason = NULL WHERE id = ?', [$uidT]); mod_log($aid, 'unsuspend', $uidT); flash('User restored.'); break;
                case 'make_mod': q('UPDATE users SET is_mod = 1 WHERE id = ?', [$uidT]); mod_log($aid, 'make mod', $uidT); flash('Moderator rights granted.'); break;
                case 'remove_mod': q('UPDATE users SET is_mod = 0 WHERE id = ?', [$uidT]); mod_log($aid, 'remove mod', $uidT); flash('Moderator rights removed.'); break;
                case 'make_admin': q('UPDATE users SET is_admin = 1 WHERE id = ?', [$uidT]); flash('Admin rights granted.'); break;
                case 'remove_admin': q('UPDATE users SET is_admin = 0 WHERE id = ?', [$uidT]); flash('Admin rights removed.'); break;
                case 'delete_user':
                    if (!empty($t['is_admin'])) {
                        flash('Remove admin rights first, then delete.', 'err');
                        break;
                    }
                    try {
                        q('DELETE FROM users WHERE id = ?', [$uidT]);
                        flash('Deleted ' . $t['username'] . ' and all their data.');
                    } catch (Throwable $e) {
                        error_log('admin delete user: ' . $e->getMessage());
                        flash('Could not delete that user. Suspend them instead.', 'err');
                    }
                    break;
                case 'recalc': recalc_user($uidT); flash('Recalculated everything for this user.'); break;
                case 'reset_pw':
                    $newPass = bin2hex(random_bytes(5));
                    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPass, PASSWORD_DEFAULT), $uidT]);
                    flash('Temporary password for ' . $t['username'] . ': ' . $newPass . ' (tell them to change it).', 'win');
                    break;
            }
        }
        redirect('admin?tab=users');
    }

    if ($do === 'exercise_save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = clean_text((string)($_POST['name'] ?? ''), 100);
        $muscle = clean_text((string)($_POST['muscle'] ?? ''), 20);
        $equip = clean_text((string)($_POST['equipment'] ?? ''), 30);
        $kind = in_array($_POST['kind'] ?? '', ['weight', 'bodyweight', 'cardio', 'duration'], true) ? $_POST['kind'] : 'weight';
        $coef = max(0.01, min(20, (float)str_replace(',', '.', (string)($_POST['coef'] ?? '1'))));
        $bw = max(0, min(1, (float)str_replace(',', '.', (string)($_POST['bw_pct'] ?? '0'))));
        $dist = max(0, min(100, (float)str_replace(',', '.', (string)($_POST['dist_coef'] ?? '0'))));
        $active = isset($_POST['active']) ? 1 : 0;
        if ($name === '' || $muscle === '') {
            flash('Name and muscle group are required.', 'err');
        } elseif ($id) {
            $old = row('SELECT kind, coef, bw_pct, dist_coef FROM exercises WHERE id = ?', [$id]);
            $scoring = !$old || $old['kind'] !== $kind || (float)$old['coef'] !== $coef || (float)$old['bw_pct'] !== $bw || (float)$old['dist_coef'] !== $dist;
            if ($scoring) {
                q('UPDATE exercises SET locked = 1 WHERE id = ?', [$id]);   // keep my edit when built-in data updates
            }
            q('UPDATE exercises SET name=?, muscle=?, equipment=?, kind=?, coef=?, bw_pct=?, dist_coef=?, active=? WHERE id=?',
              [$name, $muscle, $equip, $kind, $coef, $bw, $dist, $active, $id]);
            rules_mark();
            flash($scoring ? 'Exercise updated. Everyone\'s points are being recalculated automatically.' : 'Exercise updated.');
        } else {
            $slug = slugify($name);
            if (row('SELECT id FROM exercises WHERE slug = ?', [$slug])) {
                flash('An exercise with that name already exists.', 'err');
            } else {
                q('INSERT INTO exercises (slug, name, muscle, equipment, kind, coef, bw_pct, dist_coef, active) VALUES (?,?,?,?,?,?,?,?,1)',
                  [$slug, $name, $muscle, $equip, $kind, $coef, $bw, $dist]);
                flash('Exercise added.');
            }
        }
        redirect('admin?tab=exercises');
    }

    if ($do === 'ev_add' || $do === 'ev_preset') {
        events_ensure();
        $title = trim(clean_text((string)($_POST['title'] ?? ''), 80));
        $desc = trim(clean_text((string)($_POST['description'] ?? ''), 400));
        $kind = ($_POST['kind'] ?? '') === 'goal' ? 'goal' : 'boost';
        game_ensure();
        $pct = max(0, min(500, (int)($_POST['pct'] ?? 0)));
        $gc = max(0, min(300, (int)($_POST['game_coin_pct'] ?? 0)));
        $gd = max(0, min(300, (int)($_POST['game_dmg_pct'] ?? 0)));
        $target = max(0, min(31, (int)($_POST['target'] ?? 0)));
        $reward = max(0, min(100000, (int)($_POST['reward'] ?? 0)));
        $s = strtotime((string)($_POST['starts_at'] ?? ''));
        $e2 = strtotime((string)($_POST['ends_at'] ?? ''));
        if ($do === 'ev_preset') {
            $p = (string)($_POST['preset'] ?? '');
            $sat = strtotime('saturday this week 00:00');
            if ($sat + 2 * 86400 <= time()) {
                $sat = strtotime('+7 days', $sat);
            }
            if ($p === 'double') { [$title, $desc, $kind, $pct, $target, $reward, $s, $e2] = ['Double points weekend', 'Every workout from Saturday to Sunday night pays double.', 'boost', 100, 0, 0, $sat, $sat + 2 * 86400]; }
            elseif ($p === 'bonusweek') { [$title, $desc, $kind, $pct, $target, $reward, $s, $e2] = ['Bonus week', 'A whole week of +50% points.', 'boost', 50, 0, 0, strtotime('today 00:00'), strtotime('+7 days 00:00')]; }
            elseif (in_array($p, ['xmas', 'newyear', 'bossweekend'], true)) {
                $yr = (int)date('Y');
                if ($p === 'xmas') {
                    $st = strtotime($yr . '-12-20 00:00');
                    if ($st + 7 * 86400 <= time()) { $st = strtotime(($yr + 1) . '-12-20 00:00'); }
                    [$title, $desc, $kind, $pct, $gc, $gd, $s, $e2] = ['Christmas Games', 'A week of festive training: bonus points, extra coins and stronger hits on the boss.', 'boost', 25, 50, 25, $st, $st + 7 * 86400];
                } elseif ($p === 'newyear') {
                    $st = strtotime($yr . '-01-01 00:00');
                    if ($st + 14 * 86400 <= time()) { $st = strtotime(($yr + 1) . '-01-01 00:00'); }
                    [$title, $desc, $kind, $pct, $gc, $gd, $s, $e2] = ['New Year Kickoff', 'Two weeks to start the year strong: bonus points and extra coins.', 'boost', 20, 30, 20, $st, $st + 14 * 86400];
                } else {
                    [$title, $desc, $kind, $pct, $gc, $gd, $s, $e2] = ['Boss Weekend', 'Saturday and Sunday: coins and boss damage boosted.', 'boost', 0, 50, 50, $sat, $sat + 2 * 86400];
                }
            }
            elseif ($p === 'sprint') { [$title, $desc, $kind, $pct, $target, $reward, $s, $e2] = ['Four-day sprint', 'Train on 4 different days this week for a big bonus.', 'goal', 0, 4, 1500, strtotime('monday this week 00:00'), strtotime('monday next week 00:00')]; }
        }
        if ($title === '' || !$s || !$e2 || $e2 <= $s || ($kind === 'boost' && $pct + $gc + $gd < 1) || ($kind === 'goal' && ($target < 1 || $reward < 1))) {
            flash('Fill in a title, valid dates and either a percent (bonus) or days + reward (goal).', 'err');
        } else {
            q('INSERT INTO events (title, description, kind, pct, target, reward, starts_at, ends_at, created_at, game_coin_pct, game_dmg_pct) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
              [$title, $desc, $kind, $kind === 'boost' ? $pct : 0, $kind === 'goal' ? $target : 0, $kind === 'goal' ? $reward : 0, date('Y-m-d H:i:s', $s), date('Y-m-d H:i:s', $e2), now(), $kind === 'boost' ? $gc : 0, $kind === 'boost' ? $gd : 0]);
            rules_mark();
            flash('Event created. Points are recalculated in the background.');
        }
        redirect('admin?tab=events');
    }
    if ($do === 'ev_season') {
        events_ensure();
        game_ensure();
        $key = (string)($_POST['key'] ?? '');
        $made = false;
        foreach (seasons_upcoming() as $su) {
            if ($su['key'] === $key || preg_replace('/^\d+:/', '', $su['key']) === $key) {
                [$yy, $kk] = explode(':', $su['key']);
                $plan = seasons_plan((int)$yy)[$kk] ?? null;
                if ($plan) {
                    seasons_insert($su['key'], $plan);
                    rules_mark();
                    flash($plan[1] . ' created.');
                    $made = true;
                }
                break;
            }
        }
        if (!$made) { flash('That season is not available.', 'err'); }
        redirect('admin?tab=events');
    }
    if ($do === 'ev_seasons') {
        set_setting('seasons_auto', ($_POST['auto'] ?? '') === '1' ? '1' : '0');
        if (($_POST['sync'] ?? '') !== '') {
            $n = seasons_sync(true);
            flash($n ? $n . ' season' . ($n === 1 ? '' : 's') . ' added to the calendar.' : 'Calendar is already up to date.');
        } else {
            flash('Saved.');
        }
        redirect('admin?tab=events');
    }
    if ($do === 'ev_del') {
        events_ensure();
        q('DELETE FROM events WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        rules_mark();
        flash('Event removed.');
        redirect('admin?tab=events');
    }

    if ($do === 'recalc_all') {
        rules_mark();
        flash('Recalculation started. It runs in the background while pages load.');
        redirect('admin?tab=site');
    }

    if ($do === 'site_save') {
        $sn = clean_text((string)($_POST['site_name'] ?? ''), 40) ?: APP_NAME_DEFAULT;
        set_setting('site_name', $sn);
        set_setting('registration', isset($_POST['registration']) ? '1' : '0');
        set_setting('tagline', clean_text((string)($_POST['tagline'] ?? ''), 90) ?: 'Free workout tracker with points, levels and quests');
        set_setting('beta_banner', isset($_POST['beta_banner']) ? '1' : '0');
        $ds = (string)($_POST['default_skin'] ?? 'auto');
        set_setting('default_skin', isset(skin_list()[$ds]) ? $ds : 'auto');
        $ls = max(0.25, min(10, (float)str_replace(',', '.', (string)($_POST['level_scale'] ?? '1'))));
        set_setting('level_scale', (string)$ls);
        rules_mark();
        flash('Site settings saved.');
        redirect('admin?tab=site');
    }
}

$totalUsers = (int)val('SELECT COUNT(*) FROM users');
$totalW = (int)val('SELECT COUNT(*) FROM workouts');
$new7 = (int)val('SELECT COUNT(*) FROM users WHERE created_at >= ?', [date('Y-m-d H:i:s', time() - 7 * 86400)]);
$active7 = (int)val('SELECT COUNT(DISTINCT user_id) FROM workouts WHERE performed_at >= ?', [date('Y-m-d H:i:s', time() - 7 * 86400)]);

define('GAME_CSS', true);
layout_header('Admin');
?>
<h1 class="pagetitle">Admin</h1>
<div class="statgrid card">
  <div><b><?= $totalUsers ?></b><span>members</span></div>
  <div><b><?= $new7 ?></b><span>new this week</span></div>
  <div><b><?= $active7 ?></b><span>active this week</span></div>
  <div><b><?= $totalW ?></b><span>workouts</span></div>
</div>
<?php ql_ensure_tables(); $fbNew = (int)val("SELECT COUNT(*) FROM feedback WHERE status = 'new'");
$nav = [
    'People' => [['users', 'Members', 'admin?tab=users'], ['feedback', 'Feedback' . ($fbNew ? ' (' . $fbNew . ')' : ''), 'admin?tab=feedback'], ['email', 'Email', 'admin?tab=email']],
    'Content' => [['exercises', 'Exercises', 'admin?tab=exercises'], ['events', 'Events', 'admin?tab=events'], ['', 'Blog', 'blog/admin'], ['game', 'Game', 'admin?tab=game'], ['fito', 'Fitocracy data', 'admin?tab=fito']],
    'Insights' => [['stats', 'Traffic and visitors', 'admin?tab=stats&sub=traffic'], ['', 'Mentions on the web', 'admin/mentions'], ['', 'Site log', 'admin/logs']],
    'Site' => array_merge([['site', 'Settings', 'admin?tab=site'], ['tools', 'Tools', 'admin?tab=tools']], function_exists('calib_view') ? [['calib', 'Calibration', 'admin?tab=calib']] : []),
]; ?>
<nav class="adminnav" aria-label="Admin">
  <?php foreach ($nav as $grp => $items): ?>
  <div><h4><?= e($grp) ?></h4>
    <?php foreach ($items as [$t, $lbl, $href]): ?><a class="<?= $t !== '' && $tab === $t ? 'on' : '' ?>" href="<?= e(url($href)) ?>"><?= e($lbl) ?></a><?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'users'):
    $list = rows('SELECT * FROM users ORDER BY id DESC LIMIT 200'); ?>
<div class="card tablewrap">
<table class="tbl">
  <tr><th>User</th><th>Lvl</th><th>pts</th><th>Last seen</th><th></th></tr>
  <?php foreach ($list as $r): ?>
  <tr class="<?= $r['is_banned'] ? 'banned' : '' ?>">
    <td><a href="<?= e(url('u/' . $r['username'])) ?>"><?= e($r['username']) ?></a>
      <?= $r['is_admin'] ? '<span class="pr">admin</span>' : '' ?><?= $r['is_mod'] ? '<span class="pr">mod</span>' : '' ?><?= $r['is_banned'] ? '<span class="pr bad">banned</span>' : '' ?>
      <div class="muted small"><?= e($r['email'] ?? '') ?></div></td>
    <td><?= (int)$r['level'] ?></td>
    <td><?= number_format((int)$r['xp']) ?></td>
    <td class="small"><?= e($r['last_seen'] ? rel_time($r['last_seen']) : '–') ?></td>
    <td>
      <form method="post" class="inl admact">
        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
        <?php if ((int)$r['id'] !== $aid): ?>
          <button class="btn small ghost" name="do" value="<?= $r['is_banned'] ? 'unban' : 'ban' ?>"><?= $r['is_banned'] ? 'Unban' : 'Ban' ?></button>
          <button class="btn small ghost" name="do" value="<?= $r['is_mod'] ? 'remove_mod' : 'make_mod' ?>"><?= $r['is_mod'] ? '− mod' : '+ mod' ?></button>
          <button class="btn small ghost" name="do" value="<?= $r['is_admin'] ? 'remove_admin' : 'make_admin' ?>"><?= $r['is_admin'] ? '− admin' : '+ admin' ?></button>
          <button class="btn small ghost" name="do" value="reset_pw">Reset pw</button>
          <?php if ((int)$r['id'] !== $aid && empty($r['is_admin'])): ?><button class="btn small ghost" style="color:var(--bad)" name="do" value="delete_user" data-confirm="Delete <?= e($r['username']) ?> and ALL their workouts, posts and data? This cannot be undone.">Delete</button><?php endif; ?>
        <?php endif; ?>
        <button class="btn small ghost" name="do" value="recalc">Recalc points</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</div>

<?php elseif ($tab === 'exercises'):
    $list = rows('SELECT * FROM exercises ORDER BY muscle, name');
    $muscles = ['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'core', 'fullbody', 'cardio']; ?>
<div class="card">
  <h4>Add exercise</h4>
  <form method="post" class="exform">
    <?= csrf_field() ?><input type="hidden" name="do" value="exercise_save"><input type="hidden" name="id" value="0">
    <input name="name" placeholder="Name" required>
    <select name="muscle"><?php foreach ($muscles as $m) echo '<option>' . e($m) . '</option>'; ?></select>
    <input name="equipment" placeholder="Equipment">
    <select name="kind"><option>weight</option><option>bodyweight</option><option>cardio</option><option>duration</option></select>
    <input name="coef" value="1.0" title="Points coefficient (per minute for cardio/duration)">
    <input name="bw_pct" value="0" title="Bodyweight fraction (bodyweight kind)">
    <input name="dist_coef" value="0" title="Points per km (cardio)">
    <button class="btn small" type="submit">Add</button>
  </form>
  <p class="muted small">Points: <code>coef × e1RM<sup>1.3</sup> × <?= POINTS_SCALE ?></code> (e1RM = load × (1 + reps/30); dumbbells counted ×2). Cardio: <code>dist_coef × 6.6 × km<sup>1.2</sup> × (pace ratio)<sup>1.3</sup></code>, or <code>coef × 6.6 × minutes</code> when only time is logged. Edit a row and press Save.</p>
</div>
<div class="card tablewrap">
<table class="tbl">
  <tr><th>Name</th><th>Muscle</th><th>Kind</th><th>Coef</th><th>BW%</th><th>/km</th><th>On</th><th></th></tr>
  <?php foreach ($list as $r): $fid = 'ef' . (int)$r['id']; ?>
  <tr>
    <td><input form="<?= $fid ?>" name="name" value="<?= e($r['name']) ?>"></td>
    <td><select form="<?= $fid ?>" name="muscle"><?php foreach ($muscles as $m) echo '<option' . ($m === $r['muscle'] ? ' selected' : '') . '>' . e($m) . '</option>'; ?></select></td>
    <td><select form="<?= $fid ?>" name="kind"><?php foreach (['weight', 'bodyweight', 'cardio', 'duration'] as $k) echo '<option' . ($k === $r['kind'] ? ' selected' : '') . '>' . $k . '</option>'; ?></select></td>
    <td><input form="<?= $fid ?>" name="coef" size="5" value="<?= e(fmt_num((float)$r['coef'], 3)) ?>"></td>
    <td><input form="<?= $fid ?>" name="bw_pct" size="4" value="<?= e(fmt_num((float)$r['bw_pct'], 2)) ?>"></td>
    <td><input form="<?= $fid ?>" name="dist_coef" size="4" value="<?= e(fmt_num((float)$r['dist_coef'], 2)) ?>"></td>
    <td><input form="<?= $fid ?>" type="checkbox" name="active" value="1" <?= $r['active'] ? 'checked' : '' ?>></td>
    <td>
      <form id="<?= $fid ?>" method="post">
        <?= csrf_field() ?><input type="hidden" name="do" value="exercise_save"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="equipment" value="<?= e($r['equipment']) ?>">
        <button class="btn small ghost" type="submit">Save</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</div>

<?php elseif ($tab === 'feedback'):
  $all = isset($_GET['all']);
  $fbs = rows('SELECT f.*, us.username, us.display_name FROM feedback f LEFT JOIN users us ON us.id = f.user_id ' . ($all ? '' : "WHERE f.status <> 'done' ") . 'ORDER BY f.id DESC LIMIT 100'); ?>
<div class="card">
  <h3>Feedback <span class="muted small">(<?= $all ? 'all' : 'open' ?>)</span></h3>
  <p class="small"><a href="<?= e(url('admin?tab=feedback' . ($all ? '' : '&all=1'))) ?>"><?= $all ? 'Show only open' : 'Show finished too' ?></a> · members can send feedback at <code>/feedback</code></p>
  <?php if (!$fbs): ?><p class="muted">Nothing here. Quiet is good.</p><?php endif; ?>
  <?php foreach ($fbs as $f): ?>
  <div class="fbitem fb-<?= e($f['status']) ?>">
    <div class="small muted"><b><?= e(strtoupper($f['kind'])) ?></b> · <?= $f['username'] ? '<a href="' . e(url('u/' . $f['username'])) . '">@' . e($f['username']) . '</a>' : 'guest' . ($f['contact'] ? ' (' . e($f['contact']) . ')' : '') ?> · <?= e(date('j M H:i', strtotime($f['created_at']))) ?> · <?= e($f['status']) ?></div>
    <p><?= nl2br(e($f['message'])) ?></p>
    <?php if ($f['page'] || $f['agent']): ?><div class="small muted"><?= e((string)$f['page']) ?> · <?= e((string)$f['agent']) ?></div><?php endif; ?>
    <form method="post" class="fbform"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <input name="note" maxlength="500" placeholder="Private note for us" value="<?= e((string)$f['note']) ?>">
      <button class="btn small ghost" name="do" value="fb_set" type="submit">Save</button>
      <select name="status"><?php foreach (['new', 'read', 'done'] as $sv): ?><option<?= $f['status'] === $sv ? ' selected' : '' ?>><?= $sv ?></option><?php endforeach; ?></select>
      <button class="btn small ghost danger" name="do" value="fb_delete" type="submit">Delete</button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($tab === 'stats'): stats_admin_view(); ?>
<?php elseif ($tab === 'game'): game_admin_view(); ?>
<?php elseif ($tab === 'tools'): staff_admin_view(); ?>

<?php elseif ($tab === 'calib' && function_exists('calib_view')): calib_view(); ?>

<?php elseif ($tab === 'events'): $evs = event_rows(); ?>
<?php game_ensure(); $up = seasons_upcoming(); $auto = setting('seasons_auto', '1') !== '0'; $created = seasons_done(); ?>
<div class="card">
  <h3>Season calendar</h3>
  <p class="muted small" style="margin-top:0">Seasons create themselves all year: New Year, spring, Easter, a small summer holiday boost (+10% points and coins, 15 June to 1 September), Back to the Gym, Halloween, Gains Friday, Christmas and a Boss Weekend on the first weekend of every month. Each one appears below as a normal event you can delete. A deleted season is not created again.</p>
  <form method="post" class="evtoggle"><?= csrf_field() ?><input type="hidden" name="do" value="ev_seasons">
    <label class="chk"><input type="checkbox" name="auto" value="1" <?= $auto ? 'checked' : '' ?>> Create seasons automatically</label>
    <button class="btn small" type="submit">Save</button>
    <button class="btn small ghost" type="submit" name="sync" value="1">Save and add missing now</button>
  </form>
  <div class="evtiles">
  <?php foreach ($up as $su): $isMade = in_array($su['key'], $created, true); ?>
    <div class="evtile<?= $isMade ? ' made' : '' ?>">
      <span class="ev-ic"><?= $su['icon'] ?></span>
      <b><?= e($su['title']) ?></b>
      <small><?= e(date('M j', $su['start'])) ?> – <?= e(date('M j', $su['end'] - 1)) ?></small>
      <small class="ev-b"><?= e($su['label']) ?></small>
      <?php if ($isMade): ?><span class="muted small">On the calendar ✔</span>
      <?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="ev_season"><input type="hidden" name="key" value="<?= e($su['key']) ?>"><button class="btn small" type="submit">Add now</button></form><?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<div class="card">
  <h3>Quick events</h3>
  <div class="evtiles">
    <form method="post" class="evtile"><?= csrf_field() ?><input type="hidden" name="do" value="ev_preset"><input type="hidden" name="preset" value="double">
      <span class="ev-ic">⚡</span><b>Double points weekend</b><small>Next Sat–Sun</small><small class="ev-b">+100% points</small><button class="btn small" type="submit">Create</button></form>
    <form method="post" class="evtile"><?= csrf_field() ?><input type="hidden" name="do" value="ev_preset"><input type="hidden" name="preset" value="bonusweek">
      <span class="ev-ic">🔥</span><b>Bonus week</b><small>7 days from today</small><small class="ev-b">+50% points</small><button class="btn small" type="submit">Create</button></form>
    <form method="post" class="evtile"><?= csrf_field() ?><input type="hidden" name="do" value="ev_preset"><input type="hidden" name="preset" value="bossweekend">
      <span class="ev-ic">⚔️</span><b>Boss Weekend</b><small>Next Sat–Sun</small><small class="ev-b">+50% coins · +50% boss damage</small><button class="btn small" type="submit">Create</button></form>
    <form method="post" class="evtile"><?= csrf_field() ?><input type="hidden" name="do" value="ev_preset"><input type="hidden" name="preset" value="sprint">
      <span class="ev-ic">🎯</span><b>Four-day sprint</b><small>This week</small><small class="ev-b">Train 4 days → +1,500 pts</small><button class="btn small" type="submit">Create</button></form>
  </div>
</div>
<div class="card">
  <h3>New event</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="ev_add">
    <label>Title <input name="title" maxlength="80" required></label>
    <label>Description <input name="description" maxlength="400"></label>
    <label>Type <select name="kind"><option value="boost">Bonus points (+ percent on every workout)</option><option value="goal">Goal (train on N different days, get a reward)</option></select></label>
    <label>Bonus percent (bonus type) <input name="pct" inputmode="numeric" value="50"></label>
    <label>Extra coins percent (bonus type, game) <input name="game_coin_pct" inputmode="numeric" value="0"></label>
    <label>Extra boss damage percent (bonus type, game) <input name="game_dmg_pct" inputmode="numeric" value="0"></label>
    <label>Days to train (goal type) <input name="target" inputmode="numeric" value="4"></label>
    <label>Reward in points (goal type) <input name="reward" inputmode="numeric" value="1000"></label>
    <label>Starts <input type="datetime-local" name="starts_at" required></label>
    <label>Ends <input type="datetime-local" name="ends_at" required></label>
    <button class="btn">Create event</button>
  </form>
</div>
<div class="card tablewrap">
  <table class="tbl"><tr><th>Event</th><th>What</th><th>When</th><th></th></tr>
  <?php foreach (array_reverse($evs) as $ev): ?>
    <tr><td><?= e($ev['title']) ?></td><td><?= e(event_label($ev)) ?></td>
      <td><?= e(date('M j H:i', strtotime($ev['starts_at']))) ?> – <?= e(date('M j H:i', strtotime($ev['ends_at']))) ?></td>
      <td><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="ev_del"><input type="hidden" name="id" value="<?= (int)$ev['id'] ?>"><button class="btn small ghost">Delete</button></form></td></tr>
  <?php endforeach; if (!$evs): ?><tr><td colspan="4" class="muted">No events yet.</td></tr><?php endif; ?>
  </table>
  <p class="muted small">Members see events on the feed card and at <code>/events</code>. Bonus-percent events are derived from workouts inside the window, so edits and deletions stay correct.</p>
</div>
<?php elseif ($tab === 'email'):
    $mc = mail_conf(); $ct = (string)setting('cron_token', ''); ?>
<?php if ($mc['pass'] === ''): ?><div class="card"><b>Sending without SMTP login.</b> <span class="muted small">The server's own mail() is used, which is fine on cPanel hosts like Hostup (no password needed). A ✅ below only means the server accepted the mail. The real delivery result is in cPanel → Track Delivery. Only fill in the SMTP form if mail() does not work.</span></div><?php endif; ?>
<div class="card">
  <h3>Outgoing email (SMTP)</h3>
  <p class="muted small">The password is saved in a private file on the server (not in the database or in any download). Leave it empty to keep the current one.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="mail_save">
    <label>SMTP host <input name="host" value="<?= e($mc['host']) ?>"></label>
    <label>Port <input name="port" type="number" value="<?= (int)$mc['port'] ?>"></label>
    <label>Security <select name="secure"><?php foreach (['ssl' => 'SSL/TLS (465)', 'tls' => 'STARTTLS (587)', 'none' => 'None'] as $k => $l): ?><option value="<?= $k ?>" <?= $mc['secure'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    <label>Username <input name="user" value="<?= e($mc['user']) ?>" autocomplete="off"></label>
    <label>Password <small class="muted"><?= $mc['pass'] !== '' ? '(saved, type to replace)' : '(not set)' ?></small><input name="pass" type="password" autocomplete="new-password"></label>
    <label>From address <input name="from" value="<?= e($mc['from']) ?>"></label>
    <label>From name <input name="name" value="<?= e($mc['name']) ?>"></label>
    <label class="check"><input type="checkbox" name="verify" value="1" <?= $mc['verify'] ? 'checked' : '' ?>> Verify the server certificate</label>
    <button class="btn" type="submit">Save</button>
  </form>
  <form method="post" style="margin-top:.6rem"><?= csrf_field() ?><input type="hidden" name="do" value="mail_test"><button class="btn ghost" type="submit">Send test mail to me</button></form>
</div>
<div class="card">
  <h3>Latest sending attempts</h3>
  <?php $ml = json_decode((string)setting('mail_log', '[]'), true) ?: []; if (!$ml): ?><p class="muted small">Nothing sent yet.</p><?php endif; ?>
  <?php foreach ($ml as $m): ?><p class="small"><?= $m['ok'] ? '✅' : '❌' ?> <?= e($m['t']) ?> → <?= e($m['to']) ?> <?= $m['ok'] ? '' : '<br><span class="muted">' . e($m['err']) . '</span>' ?></p><?php endforeach; ?>
</div>
<div class="card">
  <h3>Reminder mails ("we miss you")</h3>
  <?php if ($ct === ''): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="cron_token"><button class="btn" type="submit">Create cron link</button></form>
  <?php else: ?>
    <p class="small">Add a daily cron job in cPanel with this command:</p>
    <input readonly onclick="this.select()" value="wget -q -O - <?= e(site_url('cron/' . $ct)) ?>">
    <p class="muted small">Sends at most 25 per run to members inactive 14+ days (no login, workout or post), then never more often than every 60 days.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="cron_token"><button class="btn ghost small" type="submit">New token</button></form>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'fito'):
    $tok = (string)setting('fito_token', '', true);
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : 'https://myfitlevel.com'), '/');
    $cmd = 'curl -fsSL https://myfitlevel.com/tools/fito_run.py -o fito_run.py && nohup python3 fito_run.py --token ' . $tok . ' > fito_run.log 2>&1 &';
?>
<div class="card">
  <h3>Collect Fitocracy info from your Raspberry Pi</h3>
  <?php if ($tok === ''): ?>
    <p class="muted small">Create a token first. The Pi uses it to send the results here.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="fito_token"><button class="btn" type="submit">Create token</button></form>
  <?php else: ?>
    <p class="muted small">Paste this ONE line in Pi Connect (the token is already filled in). It downloads the latest scripts, then searches, reads screenshots and uploads new finds every 10 minutes, all day if needed. Watch with <code>tail -f fito_run.log</code>. Stop with <code>pkill -f fito_run.py</code>. It is safe to start again later; finished work is remembered.</p>
    <textarea readonly rows="4" style="width:100%;font-family:monospace;font-size:12px" onclick="this.select()"><?= e($cmd) ?></textarea>
    <form method="post" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="do" value="fito_token"><button class="btn small ghost" type="submit">New token (old one stops working)</button></form>
  <?php endif; ?>
  <?php $st = is_file($fitoDir . '/status.json') ? json_decode((string)file_get_contents($fitoDir . '/status.json'), true) : null;
  if (is_array($st)): $age = time() - (int)($st['t'] ?? 0); $done = ($st['phase'] ?? '') === 'DONE'; $tot = (int)($st['total'] ?? 0); $pct = $tot ? (int)round(100 * (int)$st['done'] / $tot) : 0; ?>
    <h3>Progress</h3>
    <?php if (!$done): ?><meta http-equiv="refresh" content="30"><?php endif; ?>
    <p><b><?= $done ? 'Finished ✔' : e((string)$st['phase']) ?></b>
      <?php if (!$done && $tot): ?> – <?= (int)$st['done'] ?> of <?= $tot ?> (<?= $pct ?>%)<?php endif; ?></p>
    <?php if (!$done && $tot): ?><div style="background:#8883;border-radius:6px;height:10px"><div style="width:<?= $pct ?>%;height:10px;border-radius:6px;background:#3b82f6"></div></div><?php endif; ?>
    <p class="muted small"><?= e((string)($st['msg'] ?? '')) ?><br>Last report <?= $age < 90 ? 'just now' : (int)($age / 60) . ' min ago' ?><?= (!$done && $age > 600) ? ' – no news for a while, check the Pi (<code>tail fito.log</code>)' : '' ?>. <?= $done ? '' : 'Page refreshes by itself.' ?></p>
  <?php endif; ?>
  <h3>Share the archive</h3>
  <?php $sh = (string)setting('fito_share', '', true); ?>
  <?php if ($sh): $link = site_url('fitocracy-archive') . '?k=' . $sh; ?>
    <p class="muted small">Anyone with this link can read and download everything collected (not personal data, nothing else on the site).</p>
    <textarea readonly rows="2" style="width:100%;font-size:12px" onclick="this.select()"><?= e($link) ?></textarea>
    <form method="post" style="margin-top:8px;display:flex;gap:8px"><?= csrf_field() ?><input type="hidden" name="do" value="fito_share"><button class="btn small ghost" name="on" value="1">New link</button><button class="btn small ghost" name="on" value="0">Turn off</button></form>
  <?php else: ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="fito_share"><button class="btn small" name="on" value="1">Create share link</button></form>
  <?php endif; ?>
  <p><a href="<?= e(url('fitocracy-archive')) ?>">Open the archive page →</a></p>
  <h3>Received</h3>
  <?php $any = false; foreach (['digest.txt', 'urls.tsv', 'images.zip', 'calib.tsv', 'calib_blocks.txt', 'calib_images.zip'] as $n): $f = $fitoDir . '/' . $n; if (is_file($f)): $any = true; ?>
    <p><a href="<?= e(url('admin?tab=fito&dl=' . $n)) ?>"><?= e($n) ?></a> <span class="muted small"><?= (int)(filesize($f) / 1024) ?> KB · <?= e(date('Y-m-d H:i', filemtime($f))) ?></span></p>
  <?php endif; endforeach; if (!$any): ?><p class="muted small">Nothing yet.</p><?php endif; ?>
  <p class="muted small">Download <b>digest.txt</b> and attach it in the Claude chat. Images are for private reference only.</p>
</div>

<?php else: ?>
<div class="card">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="do" value="site_save">
    <label>Site name <input name="site_name" maxlength="40" value="<?= e(site_name()) ?>"></label>
    <label>Tagline <small class="muted">(used in search results and sharing)</small> <input name="tagline" maxlength="90" value="<?= e(site_tagline()) ?>"></label>
    <label class="check"><input type="checkbox" name="registration" value="1" <?= setting('registration', '1') === '1' ? 'checked' : '' ?>> Allow new registrations</label>
    <label class="check"><input type="checkbox" name="beta_banner" value="1" <?= setting('beta_banner', '1') === '1' ? 'checked' : '' ?>> Show the "site is being built" banner</label>
    <label>Default look <small class="muted">(for guests and members who pick "Automatic")</small>
      <select name="default_skin"><?php foreach (skin_list() as $k => $sk): ?><option value="<?= e($k) ?>" <?= setting('default_skin', 'auto') === $k ? 'selected' : '' ?>><?= e($sk[0]) ?></option><?php endforeach; ?></select></label>
    <label>Level speed multiplier <small class="muted">(1 = the standard level table; 2 = every level needs twice the points; changes recalculate everyone)</small>
      <input name="level_scale" inputmode="decimal" value="<?= e(fmt_num(level_scale(), 2)) ?>"></label>
    <button class="btn" type="submit">Save</button>
  </form>
  <?php $rs = recalc_status(); ?>
  <?php $elf = APP_ROOT . '/config/errors.log'; ?>
  <h3>Recent errors</h3>
  <?php if (is_file($elf) && filesize($elf) > 0): $lines = array_slice(file($elf, FILE_IGNORE_NEW_LINES) ?: [], -12); ?>
    <pre class="small" style="white-space:pre-wrap;word-break:break-all;max-height:260px;overflow:auto"><?= e(implode("\n", $lines)) ?></pre>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="clear_errors"><button class="btn small ghost" type="submit">Clear</button></form>
  <?php else: ?><p class="muted small">No errors logged. 🎉</p><?php endif; ?>
  <h3>Rules &amp; recalculation</h3>
  <p class="muted small"><?= $rs['pending'] ? 'Recalculating… ' . (int)$rs['left'] . ' of ' . (int)$rs['users'] . ' members left (progresses as pages load).' : 'Everything is up to date' . ($rs['last'] ? ' (last run ' . e($rs['last']) . ', ' . (int)$rs['changed'] . ' members changed)' : '') . '.' ?></p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="recalc_all"><button class="btn small ghost" type="submit">Recalculate everything now</button></form>
  <p class="muted small">App version <?= e(APP_VERSION) ?> · schema <?= (int)setting('schema_version', 0) ?> · PHP <?= e(PHP_VERSION) ?></p>
</div>
<?php endif; ?>
<?php layout_footer(); ?>
