<?php
declare(strict_types=1);

$uid = (int)$args[0];
$ok = hash_equals(substr(hash_hmac('sha256', 'unsub' . $uid, site_secret()), 0, 16), (string)$args[1]);
if ($ok && is_post()) {
    csrf_check();
    q('UPDATE users SET email_optout = 1 WHERE id = ?', [$uid]);
}
$done = $ok && is_post();
layout_header('Unsubscribe');
?>
<div class="card authcard center">
  <?php if (!$ok): ?><h2>Link not valid</h2><p class="muted">Open Settings to manage your emails.</p>
  <?php elseif ($done): ?><h2>You are unsubscribed</h2><p class="muted">No more reminder emails. Account emails like password resets still work.</p>
  <?php else: ?>
    <h2>Stop reminder emails?</h2>
    <form method="post"><?= csrf_field() ?><button class="btn" type="submit">Yes, unsubscribe me</button></form>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>
