<?php
declare(strict_types=1);

$admin = require_admin();
require_once APP_ROOT . '/includes/mentions.php';
mentions_ensure();
if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'queries') {
        set_setting('mentions_queries', mb_substr((string)($_POST['queries'] ?? ''), 0, 1200));
        flash('Search words saved.');
        redirect('admin/mentions');
    }
    if ($do === 'runnow') {
        flash('Search done: ' . mentions_search_run());
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
<?php $last = (int)setting('mentions_last_run', '0'); ?>
<div class="card">
  <h3>Search</h3>
  <p class="small muted">Searches Hacker News, Reddit, Lemmy and Stack Exchange for the words below. Last run: <?= $last ? e(rel_time(date('Y-m-d H:i:s', $last))) . ' · ' . e((string)setting('mentions_last_info', '')) : 'never' ?>. It also runs by itself about once a day through your existing cron job.</p>
  <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="do" value="runnow"><button class="btn" type="submit">Run search now</button></form>
  <p class="small muted">Takes up to half a minute.</p>
  <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="do" value="queries">
    <label class="small muted">Search words, one per line (max 12)</label>
    <textarea name="queries" rows="8" style="width:100%"><?= e(implode("\n", mentions_queries())) ?></textarea>
    <p><button class="btn" type="submit">Save search words</button></p>
  </form>
</div>
<?php layout_footer();
