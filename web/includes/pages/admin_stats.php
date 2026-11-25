<?php
declare(strict_types=1);

function stats_flag(string $cc): string
{
    if (!preg_match('/^[A-Za-z]{2}$/', $cc)) {
        return '🌐';
    }
    $cc = strtoupper($cc);
    return mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65);
}

function stats_country_name(string $cc): string
{
    static $n = ['SE' => 'Sweden', 'US' => 'United States', 'GB' => 'United Kingdom', 'FI' => 'Finland', 'NO' => 'Norway', 'DK' => 'Denmark', 'DE' => 'Germany',
        'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands', 'CA' => 'Canada', 'AU' => 'Australia', 'IN' => 'India', 'BR' => 'Brazil',
        'PH' => 'Philippines', 'PL' => 'Poland', 'IE' => 'Ireland', 'MX' => 'Mexico', 'JP' => 'Japan', 'CN' => 'China', 'RU' => 'Russia', 'SG' => 'Singapore',
        'AE' => 'United Arab Emirates', 'ZA' => 'South Africa', 'NZ' => 'New Zealand', 'IS' => 'Iceland', 'EE' => 'Estonia', 'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria'];
    return $n[strtoupper($cc)] ?? strtoupper($cc);
}

/** Label for a visit's source, used the same way in visitors and signups queries. */
function stats_src_sql(string $p = ''): string
{
    return "CASE WHEN {$p}ref_type='direct' THEN 'Direct / unknown' WHEN {$p}ref_type='internal' THEN 'Own site' WHEN {$p}ref_type='campaign' THEN CONCAT('Campaign: ', {$p}utm) ELSE {$p}ref_host END";
}

function stats_admin_view(): void
{
    stats_ensure();
    $days = in_array((int)($_GET['days'] ?? 7), [1, 7, 30, 90], true) ? (int)($_GET['days'] ?? 7) : 7;
    $sub = in_array($_GET['sub'] ?? '', ['overview', 'sources', 'pages', 'audience', 'privacy'], true) ? $_GET['sub'] : 'overview';
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $W = 'day >= ?';
    $P = [$from];
    $H = "bot = '' AND js = 1";
    $src = stats_src_sql();
    $lnk = fn(string $s, int $d) => url('admin?tab=stats&sub=' . $s . '&days=' . $d);

    $tot = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W", $P);
    $bots = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W AND bot <> ''", $P);
    $views = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W AND $H", $P);
    $uniq = (int)val("SELECT COUNT(DISTINCT vid) FROM stat_visits WHERE $W AND $H", $P);
    $today = (int)val("SELECT COUNT(DISTINCT vid) FROM stat_visits WHERE day = ? AND $H", [date('Y-m-d')]);
    $ret = (int)val("SELECT COUNT(*) FROM (SELECT vid FROM stat_visits WHERE consent = 1 AND $H GROUP BY vid HAVING MAX(day) >= ? AND COUNT(DISTINCT day) > 1) x", $P);
    $newc = (int)val("SELECT COUNT(*) FROM (SELECT vid FROM stat_visits WHERE consent = 1 AND $H GROUP BY vid HAVING MAX(day) >= ? AND COUNT(DISTINCT day) = 1) x", $P);
    $signups = (int)val("SELECT COUNT(*) FROM stat_signups WHERE $W", $P);
    $matched = (int)val("SELECT COUNT(*) FROM stat_signups WHERE $W AND matched = 1", $P);
    $avg = (int)val("SELECT AVG(dwell) FROM stat_visits WHERE $W AND dwell > 0 AND bot = ''", $P);
    $pct = fn(int $a, int $b): string => $b > 0 ? round($a * 100 / $b, $a * 100 / $b < 10 ? 1 : 0) . '%' : '–';
    $nf = fn($n) => fmt_big((float)$n);

    $bar = function (array $rs, string $k, string $unit = '') {
        $max = 1;
        foreach ($rs as $r) { $max = max($max, (int)$r['n']); }
        echo '<div class="hb">';
        foreach ($rs as $r) {
            $nm = $r[$k] === '' ? '(none)' : $r[$k];
            echo '<div class="hbr"><span class="hbl">' . e($nm) . '</span><span class="hbt"><i style="width:' . max(2, (int)round(100 * $r['n'] / $max)) . '%"></i></span><b>' . number_format((int)$r['n']) . e($unit) . '</b></div>';
        }
        if (!$rs) { echo '<p class="muted small">No data yet</p>'; }
        echo '</div>';
    };
    ?>
<div class="sx">
<div class="sx-top">
  <div class="sx-tabs">
    <?php foreach (['overview' => 'Overview', 'sources' => 'Sources', 'pages' => 'Pages', 'audience' => 'Audience', 'privacy' => 'Privacy & log'] as $k => $lbl): ?>
    <a class="<?= $sub === $k ? 'on' : '' ?>" href="<?= e($lnk($k, $days)) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="chips">
    <?php foreach ([1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $lbl): ?>
    <a class="chip <?= $days === $d ? 'on' : '' ?>" href="<?= e($lnk($sub, $d)) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($sub === 'overview'):
    $ser = [];
    foreach (rows("SELECT day, COUNT(*) v, COUNT(DISTINCT vid) u FROM stat_visits WHERE $W AND $H GROUP BY day", $P) as $r) { $ser[$r['day']] = $r; }
    $sg = [];
    foreach (rows("SELECT day, COUNT(*) n FROM stat_signups WHERE $W GROUP BY day", $P) as $r) { $sg[$r['day']] = (int)$r['n']; }
    $pts = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $pts[] = ['d' => $d, 'u' => (int)($ser[$d]['u'] ?? 0), 'v' => (int)($ser[$d]['v'] ?? 0), 's' => $sg[$d] ?? 0];
    }
    $mx = max(1, max(array_column($pts, 'v')));
    $n = count($pts); $Wd = 640; $Ht = 170; $pl = 6; $bw = ($Wd - $pl * 2) / max(1, $n);
    ?>
<div class="sx-kpis">
  <div class="kp"><span>Visitors</span><b><?= $nf($uniq) ?></b><small><?= $today ?> today</small></div>
  <div class="kp"><span>Page views</span><b><?= $nf($views) ?></b><small><?= $uniq > 0 ? number_format($views / $uniq, 1) : '0' ?> per visitor</small></div>
  <div class="kp acc"><span>New members</span><b><?= $nf($signups) ?></b><small><?= $pct($signups, $uniq) ?> of visitors</small></div>
  <div class="kp"><span>Returning</span><b><?= $nf($ret) ?></b><small><?= $pct($ret, $ret + $newc) ?> of cookie-OK visitors</small></div>
  <div class="kp"><span>Bots</span><b><?= $nf($bots) ?></b><small><?= $pct($bots, $tot) ?> of all hits</small></div>
  <div class="kp"><span>Time on page</span><b><?= $avg ?>s</b><small>average</small></div>
</div>
<div class="card sx-chart">
  <h3>Visitors per day</h3>
  <svg viewBox="0 0 <?= $Wd ?> <?= $Ht + 24 ?>" role="img" aria-label="Visitors and page views per day">
    <?php foreach ([0.25, 0.5, 0.75, 1] as $g): $y = $Ht - $g * ($Ht - 14); ?>
    <line x1="0" x2="<?= $Wd ?>" y1="<?= round($y, 1) ?>" y2="<?= round($y, 1) ?>" class="gl"/><text x="<?= $Wd ?>" y="<?= round($y - 2, 1) ?>" text-anchor="end" class="gt"><?= e($nf($mx * $g)) ?></text>
    <?php endforeach; ?>
    <?php foreach ($pts as $i => $p):
        $x = $pl + $i * $bw; $hv = $p['v'] / $mx * ($Ht - 14); $hu = $p['u'] / $mx * ($Ht - 14); ?>
    <g class="col" style="animation-delay:<?= min(900, $i * 18) ?>ms">
      <rect x="<?= round($x + $bw * 0.1, 1) ?>" y="<?= round($Ht - $hv, 1) ?>" width="<?= round($bw * 0.8, 1) ?>" height="<?= round($hv, 1) ?>" class="b1" rx="2"><title><?= e($p['d']) ?>: <?= $p['v'] ?> views, <?= $p['u'] ?> visitors, <?= $p['s'] ?> signups</title></rect>
      <rect x="<?= round($x + $bw * 0.1, 1) ?>" y="<?= round($Ht - $hu, 1) ?>" width="<?= round($bw * 0.8, 1) ?>" height="<?= round($hu, 1) ?>" class="b2" rx="2"/>
      <?php if ($p['s'] > 0): ?><circle cx="<?= round($x + $bw / 2, 1) ?>" cy="<?= round($Ht - $hv - 8, 1) ?>" r="4" class="b3"><title><?= $p['s'] ?> new members</title></circle><?php endif; ?>
    </g>
    <?php if ($n <= 14 || $i % max(1, (int)ceil($n / 10)) === 0): ?>
    <text x="<?= round($x + $bw / 2, 1) ?>" y="<?= $Ht + 16 ?>" text-anchor="middle" class="gt"><?= e(date($n > 31 ? 'j/n' : 'D j', strtotime($p['d']))) ?></text>
    <?php endif; endforeach; ?>
  </svg>
  <div class="sx-leg"><span><i class="l1"></i>Page views</span><span><i class="l2"></i>Visitors</span><span><i class="l3"></i>New member</span></div>
</div>
<div class="sx-two">
  <div class="card"><h3>Where people come from</h3>
    <?php $bar(rows("SELECT CASE ref_type WHEN 'search' THEN 'Search engines' WHEN 'social' THEN 'Social media' WHEN 'other' THEN 'Other websites' WHEN 'campaign' THEN 'Campaign links' ELSE 'Direct / unknown' END AS k, COUNT(DISTINCT vid) n FROM stat_visits WHERE $W AND $H AND ref_type <> 'internal' GROUP BY k ORDER BY n DESC", $P), 'k'); ?>
    <p class="small"><a href="<?= e($lnk('sources', $days)) ?>">All sources and signups per source →</a></p>
  </div>
  <div class="card"><h3>Top countries</h3>
    <?php $cs = rows("SELECT IF(country='','??',country) c, COUNT(DISTINCT vid) n FROM stat_visits WHERE $W AND $H GROUP BY c ORDER BY n DESC LIMIT 8", $P);
    foreach ($cs as &$c) { $c['k'] = stats_flag($c['c'] === '??' ? '' : $c['c']) . ' ' . ($c['c'] === '??' ? 'Unknown' : stats_country_name($c['c'])); } unset($c);
    $bar($cs, 'k'); ?>
  </div>
</div>

<?php elseif ($sub === 'sources'): ?>
<div class="card"><h3>Sources → visitors → members</h3>
  <p class="small muted">Visitors counted per source (people arriving from outside). Members = sign-ups whose first visit came from that source (matched through the same-day visitor hash, or the visitor cookie if they pressed OK). Anonymous: no member is linked to a visit.</p>
  <?php
  $vs = rows("SELECT $src AS k, COUNT(DISTINCT vid) v, COUNT(*) pv FROM stat_visits WHERE $W AND $H AND ref_type <> 'internal' GROUP BY k ORDER BY v DESC LIMIT 25", $P);
  $sgs = [];
  foreach (rows("SELECT " . stats_src_sql() . " AS k, COUNT(*) n FROM stat_signups WHERE $W GROUP BY k", $P) as $r) { $sgs[$r['k']] = (int)$r['n']; }
  $seen = [];
  ?>
  <div class="tablewrap"><table class="tbl sx-t"><tr><th>Source</th><th>Visitors</th><th>Page views</th><th>New members</th><th>Conversion</th></tr>
  <?php foreach ($vs as $r): $m = $sgs[$r['k']] ?? 0; $seen[$r['k']] = 1; ?>
    <tr><td><?= e($r['k']) ?></td><td><?= number_format((int)$r['v']) ?></td><td><?= number_format((int)$r['pv']) ?></td><td><b><?= $m ?></b></td><td><?= $pct($m, (int)$r['v']) ?></td></tr>
  <?php endforeach; foreach ($sgs as $k => $m): if (!isset($seen[$k])): ?>
    <tr><td><?= e($k) ?></td><td>–</td><td>–</td><td><b><?= $m ?></b></td><td>–</td></tr>
  <?php endif; endforeach; if (!$vs && !$sgs): ?><tr><td class="muted">No data yet</td></tr><?php endif; ?>
  </table></div>
  <p class="small muted" style="margin-top:8px">Tip: add <code>?ref=facebook-group</code> to links you share to see exactly which post brings people.</p>
</div>
<div class="sx-two">
  <div class="card"><h3>Sign-ups by source type</h3>
    <?php $bar(rows("SELECT CASE ref_type WHEN 'search' THEN 'Search engines' WHEN 'social' THEN 'Social media' WHEN 'other' THEN 'Other websites' WHEN 'campaign' THEN 'Campaign links' ELSE 'Direct / unknown' END AS k, COUNT(*) n FROM stat_signups WHERE $W GROUP BY k ORDER BY n DESC", $P), 'k'); ?>
  </div>
  <div class="card"><h3>From visit to member</h3>
    <div class="sx-fun">
      <div><b><?= $nf($uniq) ?></b><span>visitors</span></div><div><b><?= $nf($signups) ?></b><span>joined</span></div><div><b><?= $pct($signups, $uniq) ?></b><span>conversion</span></div>
    </div>
    <?php $fv = row("SELECT AVG(pages) p, AVG(mins) m FROM stat_signups WHERE $W AND matched = 1", $P); ?>
    <p class="small muted">Matched to a first visit: <?= $matched ?> of <?= $signups ?>.<?= $matched > 0 ? ' On average they looked at ' . number_format((float)$fv['p'], 1) . ' pages and signed up ' . (int)round((float)$fv['m']) . ' min after arriving.' : '' ?></p>
  </div>
</div>
<div class="card"><h3>Latest members: how they found us</h3>
  <div class="tablewrap"><table class="tbl sx-t"><tr><th>When</th><th>From</th><th>Landed on</th><th>Country</th></tr>
  <?php foreach (rows('SELECT * FROM stat_signups ORDER BY id DESC LIMIT 15') as $r): ?>
    <tr><td class="small"><?= e(rel_time($r['ts'])) ?></td><td><?= e($r['matched'] ? ($r['ref_type'] === 'direct' ? 'Direct / unknown' : ($r['ref_host'] !== '' ? $r['ref_host'] : ($r['utm'] !== '' ? 'Campaign: ' . $r['utm'] : $r['ref_type']))) : 'Unknown (no earlier visit found)') ?></td><td class="small"><?= e($r['landing'] ?: '–') ?></td><td><?= e(stats_flag((string)$r['country']) . ' ' . ($r['country'] ?: '??')) ?></td></tr>
  <?php endforeach; ?>
  </table></div>
</div>

<?php elseif ($sub === 'pages'): ?>
<div class="sx-two">
  <div class="card"><h3>Landing pages</h3>
    <p class="small muted">Where people arrive from outside, with how many of those later joined.</p>
    <?php
    $lp = rows("SELECT path, COUNT(DISTINCT vid) n FROM stat_visits WHERE $W AND $H AND ref_type <> 'internal' GROUP BY path ORDER BY n DESC LIMIT 15", $P);
    $ls = [];
    foreach (rows("SELECT landing, COUNT(*) n FROM stat_signups WHERE $W GROUP BY landing", $P) as $r) { $ls[$r['landing']] = (int)$r['n']; }
    ?>
    <div class="tablewrap"><table class="tbl sx-t"><tr><th>Page</th><th>Visitors</th><th>Joined</th></tr>
    <?php foreach ($lp as $r): ?><tr><td><?= e($r['path']) ?></td><td><?= number_format((int)$r['n']) ?></td><td><b><?= $ls[$r['path']] ?? 0 ?></b></td></tr><?php endforeach; if (!$lp): ?><tr><td class="muted">No data yet</td></tr><?php endif; ?>
    </table></div>
  </div>
  <div class="card"><h3>Most viewed pages</h3>
    <?php $bar(rows("SELECT path AS k, COUNT(*) n FROM stat_visits WHERE $W AND $H GROUP BY path ORDER BY n DESC LIMIT 15", $P), 'k'); ?>
  </div>
</div>

<?php elseif ($sub === 'audience'):
    $sh = (int)val("SELECT COUNT(*) FROM stat_consent WHERE $W AND event = 'shown'", $P);
    $hum = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W AND bot = '' AND human = 1", $P);
    $likely = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W AND bot = '' AND human = 0 AND js = 1", $P);
    $nojs = max(0, $tot - $bots - $hum - $likely);
    ?>
<div class="sx-kpis">
  <div class="kp"><span>New (cookie OK)</span><b><?= $nf($newc) ?></b><small>first time seen</small></div>
  <div class="kp"><span>Returning (cookie OK)</span><b><?= $nf($ret) ?></b><small>seen on 2+ days</small></div>
  <div class="kp"><span>All visitors</span><b><?= $nf($uniq) ?></b><small>others counted per day</small></div>
</div>
<p class="small muted">Returning visitors can only be recognised for people who pressed OK on the cookie banner. Everyone else gets a daily-changing anonymous hash, so they count as new each day. That is what keeps the stats GDPR-friendly.</p>
<div class="sx-two">
  <div class="card"><h3>Countries</h3>
    <?php $cs = rows("SELECT IF(country='','??',country) c, COUNT(DISTINCT vid) n FROM stat_visits WHERE $W AND $H GROUP BY c ORDER BY n DESC LIMIT 20", $P);
    foreach ($cs as &$c) { $c['k'] = stats_flag($c['c'] === '??' ? '' : $c['c']) . ' ' . ($c['c'] === '??' ? 'Unknown' : stats_country_name($c['c'])); } unset($c);
    $bar($cs, 'k'); ?>
  </div>
  <div class="card"><h3>Human or bot?</h3>
    <?php $bar([['k' => 'Human (interacted)', 'n' => $hum], ['k' => 'Likely human', 'n' => $likely], ['k' => 'No JavaScript / unknown', 'n' => $nojs], ['k' => 'Known bot', 'n' => $bots]], 'k'); ?>
    <h3 style="margin-top:14px">Bots seen</h3>
    <?php $bar(rows("SELECT bot AS k, COUNT(*) n FROM stat_visits WHERE $W AND bot<>'' GROUP BY k ORDER BY n DESC LIMIT 12", $P), 'k'); ?>
  </div>
</div>
<div class="sx-two">
  <div class="card"><h3>Devices</h3>
    <?php $bar(rows("SELECT device AS k, COUNT(DISTINCT vid) n FROM stat_visits WHERE $W AND $H GROUP BY k ORDER BY n DESC", $P), 'k'); ?>
    <h3 style="margin-top:14px">Browsers</h3>
    <?php $bar(rows("SELECT browser AS k, COUNT(DISTINCT vid) n FROM stat_visits WHERE $W AND $H GROUP BY k ORDER BY n DESC LIMIT 8", $P), 'k'); ?>
  </div>
  <div class="card"><h3>Systems</h3>
    <?php $bar(rows("SELECT os AS k, COUNT(DISTINCT vid) n FROM stat_visits WHERE $W AND $H GROUP BY k ORDER BY n DESC LIMIT 8", $P), 'k'); ?>
    <h3 style="margin-top:14px">Languages</h3>
    <?php $bar(rows("SELECT IF(lang='','??',lang) AS k, COUNT(DISTINCT vid) n FROM stat_visits WHERE $W AND $H GROUP BY k ORDER BY n DESC LIMIT 8", $P), 'k'); ?>
  </div>
</div>

<?php else:
    $sh = (int)val("SELECT COUNT(*) FROM stat_consent WHERE $W AND event = 'shown'", $P);
    $ac = (int)val("SELECT COUNT(*) FROM stat_consent WHERE $W AND event = 'accept'", $P);
    $rj = (int)val("SELECT COUNT(*) FROM stat_consent WHERE $W AND event = 'reject'", $P);
    $sig = (int)val("SELECT COUNT(*) FROM stat_visits WHERE $W AND consent = 3 AND bot = ''", $P);
    ?>
<div class="card">
  <h3>Privacy model</h3>
  <p class="small muted">No tracking cookies before consent. Without consent a visitor is an anonymous hash that changes every day; nothing is linked to a member. Sign-ups are stored as anonymous rows (source, landing page, country, device). Cookie-OK visitors get a random id and their IP is kept <?= STATS_IP_DAYS ?> days; all visit rows are deleted after <?= STATS_KEEP_DAYS ?> days. Do Not Track and Global Privacy Control are respected. Admins are never counted.</p>
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
      <td class="small"><?= e($who) ?> · <?= e(stats_flag((string)$r['country']) . ' ' . ($r['country'] ?: '??')) ?> · <?= e(trim($r['device'] . ' ' . $r['browser'])) ?><?= $r['ip'] ? ' · <b>' . e($r['ip']) . '</b>' : '' ?></td>
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
<?php endif; ?>
</div>
<?php
}
