<?php
declare(strict_types=1);

$tok = (string)$args[0];
$t = row('SELECT id FROM users WHERE email_token = ? AND is_banned = 0', [$tok]);
if ($t) {
    q('UPDATE users SET email_verified_at = ?, email_token = NULL WHERE id = ?', [now(), $t['id']]);
    flash('Email confirmed. Thank you!');
} else {
    flash('That confirmation link is not valid any more. You can ask for a new one in Settings.', 'err');
}
redirect(me() ? 'feed' : 'login');
