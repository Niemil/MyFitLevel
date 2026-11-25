<?php
declare(strict_types=1);

$u = require_login();
define('GAME_CSS', true);
$uid = (int)$u['id'];
$newToken = null;

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');

    if ($do === 'profile') {
        $dn = clean_text((string)($_POST['display_name'] ?? ''), 60);
        $bio = clean_text((string)($_POST['bio'] ?? ''), 300);
        $unit = ($_POST['unit'] ?? 'kg') === 'lb' ? 'lb' : 'kg';
        $bwIn = trim((string)($_POST['bodyweight'] ?? ''));
        $bw = null;
        if ($bwIn !== '') {
            $v = (float)str_replace(',', '.', $bwIn);
            $bw = $unit === 'lb' ? $v / LB_PER_KG : $v;
            $bw = ($bw >= 20 && $bw <= 400) ? round($bw, 1) : null;
        }
        $vis = in_array($_POST['visibility'] ?? '', ['public', 'members', 'followers'], true) ? $_POST['visibility'] : 'public';
        $pre = in_array($_POST['prefill'] ?? '', ['first', 'all', 'none'], true) ? $_POST['prefill'] : 'first';
        $skin = isset(skin_list()[$_POST['skin'] ?? '']) ? (string)$_POST['skin'] : 'auto';
        q('UPDATE users SET display_name = ?, bio = ?, unit = ?, bodyweight_kg = ?, visibility = ?, share_public = ?, prefill = ?, skin = ? WHERE id = ?',
          [$dn ?: null, $bio ?: null, $unit, $bw, $vis, $vis === 'followers' ? 0 : 1, $pre, $skin, $uid]);
        flash('Settings saved.');
    } elseif ($do === 'email') {
        $email = clean_text((string)($_POST['email'] ?? ''), 190);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('That email address does not look right.', 'err');
        } elseif ($email !== '' && row('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $uid])) {
            flash('That email is already used by another account.', 'err');
        } else {
            if ($email !== (string)($u['email'] ?? '')) {
                q('UPDATE users SET email = ?, email_verified_at = NULL, email_token = NULL WHERE id = ?', [$email ?: null, $uid]);
                if ($email !== '') {
                    mail_user_verify(row('SELECT * FROM users WHERE id = ?', [$uid]));
                    flash('Email saved. We sent a confirmation link to ' . $email . '.');
                } else {
                    flash('Email removed.');
                }
            }
        }
    } elseif ($do === 'resend_verify') {
        if (empty($u['email'])) {
            flash('Add your email address first, then we can send the confirmation.', 'err');
        } elseif (!empty($u['email_verified_at'])) {
            flash('Your email is already confirmed.');
        } elseif (!rate_ok('rv:' . $uid, 6, 3600)) {
            flash('You asked for a lot of mails in a short time. Wait about 10 minutes and try again. Check your spam folder too.', 'err');
        } else {
            rate_hit('rv:' . $uid);
            [$ok, $err] = mail_user_verify($u);
            flash($ok ? 'Confirmation mail sent to ' . $u['email'] . '. Check spam too.' : 'Could not send the mail right now. Try again later.', $ok ? 'ok' : 'err');
        }
    } elseif ($do === 'hide_onboard') {
        q('UPDATE users SET onboard_hidden = 1 WHERE id = ?', [$uid]);
        redirect('feed');
    } elseif ($do === 'about') {
        $g = in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : null;
        $goal = in_array($_POST['goal'] ?? '', ['strength', 'muscle', 'fatloss', 'endurance', 'health'], true) ? $_POST['goal'] : null;
        $optout = empty($_POST['mail_ok']) ? 1 : 0;
        $tzIn = (string)($_POST['timezone'] ?? '');
        q('UPDATE users SET gender = ?, goal = ?, email_optout = ?, timezone = ? WHERE id = ?', [$g, $goal, $optout, valid_tz($tzIn) ? $tzIn : null, $uid]);
        flash('Saved.');
    } elseif ($do === 'password') {
        $old = (string)($_POST['old'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify($old, $u['password_hash'])) {
            flash('Your current password is wrong.', 'err');
        } elseif (strlen($new) < 8) {
            flash('New password must be at least 8 characters.', 'err');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            remember_forget(true, $uid);
            remember_issue($uid);
            session_regenerate_id(true);
            flash('Password changed.');
        }
    } elseif ($do === 'token_new') {
        $kname = trim(clean_text((string)($_POST['kname'] ?? ''), 40));
        if ((int)val('SELECT COUNT(*) FROM api_keys WHERE user_id = ?', [$uid]) >= 20) {
            flash('You already have 20 keys. Revoke some first.', 'err');
        } else {
            api_keys_ensure();
            api_key_create($uid, $kname !== '' ? $kname : 'Key ' . date('M j'));
            $nid = (int)val('SELECT MAX(id) FROM api_keys WHERE user_id = ?', [$uid]);
            redirect('settings?newkey=' . $nid . '#api');
        }
    } elseif ($do === 'token_revoke') {
        q('UPDATE users SET api_token_hash = NULL WHERE id = ?', [$uid]);
        flash('Old main key revoked.');
    } elseif ($do === 'key_del') {
        api_keys_ensure();
        q('DELETE FROM api_keys WHERE id = ? AND user_id = ?', [(int)($_POST['id'] ?? 0), $uid]);
        flash('Key revoked.');
    } elseif ($do === 'delete') {
        if (!password_verify((string)($_POST['pw'] ?? ''), $u['password_hash'])) {
            flash('Wrong password. Your account was NOT deleted.', 'err');
        } elseif (($_POST['confirm'] ?? '') !== 'DELETE') {
            flash('Type DELETE to confirm. Your account was NOT deleted.', 'err');
        } else {
            q('DELETE FROM users WHERE id = ?', [$uid]);
            logout_user();
            start_session();
            flash('Your account and all your data have been deleted.');
            redirect('');
        }
    }
    if ($newToken === null) {
        redirect('settings');
    }
    $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
}

$bwShow = $u['bodyweight_kg'] !== null ? fmt_num($u['unit'] === 'lb' ? (float)$u['bodyweight_kg'] * LB_PER_KG : (float)$u['bodyweight_kg'], 1) : '';
$apiBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . url('api/v1');

layout_header('Settings');
?>
<h1 class="pagetitle">Settings</h1>
<div class="narrowcol">
<div class="card">
  <h3>Profile &amp; preferences</h3>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="do" value="profile">
    <label>Display name <input name="display_name" maxlength="60" value="<?= e($u['display_name'] ?? '') ?>" placeholder="<?= e($u['username']) ?>"></label>
    <label>Bio <textarea name="bio" rows="2" maxlength="300"><?= e($u['bio'] ?? '') ?></textarea></label>
    <div class="row2">
      <label>Units <select name="unit"><option value="kg" <?= $u['unit'] === 'kg' ? 'selected' : '' ?>>Kilograms</option><option value="lb" <?= $u['unit'] === 'lb' ? 'selected' : '' ?>>Pounds</option></select></label>
      <label>Bodyweight <small class="muted">(for pull-ups, push-ups, etc.)</small>
        <input name="bodyweight" inputmode="decimal" value="<?= e($bwShow) ?>" placeholder="75"></label>
    </div>
    <label>Who can see my workouts and posts
      <select name="visibility">
        <option value="public" <?= $u['visibility'] === 'public' ? 'selected' : '' ?>>Everyone (public, also people who aren't logged in)</option>
        <option value="members" <?= $u['visibility'] === 'members' ? 'selected' : '' ?>>Members only (logged-in members)</option>
        <option value="followers" <?= $u['visibility'] === 'followers' ? 'selected' : '' ?>>Only people who follow me</option>
      </select></label>
    <label>Look &amp; colours <select name="skin"><?php foreach (skin_list() as $k => $sk): ?><option value="<?= e($k) ?>" <?= ($u['skin'] ?? 'auto') === $k ? 'selected' : '' ?>><?= e($sk[0]) ?></option><?php endforeach; ?></select></label>
    <div class="skinrow"><?php foreach (skin_list() as $k => $sk): if ($k === 'auto') { continue; } ?><span class="skinchip" title="<?= e($sk[0] . ': ' . $sk[4]) ?>"><i style="background:<?= e($sk[1]) ?>"></i><i style="background:<?= e($sk[2]) ?>"></i><i style="background:<?= e($sk[3]) ?>"></i></span><?php endforeach; ?></div>
    <label>When I add an exercise to a workout
      <select name="prefill">
        <option value="first" <?= $u['prefill'] === 'first' ? 'selected' : '' ?>>Fill in the first set like last time (default)</option>
        <option value="all" <?= $u['prefill'] === 'all' ? 'selected' : '' ?>>Copy all sets from last time</option>
        <option value="none" <?= $u['prefill'] === 'none' ? 'selected' : '' ?>>Start empty (I still see last time's numbers)</option>
      </select></label>
    <button class="btn" type="submit">Save</button>
  </form>
</div>

<div class="card" id="about">
  <h3>About you</h3>
  <p class="muted small">Optional. Gender may be used for strength standards later. It is never shown on your profile.</p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="do" value="about">
    <label>Gender
      <select name="gender"><option value="">Prefer not to say</option>
        <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $k => $l): ?><option value="<?= $k ?>" <?= ($u['gender'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select></label>
    <label>Main goal
      <select name="goal"><option value="">Not chosen</option>
        <?php foreach (['strength' => 'Get stronger', 'muscle' => 'Build muscle', 'fatloss' => 'Lose fat', 'endurance' => 'Endurance', 'health' => 'Stay healthy'] as $k => $l): ?><option value="<?= $k ?>" <?= ($u['goal'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select></label>
    <label>Time zone <small class="muted">(used for your workout times)</small>
      <select name="timezone" id="tzselect" <?= !empty($u['timezone']) ? 'data-set="1"' : '' ?>><option value="">Server default</option>
        <?php foreach (DateTimeZone::listIdentifiers() as $tzn): ?><option value="<?= e($tzn) ?>" <?= ($u['timezone'] ?? '') === $tzn ? 'selected' : '' ?>><?= e($tzn) ?></option><?php endforeach; ?>
      </select></label>
    <label class="check"><input type="checkbox" name="mail_ok" value="1" <?= empty($u['email_optout']) ? 'checked' : '' ?>> Send me friendly reminder emails</label>
    <button class="btn" type="submit">Save</button>
  </form>
</div>

<div class="card">
  <h3>Invite friends</h3>
  <p class="muted small">Share your link. You earn <?= REFERRAL_CREDITS ?> credits when a friend logs their first workout. Credits will unlock cosmetic perks.</p>
  <input readonly value="<?= e(site_url('register?ref=' . $u['username'])) ?>" onclick="this.select()">
  <p class="small">Joined through you: <b><?= (int)val('SELECT COUNT(*) FROM users WHERE referred_by = ?', [$uid]) ?></b> · Your credits: <b><?= (int)($u['credits'] ?? 0) ?></b></p>
</div>

<div class="card" id="email">
  <h3>Email <?php if (!empty($u['email'])): ?><small class="<?= !empty($u['email_verified_at']) ? 'ok' : 'muted' ?>"><?= !empty($u['email_verified_at']) ? '✓ confirmed' : 'not confirmed' ?></small><?php endif; ?></h3>
  <form method="post" class="row2">
    <?= csrf_field() ?><input type="hidden" name="do" value="email">
    <label>Email address <input type="email" name="email" value="<?= e($u['email'] ?? '') ?>"></label>
    <div><button class="btn" type="submit">Update</button></div>
  </form>
</div>

<div class="card">
  <h3>Change password</h3>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="do" value="password">
    <label>Current password <input type="password" name="old" autocomplete="current-password" required></label>
    <label>New password <input type="password" name="new" autocomplete="new-password" minlength="8" required></label>
    <button class="btn" type="submit">Change password</button>
  </form>
</div>

<?php
api_keys_ensure();
$popKey = null;
if (isset($_GET['newkey'])) {
    $pk = row('SELECT * FROM api_keys WHERE id = ? AND user_id = ?', [(int)$_GET['newkey'], $uid]);
    if ($pk && api_key_reveal($pk) !== '') { $popKey = [$pk['name'], api_key_reveal($pk)]; }
}
if ($popKey): ?>
<div class="modal" id="keymodal" role="dialog" aria-modal="true" aria-label="New API key">
  <a class="modal-bg" href="<?= e(url('settings')) ?>#api" aria-label="Close"></a>
  <div class="modal-box">
    <div class="modal-ico">🔑</div>
    <h3>Key "<?= e($popKey[0]) ?>" is ready</h3>
    <p class="muted small">Tap the key to copy it. You can also find it again in the list below any time.</p>
    <button type="button" class="keycopy big" data-copy="<?= e($popKey[1]) ?>"><code><?= e($popKey[1]) ?></code><span>Tap to copy</span></button>
    <a class="btn ghost" href="<?= e(url('settings')) ?>#api">Done</a>
  </div>
</div>
<?php endif;
$myKeys = rows('SELECT * FROM api_keys WHERE user_id = ? ORDER BY id DESC', [$uid]); ?>
<div class="card" id="api">
  <h3>API access</h3>
  <p class="muted small">Use an API key to read your workouts from another site or app (for example another site of yours), or to log workouts from a script.
     Base URL: <code><?= e($apiBase) ?></code></p>
  <p class="muted small">You can have many keys, one per app or site (the Android app makes its own). Creating a new key never touches the others. Keep keys private: anyone with a key can log steps and read your data as you.</p>
  <?php foreach ($myKeys as $k): $kv = api_key_reveal($k); ?>
    <div class="keybox">
      <div class="keyhead">
        <div><b><?= e($k['name']) ?></b><br><small class="muted">made <?= e(date('M j', strtotime($k['created_at']))) ?> · <?= $k['last_used'] ? 'last used ' . e(rel_time($k['last_used'])) : 'never used' ?></small></div>
        <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><button class="btn ghost danger small" name="do" value="key_del" type="submit">Revoke</button></form>
      </div>
      <?php if ($kv !== ''): ?>
        <button type="button" class="keycopy" data-copy="<?= e($kv) ?>"><code><?= e($kv) ?></code><span>Tap to copy</span></button>
      <?php else: ?><p class="muted small" style="margin:.4rem 0 0">Made before keys could be shown again. Create a new one if you need to see it.</p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!empty($u['api_token_hash'])): ?>
    <form method="post" class="inl" style="margin:.3rem 0"><?= csrf_field() ?><b>Main key (older)</b> <small class="muted">still works</small>
      <button class="btn ghost danger small" name="do" value="token_revoke" type="submit">Revoke</button></form>
  <?php endif; ?>
  <form method="post" class="newkey"><?= csrf_field() ?>
    <input name="kname" maxlength="40" placeholder="Name, e.g. snakediet">
    <button class="btn" name="do" value="token_new" type="submit">Create new key</button>
  </form>
</div>

<div class="card">
  <h3>Your data</h3>
  <p class="muted small">Download everything you have logged here as a JSON file.</p>
  <a class="btn ghost" href="<?= e(url('settings/export')) ?>">Download my data</a>
  <hr>
  <h4>Delete account</h4>
  <p class="muted small">This permanently deletes your account, workouts, comments and everything else. It cannot be undone.</p>
  <form method="post" data-confirm="Really delete your account and all data?">
    <?= csrf_field() ?><input type="hidden" name="do" value="delete">
    <label>Password <input type="password" name="pw" autocomplete="current-password" required></label>
    <label>Type DELETE to confirm <input name="confirm" autocomplete="off" required></label>
    <button class="btn danger" type="submit">Delete my account</button>
  </form>
</div>
</div>
<?php layout_footer('', ['tz.js', 'copy.js']); ?>
