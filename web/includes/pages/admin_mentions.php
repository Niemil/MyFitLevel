<?php
declare(strict_types=1);

$admin = require_admin();
require_once APP_ROOT . '/includes/mentions.php';
mentions_ensure();
if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'mailcheck') {
        set_setting('mentions_imap_host', clean_text((string)($_POST['host'] ?? ''), 120));
        flash(mentions_check_mail());
        redirect('admin/mentions');
    }
    $id = (int)($_POST['id'] ?? 0);
    $to = (string)($_POST['to'] ?? '');
    if ($id > 0 && in_array($to, ['new', 'seen', 'replied', 'ignored'], true)) {
        q('UPDATE mentions SET status = ? WHERE id = ?', [$to, $id]);
    }
    redirect('admin/mentions?f=' . urlencode((string)($_POST['f'] ?? 'open')));
}
$f = in_array($_GET['f'] ?? '', ['open', 'all', 'replied', 'ignored'], true) ? $_GET['f'] : 'open';
$w = $f === 'open' ? "status IN ('new','seen')" : ($f === 'all' ? '1=1' : 'status = ' . ($f === 'replied' ? "'replied'" : "'ignored'"));
$list = rows("SELECT * FROM mentions WHERE $w ORDER BY (status='new') DESC, score DESC, id DESC LIMIT 100");
$cnt = (int)val("SELECT COUNT(*) FROM mentions WHERE status = 'new'");
layout_header('Mentions', '');
?>
<h1 class="pagetitle">Mentions</h1>
<p class="muted small"><a href="<?= e(url('admin')) ?>">Back to admin</a> · Places on the web where people talk about Fitocracy or look for an alternative. Found daily by the watcher script. Answer by hand, as yourself.</p>
<div class="card">
  <p class="sx-filter small">
    <?php foreach (['open' => 'To look at (' . $cnt . ' new)', 'replied' => 'Replied', 'ignored' => 'Ignored', 'all' => 'All'] as $k => $l): ?>
    <a class="<?= $f === $k ? 'on' : '' ?>" href="<?= e(url('admin/mentions?f=' . $k)) ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </p>
  <?php if (!$list): ?><p class="muted">Nothing here yet.</p><?php endif; ?>
  <?php foreach ($list as $r): ?>
  <div class="mention" style="padding:10px 0;border-top:1px solid var(--line)">
    <div class="small muted"><b><?= (int)$r['score'] ?>%</b> match · <?= e($r['source']) ?><?= $r['place'] !== '' ? ' · ' . e($r['place']) : '' ?><?= $r['author'] !== '' ? ' · ' . e($r['author']) : '' ?> · found <?= e(rel_time($r['found_at'])) ?><?= $r['status'] === 'new' ? ' · <b>new</b>' : '' ?></div>
    <div><a href="<?= e($r['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($r['title'] !== '' ? $r['title'] : $r['url']) ?></a></div>
    <?php if ($r['snippet'] !== ''): ?><div class="small muted"><?= e($r['snippet']) ?></div><?php endif; ?>
    <form method="post" class="inl" style="margin-top:6px">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="f" value="<?= e($f) ?>">
      <?php foreach (['replied' => 'I replied', 'ignored' => 'Ignore', 'seen' => 'Seen'] as $to => $l): if ($r['status'] !== $to): ?>
      <button class="btn small" name="to" value="<?= $to ?>" type="submit"><?= e($l) ?></button>
      <?php endif; endforeach; ?>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<?php $last = (int)setting('mentions_last_run', '0'); $mc = mail_conf(); $notes = json_decode((string)setting('mentions_notes', '[]'), true) ?: []; ?>
<?php if ($notes): ?>
<div class="card">
  <h3>Needs your click</h3>
  <p class="small muted">Confirmation mails from Google Alerts or F5Bot. Open the link once to switch the alert on.</p>
  <?php foreach ($notes as $nt): ?><p class="small"><?= e($nt['s']) ?><?php foreach ($nt['u'] as $u): ?><br><a href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e(mb_substr($u, 0, 70)) ?></a><?php endforeach; ?></p><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="card">
  <h3>Alert mail</h3>
  <p class="small muted">Send Google Alerts and F5Bot to <b><?= e($mc['user']) ?></b>. The site reads that mailbox every time the daily cron job runs and lists the posts above. Last check: <?= $last ? e(rel_time(date('Y-m-d H:i:s', $last))) . ' · ' . e((string)setting('mentions_last_info', '')) : 'never' ?>.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="mailcheck">
    <label class="small muted">Mail server (usually the same as in Admin, Email)</label>
    <input type="text" name="host" value="<?= e((string)setting('mentions_imap_host', '') ?: (string)$mc['host']) ?>" style="width:100%">
    <p><button class="btn" type="submit">Check mail now</button></p>
  </form>
</div>
<div class="card">
  <h3>How to set it up</h3>
  <ol class="small">
    <li>google.com/alerts: type a phrase such as <i>fitocracy alternative</i>, press Show options, choose <b>As-it-happens</b> or <b>Once a day</b> and deliver to <b><?= e($mc['user']) ?></b>. Make one alert per phrase.</li>
    <li>f5bot.com: sign up with <b><?= e($mc['user']) ?></b> and add your keywords.</li>
    <li>A confirmation mail arrives first. Press Check mail now and it shows up under Needs your click.</li>
  </ol>
</div>
<?php layout_footer();
