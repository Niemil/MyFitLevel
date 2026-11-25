<?php
declare(strict_types=1);

function stats_admin_view(): void
{
    stats_ensure();
    $days = in_array((int)($_GET['days'] ?? 7), [1, 7, 30, 90], true) ? (int)($_GET['days'] ?? 7) : 7;
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $W = 'day >= ?';
    $P = [$from];
    $human = "bot = '' AND (human = 1 OR js = 1)";
    $tot = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W", $P);
    $bots = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W AND bot <> ''", $P);
    $hum = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W AND bot = '' AND human = 1", $P);
    $likely = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W AND bot = '' AND human = 0 AND js = 1", $P);
    $nojs = $tot - $bots - $hum - $likely;
    $uniq = (int)val("SELECT COUNT(DISTINCT vid) FROM stat_visits WHERE $W AND bot = '' AND js = 1", $P);
    $today = (int)val("SELECT COUNT(DISTINCT vid) FROM stat_visits WHERE day = ? AND bot = '' AND js = 1", [date('Y-m-d')]);
    $avg = (int)val("SELECT AVG(dwell) FROM stat_visits WHERE $W AND dwell > 0 AND bot = ''", $P);
    $sh = (int)val("SELECT COUNT(*) FROM stat_consent WHERE $W AND event = 'shown'", $P);
    $ac = (int)val("SELECT COUNT(*) FROM stat_consent WHERE $W AND event = 'accept'", $P);
    $rj = (int)val("SELECT COUNT(*) FROM stat_consent WHERE $W AND event = 'reject'", $P);
    $sig = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W AND consent = 3 AND bot = ''", $P);
    $pct = fn(int $a, int $b): string => $b > 0 ? round($a * 100 / $b) . '%' : '–';
    $list = function (string $sql, array $p = []) {
        return rows($sql, $p);
    };
    $bar = function (array $rs, string $k, string $label = '') {
        $max = 1;
        foreach ($rs as $r) { $max = max($max, (int)$r['n']); }
        echo '<table class="tbl">';
        foreach ($rs as $r) {
            $nm = $r[$k] === '' ? '(none)' : $r[$k];
            echo '<tr><td>' . e($nm) . '</td><td style="width:45%"><span class="sbar" style="width:' . max(2, (int)round(100 * $r['n'] / $max)) . '%"></span></td><td class="small">' . (int)$r['n'] . '</td></tr>';
        }
        if (!$rs) { echo '<tr><td class="muted">No data yet</td></tr>'; }
        echo '</table>';
    };
    ?>
<div class="chips" style="margin-bottom:10px">
  <?php foreach ([1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $lbl): ?>
  <a class="chip <?= $days === $d ? 'on' : '' ?>" href="<?= e(url('admin?tab=stats&days=' . $d)) ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>
<div class="sgrid">
  <div class="card"><b><?= number_format($tot) ?></b><span class="muted small">page views</span></div>
  <div class="card"><b><?= number_format($uniq) ?></b><span class="muted small">visitors (<?= $today ?> today)</span></div>
  <div class="card"><b><?= number_format($hum + $likely) ?></b><span class="muted small">likely people</span></div>
  <div class="card"><b><?= number_format($bots) ?></b><span class="muted small">known bots</span></div>
  <div class="card"><b><?= $avg ?>s</b><span class="muted small">avg time on page</span></div>
</div>
<div class="card">
  <h3>Human or bot?</h3>
  <p class="small muted">Human = interacted (scroll, tap, key). Likely human = JavaScript ran but no interaction yet. No JS = unknown or a simple bot. Visitors are counted per day for people who have not said OK, so a returning person on another day counts again.</p>
  <?php $bar([['k' => 'Human', 'n' => $hum], ['k' => 'Likely human', 'n' => $likely], ['k' => 'No JavaScript / unknown', 'n' => $nojs], ['k' => 'Known bot', 'n' => $bots]], 'k'); ?>
</div>
<div class="card">
  <h3>Cookie choice</h3>
  <table class="tbl">
    <tr><td>Banner shown</td><td><?= $sh ?></td><td></td></tr>
    <tr><td>Pressed OK</td><td><?= $ac ?></td><td class="small"><?= $pct($ac, $sh) ?></td></tr>
    <tr><td>Pressed No thanks</td><td><?= $rj ?></td><td class="small"><?= $pct($rj, $sh) ?></td></tr>
    <tr><td>No answer yet</td><td><?= max(0, $sh - $ac - $rj) ?></td><td class="small"><?= $pct(max(0, $sh - $ac - $rj), $sh) ?></td></tr>
    <tr><td>Browser privacy signal (DNT/GPC, no banner)</td><td><?= $sig ?></td><td class="small">page views</td></tr>
  </table>
</div>
<div class="card"><h3>Where from</h3>
  <?php $bar($list("SELECT CASE WHEN ref_type='direct' THEN 'Direct / unknown' WHEN ref_type='internal' THEN 'Own site' WHEN ref_type='campaign' THEN CONCAT('Campaign: ', utm) ELSE ref_host END AS k, COUNT(*) n FROM stat_visits WHERE $W AND bot='' GROUP BY k ORDER BY n DESC LIMIT 15", $P), 'k'); ?>
  <p class="small muted" style="margin-top:8px">Tip: add <code>?ref=facebook-group</code> to links you share to see exactly which post brings people.</p>
</div>
<div class="card"><h3>Countries</h3>
  <?php $bar($list("SELECT IF(country='','Unknown',country) AS k, COUNT(*) n FROM stat_visits WHERE $W AND bot='' GROUP BY k ORDER BY n DESC LIMIT 15", $P), 'k'); ?>
</div>
<div class="card"><h3>Top pages</h3>
  <?php $bar($list("SELECT path AS k, COUNT(*) n FROM stat_visits WHERE $W AND bot='' GROUP BY path ORDER BY n DESC LIMIT 15", $P), 'k'); ?>
</div>
<div class="card"><h3>Devices, browsers, systems</h3>
  <?php $bar($list("SELECT device AS k, COUNT(*) n FROM stat_visits WHERE $W AND bot='' GROUP BY k ORDER BY n DESC", $P), 'k');
  $bar($list("SELECT browser AS k, COUNT(*) n FROM stat_visits WHERE $W AND bot='' GROUP BY k ORDER BY n DESC LIMIT 8", $P), 'k');
  $bar($list("SELECT os AS k, COUNT(*) n FROM stat_visits WHERE $W AND bot='' GROUP BY k ORDER BY n DESC LIMIT 8", $P), 'k'); ?>
</div>
<div class="card"><h3>Bots seen</h3>
  <?php $bar($list("SELECT bot AS k, COUNT(*) n FROM stat_visits WHERE $W AND bot<>'' GROUP BY k ORDER BY n DESC LIMIT 12", $P), 'k'); ?>
</div>
<div class="card tablewrap"><h3>Latest visits</h3>
  <p class="small muted">IP addresses are only stored for visitors who pressed OK, and are removed after <?= STATS_IP_DAYS ?> days.</p>
  <table class="tbl">
    <tr><th>When</th><th>Page</th><th>From</th><th>Who</th></tr>
    <?php foreach (rows('SELECT * FROM stat_visits ORDER BY id DESC LIMIT 40') as $r):
        $who = $r['bot'] !== '' ? '🤖 ' . $r['bot'] : ($r['human'] ? '🙂 human' : ($r['js'] ? '🙂 likely' : '❓ no JS')); ?>
    <tr>
      <td class="small"><?= e(rel_time($r['ts'])) ?></td>
      <td class="small"><?= e($r['path']) ?></td>
      <td class="small"><?= e($r['ref_host'] !== '' ? $r['ref_host'] : ($r['utm'] !== '' ? $r['utm'] : $r['ref_type'])) ?></td>
      <td class="small"><?= e($who) ?> · <?= e($r['country'] ?: '??') ?> · <?= e(trim($r['device'] . ' ' . $r['browser'])) ?><?= $r['ip'] ? ' · <b>' . e($r['ip']) . '</b>' : '' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<div class="card"><h3>Country data</h3>
  <?php $geo = (int)val('SELECT COUNT(*) FROM geo_ranges'); ?>
  <p class="small muted">Country comes from Cloudflare's header if you use it, otherwise from a local IP-to-country table (<?= number_format($geo) ?> ranges loaded). Download the free “IP to Country Lite” CSV from <b>db-ip.com/db/download/ip-to-country-lite</b>, unzip it, and upload it here. It is licensed CC BY 4.0, so the privacy page credits DB-IP.</p>
  <form method="post" enctype="multipart/form-data" class="inl">
    <?= csrf_field() ?><input type="hidden" name="do" value="geo_import">
    <input type="file" name="geo" accept=".csv,text/csv" required> <button class="btn" type="submit">Import</button>
  </form>
</div>
<?php
}
