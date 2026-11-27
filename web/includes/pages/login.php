<?php
declare(strict_types=1);

if (me()) {
    redirect('today');
}

$error = '';
$ident = '';
if (is_post()) {
    csrf_check();
    $ident = clean_text((string)($_POST['identity'] ?? ''), 190);
    $pass = (string)($_POST['password'] ?? '');
    $key = 'login:' . client_ip();
    $key2 = 'login:u:' . strtolower($ident);

    if (!rate_ok($key, 15, 900) || !rate_ok($key2, 8, 900)) {
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        ban_sweep();
        $u = row('SELECT * FROM users WHERE username = ? OR email = ?', [$ident, $ident]);
        if ($u && !$u['is_banned'] && password_verify($pass, $u['password_hash'])) {
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            }
            login_user((int)$u['id']);
            $to = $_SESSION['after_login'] ?? '';
            unset($_SESSION['after_login']);
            if ($to !== '' && $to[0] === '/' && !str_starts_with($to, '//')) {
                header('Location: ' . $to);
                exit;
            }
            redirect('today');
        }
        rate_hit($key);
        rate_hit($key2);
        $shown = str_contains($ident, '@') ? substr($ident, 0, 1) . '***' . substr($ident, (int)strrpos($ident, '@')) : $ident;   // email addresses are masked
        AppLog::security('login_failed', 'Failed login for "' . mb_substr($shown, 0, 60) . '"' . (($u && $u['is_banned']) ? ' (suspended account)' : ''));
        $error = ($u && $u['is_banned'])
            ? 'This account has been suspended' . ($u['banned_until'] ? ' until ' . date('j M Y H:i', strtotime($u['banned_until'])) : ' permanently') . ($u['ban_reason'] ? ': ' . $u['ban_reason'] : '.')
            : 'Wrong username or password.';
    }
}

layout_header('Log in');
?>
<div class="card narrow">
  <h2>Welcome back</h2>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <label>Username or email
      <input name="identity" value="<?= e($ident) ?>" autocomplete="username" required autofocus>
    </label>
    <label>Password
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn wide" type="submit">Log in</button>
    <p class="muted center small"><a href="<?= e(url('forgot')) ?>">Forgot your password?</a></p>
  </form>
  <p class="muted center">New here? <a href="<?= e(url('register')) ?>">Create an account</a></p>
</div>
<?php layout_footer(); ?>
