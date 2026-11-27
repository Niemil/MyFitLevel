<?php
declare(strict_types=1);

$admin = require_admin();
$f = (string)($_GET['f'] ?? 'all');
$f = in_array($f, ['all', 'error', 'warn', 'security'], true) ? $f : 'all';

$ready = true;
try {
    $cnt = rows("SELECT level, COUNT(*) c, SUM(n) total FROM app_log WHERE created_at >= ? GROUP BY level", [date('Y-m-d H:i:s', time() - 86400)]);
    $list = rows('SELECT * FROM app_log' . ($f === 'all' ? '' : ' WHERE level = ?') . ' ORDER BY id DESC LIMIT 200', $f === 'all' ? [] : [$f]);
} catch (Throwable $e) {
    $ready = false;
    $cnt = [];
    $list = [];
}
$by = [];
foreach ($cnt as $c) {
    $by[$c['level']] = (int)$c['total'];
}
$fmt = static function (array $l): string {
    $when = $l['created_at'];
    $n = (int)$l['n'] > 1 ? ' x' . (int)$l['n'] : '';
    return $when . ' | ' . strtoupper((string)$l['level']) . ' | ' . $l['kind'] . ' | ' . $l['message'] . $n . ' | ' . ($l['place'] !== '' ? $l['place'] . ' | ' : '') . $l['url'] . ' | u' . ($l['user_id'] ?: '-') . ' | ' . $l['ip'] . ' | ' . $l['ua'];
};
$dump = implode("\n", array_map($fmt, array_slice($list, 0, 60)));
layout_header('Site log', '');
?>
<h1 class="pagetitle">Site log</h1>
<p class="muted small"><a href="<?= e(url('admin')) ?>">Back to admin</a> · Kept for 30 days. Same identical rows within a minute are merged (x3 = three times). No passwords, keys or search words are stored. PHP's own error log is in <code>logs/php-error.log</code> on the server, outside the public folder.</p>

<div class="card">
  <div class="row" style="gap:1.2rem;flex-wrap:wrap">
    <span>Last 24 h:</span>
    <b>Errors <?= (int)($by['error'] ?? 0) ?></b>
    <b>Warnings <?= (int)($by['warn'] ?? 0) ?></b>
    <b>Security <?= (int)($by['security'] ?? 0) ?></b>
    <span class="muted">Info <?= (int)($by['info'] ?? 0) ?></span>
  </div>
  <div class="tabs" style="margin-top:.6rem">
    <?php foreach (['all' => 'All', 'error' => 'Errors', 'warn' => 'Warnings', 'security' => 'Security'] as $k => $lab): ?>
      <a class="<?= $f === $k ? 'on' : '' ?>" href="<?= e(url('admin/logs?f=' . $k)) ?>"><?= e($lab) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!$ready || !$list): ?>
  <div class="card"><p class="muted"><?= $ready ? 'Nothing logged yet.' : 'The log table is created the first time something is logged.' ?></p></div>
<?php else: ?>
<div class="card">
  <h3>Copy the latest 60 lines</h3>
  <textarea readonly rows="8" style="width:100%;font-family:monospace;font-size:.72rem" onclick="this.select()"><?= e($dump) ?></textarea>
  <p><button type="button" class="btn ghost" data-copy="<?= e($dump) ?>">Copy</button></p>
</div>
<div class="card">
  <h3>Latest <?= count($list) ?> rows</h3>
  <?php foreach ($list as $l): ?>
    <div class="small" style="margin:.55rem 0;word-break:break-word;border-top:1px solid rgba(128,128,128,.18);padding-top:.45rem">
      <b class="<?= $l['level'] === 'error' ? 'bad' : ($l['level'] === 'security' ? 'warn' : '') ?>"><?= e(strtoupper((string)$l['level'])) ?></b>
      <code><?= e($l['kind']) ?></code>
      <span class="muted"><?= e($l['created_at']) ?><?= (int)$l['n'] > 1 ? ' · x' . (int)$l['n'] : '' ?></span><br>
      <?= e($l['message']) ?><br>
      <span class="muted"><?= e(($l['place'] !== '' ? $l['place'] . ' · ' : '') . $l['url'] . ' · ' . ($l['user_id'] ? 'user #' . (int)$l['user_id'] : 'guest') . ' · ' . $l['ip'] . ' · ' . $l['ua']) ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php layout_footer('', ['copy.js']); ?>
