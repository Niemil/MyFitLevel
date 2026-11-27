<?php
declare(strict_types=1);

$admin = require_admin();
require_once APP_ROOT . '/includes/mentions.php';
mentions_ensure();
if (is_post()) {
    csrf_check();
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
$key = mentions_key();
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
<div class="card">
  <h3>Watcher setup</h3>
  <p class="small muted">The key lets your script send results here. Keep it private.</p>
  <textarea readonly rows="2" id="mkey" style="width:100%"><?= e($key) ?></textarea>
  <p><button class="btn small" type="button" data-copy="<?= e($key) ?>">Copy key</button></p>
  <p class="small muted">Script: <code>docs/pi/mentions_watch.py</code> in the repo.</p>
</div>
<?php layout_footer('', ['copy.js']);
