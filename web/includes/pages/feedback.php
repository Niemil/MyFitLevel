<?php
declare(strict_types=1);

$u = me();
ql_ensure_tables();
$kinds = ['bug' => 'Something is broken', 'idea' => 'Idea or suggestion', 'points' => 'Points or levels feel off', 'other' => 'Something else'];

if (is_post()) {
    csrf_check();
    $key = 'fb:' . ($u ? (int)$u['id'] : client_ip());
    $msg = clean_text((string)($_POST['message'] ?? ''), 3000);
    $kind = isset($kinds[$_POST['kind'] ?? '']) ? (string)$_POST['kind'] : 'other';
    if (trim((string)($_POST['website'] ?? '')) !== '') {   // honeypot
        redirect('feedback?sent=1');
    }
    // Guests sending links, domains or SEO pitches are bots: pretend it worked and drop it.
    if (!$u && preg_match('~https?://|www\.|\b[a-z0-9-]+\.(?:pro|xyz|top|biz|info|ru|cn|click|site|online)\b|search index|backlink|\bseo\b|crypto|casino~i', $msg)) {
        redirect('feedback?sent=1');
    }
    if (mb_strlen($msg) < 5) {
        flash('Write a few words about it first.', 'err');
        redirect('feedback');
    }
    if (!rate_ok($key, 8, 3600)) {
        flash('That is a lot of feedback in a short time. Please try again later.', 'err');
        redirect('feedback');
    }
    rate_hit($key);
    $page = clean_text((string)($_POST['page'] ?? ''), 200);
    $agent = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200);
    $contact = clean_text((string)($_POST['contact'] ?? ''), 120);
    q('INSERT INTO feedback (user_id, kind, message, page, agent, contact, created_at) VALUES (?,?,?,?,?,?,?)',
      [$u ? (int)$u['id'] : null, $kind, $msg, $page ?: null, $agent, $contact ?: null, now()]);
    $who = $u ? display_name($u) : 'A visitor';
    foreach (rows('SELECT id, email FROM users WHERE is_admin = 1 AND is_banned = 0') as $a) {
        q('INSERT INTO notifications (user_id, actor_id, type, text, created_at) VALUES (?,?,?,?,?)',
          [$a['id'], $u ? $u['id'] : null, 'feedback', "New feedback ($kind) from $who: " . mb_substr($msg, 0, 80), now()]);
        if (!empty($a['email'])) {
            try {
                send_mail($a['email'], 'New feedback on ' . site_name() . ' (' . $kind . ')',
                    mail_html('New feedback', '<p><b>' . e($who) . '</b> sent a ' . e($kind) . ' note' . ($page ? ' from ' . e($page) : '') . ':</p><p>' . nl2br(e($msg)) . '</p>', 'Open feedback', site_url('admin?tab=feedback')),
                    "$who ($kind): $msg");
            } catch (Throwable $e) {
                error_log('feedback mail: ' . $e->getMessage());
            }
        }
    }
    redirect('feedback?sent=1');
}

layout_header('Feedback', '');
$from = clean_text((string)($_GET['from'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 200);
?>
<h1 class="pagetitle">Feedback</h1>
<?php if (isset($_GET['sent'])): ?>
  <div class="card center"><h3>Thank you! 🙌</h3><p class="muted">Your note reached the people building <?= e(site_name()) ?>. We read everything.</p><a class="btn" href="<?= e(url($u ? 'feed' : '')) ?>">Back</a></div>
<?php else: ?>
<p class="muted"><?= e(site_name()) ?> is brand new and built in the open. Found a bug, miss a feature or think the points are off? Tell us. Want to help build it? Say so in the message.</p>
<form method="post" class="card"><?= csrf_field() ?>
  <input type="hidden" name="page" value="<?= e($from) ?>">
  <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
  <label>What is it about?
    <select name="kind"><?php foreach ($kinds as $k => $l): ?><option value="<?= e($k) ?>"<?= ($_GET['kind'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label>Your message
    <textarea name="message" rows="6" maxlength="3000" required placeholder="What happened, or what would you like to see? For bugs: what did you tap, and what did you expect?"></textarea></label>
  <?php if (!$u): ?><label>Contact <small class="muted">(optional, so we can reply)</small><input name="contact" maxlength="120"></label><?php endif; ?>
  <button class="btn wide" type="submit">Send feedback</button>
</form>
<?php endif; ?>
<?php layout_footer(''); ?>
