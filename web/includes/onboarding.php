<?php
declare(strict_types=1);

const REFERRAL_CREDITS = 10;

function onboard_tasks(array $u): array
{
    $uid = (int)$u['id'];
    $hasW = (bool)val('SELECT 1 FROM workouts WHERE user_id = ? LIMIT 1', [$uid]);
    $hasF = (bool)val('SELECT 1 FROM follows WHERE follower_id = ? LIMIT 1', [$uid]);
    $t = [];
    if (!empty($u['email'])) {
        $t[] = ['Confirm your email', !empty($u['email_verified_at']), 'settings#email'];
    } else {
        $t[] = ['Add an email (for password reset)', false, 'settings#email'];
    }
    $t[] = ['Add a profile photo', !empty($u['avatar_token']), 'u/' . $u['username']];
    $t[] = ['Enter your bodyweight', !empty($u['bodyweight_kg']), 'welcome'];
    $t[] = ['Choose your goal', !empty($u['goal']), 'welcome'];
    $t[] = ['Log your first workout', $hasW, 'log'];
    $t[] = ['Follow someone', $hasF, 'members'];
    return $t;
}

function onboard_card(array $u): void
{
    if (!empty($u['onboard_hidden'])) {
        return;
    }
    $tasks = onboard_tasks($u);
    $done = count(array_filter($tasks, fn($t) => $t[1]));
    if ($done === count($tasks)) {
        return;
    }
    $pct = (int)round($done / count($tasks) * 100);
    ?>
<div class="card onboard">
  <div class="onb-head"><h3>Getting started <small class="muted"><?= $done ?>/<?= count($tasks) ?></small></h3>
    <form method="post" action="<?= e(url('settings')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="hide_onboard"><button class="btn ghost small" type="submit">Hide</button></form>
  </div>
  <div class="onb-bar"><i style="width:<?= $pct ?>%"></i></div>
  <ul class="onb-list">
    <?php foreach ($tasks as $t): ?>
      <li class="<?= $t[1] ? 'ok' : '' ?>"><a href="<?= e(url($t[2])) ?>"><span class="onb-dot"><?= $t[1] ? '✓' : '' ?></span><?= e($t[0]) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <p class="small muted onb-opt"><?= !empty($u['default_gym_id']) ? '🏋️ Your gym is set.' : '🏋️ Optional: <a href="' . e(url('gyms')) . '">add your gym or set up a home gym</a>' ?></p>
</div>
<?php
}

function verify_banner(array $u): void
{
    if (empty($u['email']) || !empty($u['email_verified_at'])) {
        return;
    }
    ?>
<div class="card verifybar">
  <span>Please confirm your email address <b><?= e($u['email']) ?></b>.</span>
  <form method="post" action="<?= e(url('settings')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="resend_verify"><button class="btn small" type="submit">Resend mail</button></form>
</div>
<?php
}

/** Called after a member's first saved workout: rewards whoever invited them. */
function referral_reward(int $uid): void
{
    $r = row('SELECT referred_by FROM users WHERE id = ?', [$uid]);
    if (!$r || !$r['referred_by']) {
        return;
    }
    if ((int)val('SELECT COUNT(*) FROM workouts WHERE user_id = ?', [$uid]) !== 1) {
        return;
    }
    q('UPDATE users SET credits = credits + ? WHERE id = ? AND is_banned = 0', [REFERRAL_CREDITS, $r['referred_by']]);
    if (function_exists('notify')) {
        try { notify((int)$r['referred_by'], $uid, 'referral', null); } catch (Throwable $e) {}
    }
}

/** "We miss you" mails. Returns number sent. */
function miss_you_run(int $limit = 25): int
{
    $cut = date('Y-m-d H:i:s', time() - 14 * 86400);
    $again = date('Y-m-d H:i:s', time() - 60 * 86400);
    $c = rows('SELECT * FROM users u
        WHERE u.email IS NOT NULL AND u.email <> "" AND u.email_optout = 0 AND u.is_banned = 0
          AND COALESCE(u.last_seen, u.created_at) < ? AND u.created_at < ?
          AND (u.miss_mailed_at IS NULL OR u.miss_mailed_at < ?)
          AND NOT EXISTS (SELECT 1 FROM workouts w WHERE w.user_id = u.id AND w.created_at >= ?)
          AND NOT EXISTS (SELECT 1 FROM posts p WHERE p.user_id = u.id AND p.created_at >= ?)
        ORDER BY u.id LIMIT ' . (int)$limit, [$cut, $cut, $again, $cut, $cut]);
    $sent = 0;
    foreach ($c as $u) {
        $wc = (int)val('SELECT COUNT(*) FROM workouts WHERE user_id = ?', [$u['id']]);
        $name = display_name($u);
        $lvl = (int)$u['level'];
        $line = $wc > 0
            ? 'You are level ' . $lvl . ' with ' . $wc . ' workouts logged. Everything is right where you left it.'
            : 'Your account is ready and waiting. Your first workout takes about a minute to log.';
        $body = '<p>Hi ' . e($name) . ',</p><p>We noticed it has been a while, and the community misses you. ' . e($line)
              . '</p><p>Even a short session counts. Log something small today and get your streak going again.</p>';
        $url = site_url('log');
        [$ok] = send_mail($u['email'], 'We miss you, ' . $name . '!',
            mail_html('We miss you!', $body, 'Log a workout', $url, 'Do not want these reminders? <a href="' . e(unsub_url((int)$u['id'])) . '">Unsubscribe</a>'),
            "Hi $name, we miss you! Log a workout: $url\nUnsubscribe: " . unsub_url((int)$u['id']),
            ['List-Unsubscribe: <' . unsub_url((int)$u['id']) . '>']);
        q('UPDATE users SET miss_mailed_at = ? WHERE id = ?', [now(), $u['id']]);   // even on failure, avoid retry storms
        $sent += $ok ? 1 : 0;
    }
    return $sent;
}

function user_tz(?array $u): DateTimeZone
{
    $n = (string)($u['timezone'] ?? '');
    try {
        return new DateTimeZone($n !== '' ? $n : date_default_timezone_get());
    } catch (Throwable $e) {
        return new DateTimeZone(date_default_timezone_get());
    }
}

function valid_tz(string $n): bool
{
    return $n !== '' && in_array($n, DateTimeZone::listIdentifiers(), true);
}
