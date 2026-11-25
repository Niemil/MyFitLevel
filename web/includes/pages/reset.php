<?php
declare(strict_types=1);

$tok = (string)$args[0];
$u = row('SELECT * FROM users WHERE pw_reset_token = ? AND pw_reset_expires > ? AND is_banned = 0', [hash('sha256', $tok), now()]);
$error = '';
if ($u && is_post()) {
    csrf_check();
    $p1 = (string)($_POST['password'] ?? '');
    if (strlen($p1) < 8) {
        $error = 'Use at least 8 characters.';
    } elseif ($p1 !== (string)($_POST['password2'] ?? '')) {
        $error = 'The two passwords do not match.';
    } else {
        q('UPDATE users SET password_hash = ?, pw_reset_token = NULL, pw_reset_expires = NULL, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?',
          [password_hash($p1, PASSWORD_DEFAULT), now(), $u['id']]);
        flash('Password changed. You can log in now.');
        redirect('login');
    }
}
layout_header('New password');
?>
<div class="card authcard">
  <h2>Choose a new password</h2>
  <?php if (!$u): ?>
    <p>This reset link has expired or was already used.</p>
    <p><a class="btn" href="<?= e(url('forgot')) ?>">Ask for a new one</a></p>
  <?php else: ?>
    <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <label>New password <small class="muted">(8+ characters)</small><input type="password" name="password" required autocomplete="new-password"></label>
      <label>Repeat password<input type="password" name="password2" required autocomplete="new-password"></label>
      <button class="btn wide" type="submit">Save password</button>
    </form>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>
