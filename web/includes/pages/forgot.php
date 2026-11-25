<?php
declare(strict_types=1);

if (me()) {
    redirect('settings');
}
$sent = false;
if (is_post()) {
    csrf_check();
    $email = clean_text((string)($_POST['email'] ?? ''), 190);
    if (rate_ok('forgot:' . client_ip(), 5, 3600) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        rate_hit('forgot:' . client_ip());
        $u = row('SELECT * FROM users WHERE email = ? AND is_banned = 0', [$email]);
        if ($u) {
            $tok = bin2hex(random_bytes(20));
            q('UPDATE users SET pw_reset_token = ?, pw_reset_expires = ? WHERE id = ?', [hash('sha256', $tok), date('Y-m-d H:i:s', time() + 3600), $u['id']]);
            $url = site_url('reset/' . $tok);
            send_mail($email, 'Reset your password', mail_html('Reset your password',
                '<p>Hi ' . e(display_name($u)) . ',</p><p>Someone asked to reset the password for your account. The link works for one hour. If it was not you, just ignore this email.</p>', 'Choose a new password', $url), "Reset your password: $url");
        }
    }
    $sent = true;   // same answer whether or not the address exists
}
layout_header('Forgot password');
?>
<div class="card authcard">
  <h2>Forgot your password?</h2>
  <?php if ($sent): ?>
    <p>If that email address belongs to an account, a reset link is on its way. It works for one hour.</p>
    <p class="muted small">Only accounts with an email address saved can reset this way.</p>
  <?php else: ?>
    <form method="post"><?= csrf_field() ?>
      <label>Email address <input type="email" name="email" required autofocus autocomplete="email"></label>
      <button class="btn wide" type="submit">Send reset link</button>
    </form>
  <?php endif; ?>
  <p class="muted center"><a href="<?= e(url('login')) ?>">Back to log in</a></p>
</div>
<?php layout_footer(); ?>
