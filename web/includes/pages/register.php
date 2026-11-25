<?php
declare(strict_types=1);

if (me()) {
    redirect('feed');
}
$open = setting('registration', '1') === '1';
$error = '';
$f = ['username' => '', 'email' => ''];
$refName = clean_text((string)($_GET['ref'] ?? $_POST['ref'] ?? ''), 30);
$refUser = $refName !== '' ? row('SELECT id, username FROM users WHERE username = ? AND is_banned = 0', [$refName]) : null;

if (is_post() && $open) {
    csrf_check();
    $f['username'] = clean_text((string)($_POST['username'] ?? ''), 30);
    $f['email'] = clean_text((string)($_POST['email'] ?? ''), 190);
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');
    $unit = ($_POST['unit'] ?? 'kg') === 'lb' ? 'lb' : 'kg';
    $ipKey = 'reg:' . client_ip();

    if (!empty($_POST['website'])) {            // honeypot
        redirect('');
    } elseif (!rate_ok($ipKey, 5, 3600)) {
        $error = 'Too many sign-ups from your connection. Try again later.';
    } elseif (!valid_username($f['username'])) {
        $error = 'Username must be 3–20 characters: letters, numbers and underscore only.';
    } elseif ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'That email address does not look right (or leave it empty).';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'The two passwords do not match.';
    } elseif (row('SELECT id FROM users WHERE username = ?', [$f['username']])) {
        $error = 'That username is taken.';
    } elseif ($f['email'] !== '' && row('SELECT id FROM users WHERE email = ?', [$f['email']])) {
        $error = 'That email is already registered.';
    } else {
        $tzIn = (string)($_POST['tz'] ?? '');
        q('INSERT INTO users (username, email, password_hash, unit, created_at, referred_by, timezone) VALUES (?,?,?,?,?,?,?)',
          [$f['username'], $f['email'] ?: null, password_hash($pass, PASSWORD_DEFAULT), $unit, now(), $refUser['id'] ?? null, valid_tz($tzIn) ? $tzIn : null]);
        $id = (int)db()->lastInsertId();
        if ($f['email'] !== '') {
            mail_user_verify(row('SELECT * FROM users WHERE id = ?', [$id]));
        }
        rate_hit($ipKey);
        login_user($id);
        redirect('welcome');
    }
}

layout_header('Join free', '', ['index' => true, 'path' => 'register', 'desc' => 'Create a free account: log workouts, earn points, level up and join the community.']);
?>
<div class="card narrow">
  <h2>Join <?= e(site_name()) ?></h2>
  <?php if (!$open): ?>
    <p class="muted">Registration is closed right now. Please check back later.</p>
  <?php else: ?>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>Username
      <input name="username" value="<?= e($f['username']) ?>" maxlength="20" autocomplete="username" required autofocus>
    </label>
    <?php if ($refUser): ?><input type="hidden" name="ref" value="<?= e($refUser['username']) ?>"><p class="muted">Invited by <b><?= e($refUser['username']) ?></b></p><?php endif; ?>
    <label>Email <small class="muted">(optional, needed for password reset)</small>
      <input type="email" name="email" value="<?= e($f['email']) ?>" autocomplete="email">
    </label>
    <label>Password <small class="muted">(8+ characters)</small>
      <input type="password" name="password" autocomplete="new-password" required>
    </label>
    <label>Repeat password
      <input type="password" name="password2" autocomplete="new-password" required>
    </label>
    <fieldset class="seg">
      <legend>Weight units <small class="muted">(you can change this later in Settings)</small></legend>
      <label><input type="radio" name="unit" value="kg" <?= ($_POST['unit'] ?? 'kg') !== 'lb' ? 'checked' : '' ?>><span>Metric · kg</span></label>
      <label><input type="radio" name="unit" value="lb" <?= ($_POST['unit'] ?? '') === 'lb' ? 'checked' : '' ?>><span>Imperial · lb</span></label>
    </fieldset>
    <input type="hidden" name="tz" id="tzfield" value="">
    <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <button class="btn wide" type="submit">Create account</button>
  </form>
  <p class="muted center">Already a member? <a href="<?= e(url('login')) ?>">Log in</a></p>
  <?php endif; ?>
</div>
<?php layout_footer('', ['tz.js']); ?>
