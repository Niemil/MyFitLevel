<?php
declare(strict_types=1);

/* Fitocracy research archive: admins, or anyone holding the share link (?k=...). Read-only. */
$dir = APP_ROOT . '/config/fito';
$share = (string)setting('fito_share', '', true);
$k = (string)($_GET['k'] ?? '');
$me = me();
$ok = ($me && !empty($me['is_admin'])) || ($share !== '' && $k !== '' && hash_equals($share, $k));
if (!$ok) {
    http_response_code(404);
    layout_header('Not found');
    echo '<div class="card"><p>Nothing here.</p></div>';
    layout_footer();
    return;
}
$kq = $k !== '' ? '&k=' . rawurlencode($k) : '';

if (isset($_GET['dl']) && in_array($_GET['dl'], ['digest.txt', 'urls.tsv', 'images.zip', 'calib.tsv', 'calib_blocks.txt', 'calib_images.zip', 'ocr.tsv', 'levels_found.tsv', 'sets.tsv'], true) && is_file($dir . '/' . $_GET['dl'])) {
    $f = $dir . '/' . $_GET['dl'];
    header('Content-Type: ' . (substr($_GET['dl'], -4) === '.zip' ? 'application/zip' : 'text/plain; charset=utf-8'));
    header('Content-Disposition: attachment; filename="fitocracy-' . $_GET['dl'] . '"');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}
if (isset($_GET['img'])) {
    $n = basename((string)$_GET['img']);
    $f = $dir . '/images/' . $n;
    if (preg_match('/^[A-Za-z0-9_-]+\.(png|jpe?g|gif|webp)$/i', $n) && is_file($f)) {
        $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
        header('Content-Type: image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
        header('Cache-Control: private, max-age=86400');
        readfile($f);
        exit;
    }
    http_response_code(404);
    exit;
}


/** Compare every known real example with what our model gives now. */
function fa_compare(): array
{
    $out = [];
    foreach (fito_examples() as [$group, $name, $ours, $lb, $reps, $min, $km, $real, $conf, $src]) {
        $ex = row('SELECT * FROM exercises WHERE name = ? AND active = 1', [$ours]);
        $pts = null;
        if ($ex) {
            if ($group === 'cardio') {
                $pts = (int)set_points($ex, ['duration_sec' => (int)round($min * 60), 'distance_m' => (int)round($km * 1000)], 75)['points'];
            } else {
                $pts = (int)set_points($ex, ['weight_kg' => $lb / 2.20462, 'reps' => $reps], 75)['points'];
            }
        }
        $out[] = ['group' => $group, 'name' => $name, 'set' => $group === 'cardio' ? rtrim(rtrim(number_format($min, 1), '0'), '.') . ' min, ' . rtrim(rtrim(number_format($km, 2), '0'), '.') . ' km' : rtrim(rtrim(number_format($lb, 1), '0'), '.') . ' lb × ' . $reps,
                  'real' => $real, 'ours' => $pts, 'diff' => ($pts !== null && $real > 0) ? (int)round(($pts / $real - 1) * 100) : null, 'conf' => $conf, 'src' => $src];
    }
    return $out;
}
$pages = [];
if (is_file($dir . '/digest.txt')) {
    $parts = preg_split('/\n\n===== /', "\n\n" . (string)file_get_contents($dir . '/digest.txt'));
    array_shift($parts);
    foreach ($parts as $p) {
        [$h, $b] = array_pad(explode(" =====\n", $p, 2), 2, '');
        $pages[] = ['head' => trim($h), 'body' => trim($b)];
    }
}
$imgs = is_dir($dir . '/images') ? array_values(array_filter(scandir($dir . '/images'), fn($n) => preg_match('/\.(png|jpe?g|gif|webp)$/i', $n))) : [];

$tabs = ['overview' => 'Overview', 'calibration' => 'Calibration', 'levels' => 'Levels', 'calc' => 'Try it', 'contribute' => 'Add data', 'sources' => 'Sources', 'raw' => 'Raw archive'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? (string)$_GET['tab'] : 'overview';
$lnk = fn(string $t, string $extra = ''): string => '?tab=' . $t . $kq . $extra;

/* ---- classify archived pages ---- */
function fa_category(string $head): string
{
    $u = strtolower($head);
    if (str_contains($u, 'reddit.com')) return 'Reddit';
    if (!str_contains($u, 'fitocracy.com')) return 'Elsewhere on the web';
    if (preg_match('#/blog|blog\.fitocracy#', $u)) return 'Fitocracy blog';
    if (preg_match('#/level#', $u)) return 'Levels';
    if (preg_match('#/achievement|/badge#', $u)) return 'Achievements';
    if (preg_match('#/quest#', $u)) return 'Quests';
    if (preg_match('#/(help|support|faq|how|rules|points|guide)#', $u)) return 'Help and rules';
    return 'Other site pages';
}
function fa_relevance(string $text): int
{
    return preg_match_all('/\b(points?|pts|level|xp|achievement|quest)\b/i', $text);
}
$cats = [];
foreach ($pages as $i => $p) {
    $c = fa_category($p['head']);
    $p['rel'] = fa_relevance($p['body']);
    $p['i'] = $i;
    $cats[$c][] = $p;
}
$catInfo = [
    'Levels'            => ['High', 'The old level list and rules. Best source for level thresholds, if the numbers survived.'],
    'Achievements'      => ['High', 'Names and conditions of the old achievements. Good for badge ideas, no per-set points.'],
    'Quests'            => ['High', 'Quest lines and their steps. Good for quest design.'],
    'Help and rules'    => ['Medium', 'Help pages that describe how points and levels work in words, usually without formulas.'],
    'Other site pages'  => ['Medium', 'General site pages: about, features, marketing text.'],
    'Fitocracy blog'    => ['Low', 'Blog posts. Interesting for history and tone, but they rarely contain point values.'],
    'Reddit'            => ['Low', 'Reddit archive. We searched for point examples and found very little.'],
    'Elsewhere on the web' => ['Medium', 'Forums, blogs and tables by users, such as quoted point values.'],
];

fito_contrib_ensure();
$contribMsg = '';
if (is_post() && ($_POST['do'] ?? '') === 'contrib') {
    csrf_check();
    $kind = ($_POST['kind'] ?? '') === 'set' ? 'set' : 'level';
    $nf = fn(string $k): float => (float)str_replace([',', ' '], ['', ''], str_replace(',', '.', (string)($_POST[$k] ?? '0')));
    $ok2 = rate_ok('fitoc' . client_ip(), 15, 3600);
    if (!$ok2) {
        $contribMsg = 'Too many submissions for now. Please try again later.';
    } elseif ($kind === 'level') {
        $lv = (int)$nf('lv'); $tot = $nf('total'); $nx = $nf('next');
        if ($lv >= 1 && $lv <= 200 && ($tot > 0 || $nx > 0) && $tot < 1e8 && $nx < 1e7) {
            q('INSERT INTO fito_contrib (kind, n1, n2, n3, note, created_at) VALUES (?,?,?,?,?,?)', ['level', $lv, $tot, $nx, clean_text((string)($_POST['note'] ?? ''), 160), now()]);
            rate_hit('fitoc' . client_ip());
            $contribMsg = 'Thank you! Level data added (shown as unverified).';
        } else { $contribMsg = 'Please enter a level and total points (or points to the next level).'; }
    } else {
        $exn = clean_text((string)($_POST['ex'] ?? ''), 80); $wt = $nf('w'); $rp = (int)$nf('reps'); $pt = $nf('pts');
        if ($exn !== '' && $pt > 0 && $pt < 100000 && $rp >= 0 && $rp < 1000 && $wt >= 0 && $wt < 5000) {
            q('INSERT INTO fito_contrib (kind, a, n1, n2, n3, unit, note, created_at) VALUES (?,?,?,?,?,?,?,?)', ['set', $exn, $wt, $rp, $pt, ($_POST['unit'] ?? 'lb') === 'kg' ? 'kg' : 'lb', clean_text((string)($_POST['note'] ?? ''), 160), now()]);
            rate_hit('fitoc' . client_ip());
            $contribMsg = 'Thank you! Set added (shown as unverified).';
        } else { $contribMsg = 'Please enter the exercise, weight, reps and the points shown.'; }
    }
}
$contribLevels = rows("SELECT n1 lv, n2 total, n3 nx, note FROM fito_contrib WHERE kind = 'level' ORDER BY id DESC LIMIT 100");
$contribSets = rows("SELECT a, n1 w, n2 reps, n3 pts, unit, note FROM fito_contrib WHERE kind = 'set' ORDER BY id DESC LIMIT 100");
$stat = is_file($dir . '/status.json') ? json_decode((string)file_get_contents($dir . '/status.json'), true) : null;
$tsvRows = function (string $f, int $max = 40) use ($dir): array {
    $p = $dir . '/' . $f;
    if (!is_file($p)) return [0, []];
    $ln = array_values(array_filter(array_map('rtrim', file($p) ?: [])));
    return [count($ln), array_slice(array_map(fn($l) => explode("\t", $l), $ln), 0, $max)];
};
layout_header('Fitocracy archive', '');
?>
<div class="prose">
  <h1>Fitocracy research archive</h1>
  <p class="muted small">Collected from public pages (Internet Archive, forums, screenshots) for research and preservation. No names or member profiles are kept, only public text and numbers. Images are for reference only. Shared by <?= e(site_name()) ?> as a starting point for anyone building a Fitocracy-style tool.</p>
  <div class="chips" style="margin:8px 0 14px">
    <?php foreach ($tabs as $t => $label): ?><a class="chip<?= $tab === $t ? ' on' : '' ?>" href="<?= e($lnk($t)) ?>"><?= e($label) ?></a><?php endforeach; ?>
  </div>

<?php if ($tab === 'overview'):
    $ex = fito_examples();
    $named = [];
    foreach ($ex as $r) { $named[$r[8]] = ($named[$r[8]] ?? 0) + 1; }
?>
  <?php if ($stat && !empty($stat['phase'])): $age = time() - (int)($stat['t'] ?? 0); $live = $age < 900; ?>
  <div class="card"><b><?= $live ? '🟢 Collecting now' : '⚪ Last run' ?></b> · <?= e((string)$stat['phase']) ?>
    <?php if (!empty($stat['total'])): ?> · <?= (int)$stat['done'] ?> of <?= (int)$stat['total'] ?> (<?= (int)round(100 * (int)$stat['done'] / max(1, (int)$stat['total'])) ?>%)<?php endif; ?>
    <div class="muted small"><?= e((string)($stat['msg'] ?? '')) ?> · updated <?= e(rel_time(date('Y-m-d H:i:s', (int)($stat['t'] ?? 0)))) ?>. <a href="<?= e($lnk('overview')) ?>">Refresh</a></div></div>
  <?php endif; ?>
  <h2>What is here</h2>
  <ul>
    <li><b>Calibration:</b> <?= count($ex) ?> real Fitocracy examples (exercise, weight, reps, points) next to what our points model gives for the same set.</li>
    <li><b>Levels:</b> our level curve and any level thresholds found in the archive.</li>
    <li><b>Try it:</b> a calculator. Enter a set and see our points, and compare with a real number if you have one.</li>
    <li><b>Sources:</b> the <?= count($pages) ?> archived pages grouped by type, with how useful each group is.</li>
    <li><b>Raw archive:</b> every page as text, a URL list and the images. Downloads below.</li>
  </ul>
  <p>
    <?php foreach (['digest.txt' => 'All text', 'urls.tsv' => 'URL list', 'images.zip' => 'Images', 'calib.tsv' => 'Point lines found', 'calib_blocks.txt' => 'Pasted logs', 'calib_images.zip' => 'Screenshots', 'ocr.tsv' => 'OCR results', 'levels_found.tsv' => 'Level lines (OCR)', 'sets.tsv' => 'Sets from archived pages'] as $f => $label): if (is_file($dir . '/' . $f)): ?>
      <a class="btn small ghost" href="?dl=<?= e($f . $kq) ?>"><?= e($label) ?> (<?= (int)(filesize($dir . '/' . $f) / 1024) ?> KB)</a>
    <?php endif; endforeach; ?>
  </p>

  <h2>How good is the data? (our confidence)</h2>
  <div class="card tablewrap"><table class="cmp">
    <thead><tr><th>Source</th><th>Amount</th><th>Confidence</th><th>Why</th></tr></thead>
    <tbody>
      <tr><td>Screenshots of old workout logs</td><td><?= $named['high'] ?? 0 ?> sets</td><td><b>High</b></td><td>Points read directly from the page. But few, and mostly heavy barbell lifts.</td></tr>
      <tr><td>Points quoted in forum and blog posts</td><td><?= $named['medium'] ?? 0 ?> sets</td><td>Medium</td><td>Real numbers, but second hand and sometimes from a different era of the site.</td></tr>
      <tr><td>Remembered examples</td><td><?= $named['low'] ?? 0 ?> set</td><td>Low</td><td>Not verified.</td></tr>
      <tr><td>Archived site pages (levels, quests, help)</td><td><?= count($pages) ?> pages</td><td>High for rules text</td><td>Official wording, but pages contain no per-set points.</td></tr>
      <tr><td>Blog posts and Reddit</td><td><?= count($cats['Fitocracy blog'] ?? []) + count($cats['Reddit'] ?? []) ?> pages</td><td>Low</td><td>Mostly context. Little usable point data.</td></tr>
    </tbody></table></div>
  <p class="muted small">Fitocracy also changed its points over the years, so examples from different years can disagree. That is a limit of any reverse engineering.</p>

  <h2>What we ended up using, and how close it is</h2>
<?php
    $res = fa_compare();
    $grp = [];
    foreach ($res as $r) { if ($r['diff'] !== null && $r['group'] !== 'bodyweight') { $grp[$r['group']][] = abs($r['diff']); } }
    $names = ['compound' => 'Barbell and machine compound lifts', 'light' => 'Very light sets', 'isolation' => 'Isolation (lateral raise)', 'cardio' => 'Cardio'];
?>
  <p>MyFitLevel uses its own formula, built on public exercise science, not a copy: <code>points = coefficient × (estimated 1RM)<sup>1.3</sup> × 0.2085</code>, with the estimated 1RM from the Epley equation. We tuned the scale and the exercise coefficients against the examples above, so heavy lifts come close, and chose to keep the model simple and fair across exercises instead of fitting every outlier.</p>
  <div class="card tablewrap"><table class="cmp">
    <thead><tr><th>Type of set</th><th>Examples</th><th>Average difference</th><th>Verdict</th></tr></thead><tbody>
    <?php foreach ($names as $g => $label): $a = $grp[$g] ?? []; if (!$a) continue; $m = (int)round(array_sum($a) / count($a)); ?>
      <tr><td><?= e($label) ?></td><td><?= count($a) ?></td><td><?= $m ?>%</td><td><?= $m <= 15 ? 'Close' : ($m <= 30 ? 'Roughly right' : 'Off') ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <p class="muted small">"Average difference" is how far our points are from the real Fitocracy points, as a percentage of the real value, averaged. See the Calibration tab for every row.</p>

  <h2>What we are still missing</h2>
  <p>If you have any of these, they would help the most:</p>
  <ul><?php foreach (fito_wanted() as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
  <h2>Ways to find more</h2>
  <ul>
    <li>Wayback Machine: old profile and activity pages of public users (they list exercise, weight, reps and points per set).</li>
    <li>Image search for "fitocracy workout" screenshots, then reading the numbers by eye or with OCR software.</li>
    <li>Reddit and forum posts where people write "I got X points for Y".</li>
  </ul>

<?php elseif ($tab === 'calibration'):
    $res = fa_compare(); $ok15 = $ok25 = $n = 0; $errs = [];
    foreach ($res as $r) { if ($r['diff'] !== null && $r['group'] !== 'bodyweight') { $n++; $a = abs($r['diff']); $errs[] = $a; if ($a <= 15) $ok15++; if ($a <= 25) $ok25++; } }
    sort($errs);
?>
  <h2>Real Fitocracy points vs our model</h2>
  <p>Each row is a set where we know the real points from old Fitocracy. "Ours" is what MyFitLevel calculates today for the same set. Weights are shown in pounds as on the old site. Differences over 15% are in red.</p>
  <?php if ($n): ?><p><b><?= $ok15 ?> of <?= $n ?></b> within 15%, <b><?= $ok25 ?> of <?= $n ?></b> within 25%. Median difference <b><?= (int)round($errs[(int)floor(($n - 1) / 2)]) ?>%</b>.</p><?php endif; ?>
  <div class="card tablewrap"><table class="cmp">
    <thead><tr><th>Exercise</th><th>Set</th><th>Real</th><th>Ours</th><th>Diff</th><th>Confidence</th><th>Source</th></tr></thead><tbody>
    <?php foreach ($res as $r): ?>
      <tr><td><?= e($r['name']) ?></td><td><?= e($r['set']) ?></td><td><?= (int)$r['real'] ?></td>
        <td><?= $r['ours'] === null ? '?' : (int)$r['ours'] ?></td>
        <td style="color:<?= $r['diff'] !== null && abs($r['diff']) > 15 ? 'var(--bad,#d33)' : 'inherit' ?>"><?= $r['diff'] === null ? '' : ($r['diff'] > 0 ? '+' : '') . $r['diff'] . '%' ?></td>
        <td><?= e(ucfirst($r['conf'])) ?></td><td class="muted small"><?= e($r['src']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php [$nOcr, $ocrRows] = $tsvRows('ocr.tsv', 25); [$nSets, $setRows] = $tsvRows('sets.tsv', 25); ?>
  <?php if ($contribSets): ?>
  <h3>Submitted by visitors (unverified)</h3>
  <div class="card tablewrap"><table class="cmp"><thead><tr><th>Exercise</th><th>Set</th><th>Real points</th><th>Note</th></tr></thead><tbody>
    <?php foreach ($contribSets as $c): ?><tr><td><?= e($c['a']) ?></td><td><?= e(rtrim(rtrim((string)$c['w'], '0'), '.')) ?> <?= e($c['unit']) ?> × <?= (int)$c['reps'] ?></td><td><?= (int)$c['pts'] ?></td><td class="muted small"><?= e($c['note']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
  <?php if ($nOcr): ?>
  <h3>Read from screenshots by OCR (unverified, <?= (int)$nOcr ?> lines)</h3>
  <p class="muted small">Automatic text reading is noisy. Check the screenshot before trusting a number. Download the full list in the Overview tab.</p>
  <div class="card tablewrap"><table class="cmp"><thead><tr><th>Image</th><th>Text</th><th>Weight</th><th>Reps</th><th>Points</th></tr></thead><tbody>
    <?php foreach ($ocrRows as $r): ?><tr><td class="small"><?= e($r[0] ?? '') ?></td><td class="small"><?= e($r[1] ?? '') ?></td><td><?= e(($r[2] ?? '') . ' ' . ($r[3] ?? '')) ?></td><td><?= e($r[4] ?? '') ?></td><td><?= e($r[5] ?? '') ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
  <?php if ($nSets): ?>
  <h3>Sets from archived workout pages (numbers only, <?= (int)$nSets ?> lines)</h3>
  <div class="card tablewrap"><table class="cmp"><thead><tr><th>Exercise</th><th>Weight</th><th>Reps</th><th>Points</th></tr></thead><tbody>
    <?php foreach ($setRows as $r): ?><tr><td class="small"><?= e($r[0] ?? '') ?></td><td><?= e(($r[1] ?? '') . ' ' . ($r[2] ?? '')) ?></td><td><?= e($r[3] ?? '') ?></td><td><?= e($r[4] ?? '') ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
  <h3>What the numbers say</h3>
  <ul>
    <li><b>Most barbell sets are close</b> (deadlift, squat, bench at working weights), but the 2010-11 bench examples ran 15-28% below our model, so old Fitocracy probably paid slightly less per set for heavy bench than the later site.</li>
    <li><b>Very light sets paid more on Fitocracy.</b> An empty bar squat gave 36 real points against about 15 from us, which suggests the old site had a floor or a flatter curve for small loads.</li>
    <li><b>Dumbbell isolation paid very little on Fitocracy</b> (5 points for 35 lb lateral raises); our model gives about three times as much. Part of the gap may be that Fitocracy scored one dumbbell while we count both hands.</li>
    <li><b>Overhead press paid about twice what our model gives.</b> One example only, so low certainty.</li>
  </ul>
  <p class="muted small">Only <?= count($res) ?> examples, so this shows direction, not an exact formula. Bodyweight examples are not scored here because the body weight is unknown.</p>

<?php elseif ($tab === 'levels'):
    $table = level_table();
    // try to read thresholds from archived level pages
    $found = [];
    foreach ($pages as $p) {
        if (!preg_match('#level#i', $p['head'])) continue;
        if (preg_match_all('/\blevel\s+(\d{1,3})\b[^\d\n]{0,40}?([\d][\d,\.]{2,9})\s*(?:points|pts|xp)\b/i', $p['body'], $m, PREG_SET_ORDER)) {
            foreach ($m as $x) { $lv = (int)$x[1]; $v = (int)str_replace([',', '.'], '', $x[2]); if ($lv >= 1 && $lv <= 100 && $v > 0) $found[$lv] = $v; }
        }
    }
    ksort($found);
    $mark = [1, 2, 3, 4, 5, 10, 15, 20, 25, 30, 40, 50, 60, 70, 75, 80, 90, 100];
?>
  <h2>Fitocracy levels</h2>
<?php
    $fl = fito_levels();
    $rowsL = [];
    foreach ($fl as [$lv, $tot, $kind, $conf, $src]) { $rowsL[] = [$lv, $tot, $kind, ucfirst($conf), $src]; }
    foreach ($found as $lv => $v) { $rowsL[] = [$lv, $v, 'threshold', 'Medium', 'read automatically from an archived level page']; }
    foreach ($contribLevels as $c) {
        $lv = (int)$c['lv'];
        if ((float)$c['total'] > 0) $rowsL[] = [$lv, (int)$c['total'], 'inside', 'Unverified', 'submitted by a visitor' . ($c['note'] !== '' ? ': ' . $c['note'] : '')];
        if ((float)$c['nx'] > 0 && (float)$c['total'] > 0) $rowsL[] = [$lv + 1, (int)((float)$c['total'] + (float)$c['nx']), 'threshold', 'Unverified', 'submitted by a visitor'];
    }
    [$nLv, $lvRows] = $tsvRows('levels_found.tsv', 60);
    foreach ($lvRows as $r) {
        if (count($r) >= 4 && (int)$r[2] > 0 && (int)$r[3] > 0) {
            if ($r[1] === 'total' && (int)$r[2] > 0) $rowsL[] = [(int)$r[2], (int)$r[3], 'inside', 'OCR, unverified', 'read from a screenshot'];
        }
    }
    usort($rowsL, fn($x, $y) => $x[0] <=> $y[0]);
    $kindTxt = ['threshold' => 'needed to reach this level', 'inside' => 'a member had this many at this level', 'to-next' => 'points still needed for this level'];
?>
  <p>These are real numbers from Fitocracy. Our own curve is further down, for comparison.</p>
  <div class="card tablewrap"><table class="cmp">
    <thead><tr><th>Level</th><th>Fitocracy total points</th><th>What it means</th><th>Confidence</th><th>Source</th><th>Ours at that level</th></tr></thead><tbody>
    <?php foreach ($rowsL as [$lv, $tot, $kind, $conf, $src]): ?>
      <tr><td><?= (int)$lv ?></td><td><b><?= number_format((int)$tot) ?></b></td><td class="small"><?= e($kindTxt[$kind] ?? $kind) ?></td><td><?= e($conf) ?></td><td class="muted small"><?= e($src) ?></td>
        <td><?= isset($table[$lv]) ? number_format($table[$lv]) : '–' ?><?php if (isset($table[$lv]) && $table[$lv] > 0 && $kind === 'threshold'): ?> <span class="muted small">(Fitocracy ×<?= number_format($tot / $table[$lv], 1) ?>)</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rowsL): ?><tr><td colspan="6" class="muted">No Fitocracy level data yet.</td></tr><?php endif; ?></tbody></table></div>
  <p><b>What this tells us:</b> level 39 needed about 452,000 total points on Fitocracy, while our curve reaches level 39 at about 113,000. Fitocracy's levels get steep, roughly four times harder than ours at level 39. Our curve is deliberately easier, so an active lifter reaches level 50 in about a year, but we should note it is not a copy.</p>
  <p><b>Help us fill this in:</b> any Fitocracy profile that shows "Level X" and "Total points" (or "N pts to Level Y") is one more data point. <a href="<?= e($lnk('contribute')) ?>">Add one here</a>. A few dozen across levels 1 to 60 would give a real curve.</p>
  <h2>Our level table (for comparison)</h2>
  <p>MyFitLevel has 100 levels with its own curve: level 2 needs 250 points, level 10 about 7,000, level 30 about 70,000, level 50 about 210,000 and level 100 about 850,000. It was tuned for real life, so an active lifter reaches level 50 in about a year.</p>
  <div class="card tablewrap"><table class="cmp">
    <thead><tr><th>Level</th><th>Rank</th><th>Total points (ours)</th></tr></thead><tbody>
    <?php foreach ($mark as $lv): ?>
      <tr><td><?= $lv ?></td><td><?= e(level_title($lv)) ?></td><td><?= number_format($table[$lv]) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <details><summary>Show all 100 levels</summary>
    <div class="card tablewrap"><table class="cmp"><thead><tr><th>Level</th><th>Total points</th><th>Step</th></tr></thead><tbody>
    <?php $prev = 0; foreach ($table as $lv => $v): ?><tr><td><?= $lv ?></td><td><?= number_format($v) ?></td><td><?= $lv > 1 ? '+' . number_format($v - $prev) : '' ?></td></tr><?php $prev = $v; endforeach; ?>
    </tbody></table></div></details>
  <p class="muted small"><b>Confidence:</b> Fitocracy rows marked High come from a real profile screenshot. Medium rows were extracted automatically from archived pages and can be misread. Unverified rows come from visitors or OCR and should be checked. Our own curve is a design choice, not data.</p>

<?php elseif ($tab === 'calc'):
    $rowsEx = rows("SELECT slug, name, kind FROM exercises WHERE active = 1 AND kind IN ('weight','bodyweight') ORDER BY name");
    $slug = (string)($_GET['ex'] ?? 'barbell-bench-press');
    $w = (float)str_replace(',', '.', (string)($_GET['w'] ?? '135'));
    $unit = ($_GET['unit'] ?? 'lb') === 'kg' ? 'kg' : 'lb';
    $reps = max(1, (int)($_GET['reps'] ?? 5));
    $real = (float)str_replace(',', '.', (string)($_GET['real'] ?? '0'));
    $bw = (float)str_replace(',', '.', (string)($_GET['bw'] ?? '75'));
    $exr = row('SELECT * FROM exercises WHERE slug = ? AND active = 1', [$slug]);
    $ours = null;
    if ($exr) { $ours = (int)set_points($exr, ['weight_kg' => $unit === 'lb' ? $w / 2.20462 : $w, 'reps' => $reps], $bw)['points']; }
?>
  <h2>Try our points model</h2>
  <p>Enter a set and see what MyFitLevel gives. If you know the real Fitocracy points for that set, add them to see how far apart we are.</p>
  <form method="get" class="card"><input type="hidden" name="tab" value="calc"><?php if ($k !== ''): ?><input type="hidden" name="k" value="<?= e($k) ?>"><?php endif; ?>
    <div class="calc">
      <label>Exercise <select name="ex"><?php foreach ($rowsEx as $x): ?><option value="<?= e($x['slug']) ?>"<?= $x['slug'] === $slug ? ' selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></label>
      <label>Weight <input name="w" inputmode="decimal" value="<?= e((string)$w) ?>"></label>
      <label>Unit <select name="unit"><option value="lb"<?= $unit === 'lb' ? ' selected' : '' ?>>lb</option><option value="kg"<?= $unit === 'kg' ? ' selected' : '' ?>>kg</option></select></label>
      <label>Reps <input name="reps" inputmode="numeric" value="<?= (int)$reps ?>"></label>
      <label>Body weight (kg, for bodyweight moves) <input name="bw" inputmode="decimal" value="<?= e((string)$bw) ?>"></label>
      <label>Real Fitocracy points (optional) <input name="real" inputmode="decimal" value="<?= $real > 0 ? e((string)$real) : '' ?>"></label>
    </div>
    <button class="btn" type="submit">Calculate</button>
  </form>
  <?php if ($ours !== null): ?>
    <div class="card"><b style="font-size:1.6rem"><?= $ours ?> points</b> <span class="muted">for <?= e($exr['name']) ?>, <?= e((string)$w) ?> <?= e($unit) ?> × <?= (int)$reps ?></span>
    <?php if ($real > 0): $d = (int)round(($ours / $real - 1) * 100); ?><p>Real: <?= e((string)$real) ?> · difference <b><?= $d > 0 ? '+' : '' ?><?= $d ?>%</b></p><?php endif; ?></div>
  <?php endif; ?>
  <p class="muted small">Dumbbell exercises: enter the weight of one dumbbell. Formula: points = coefficient × (weight × (1 + reps ÷ 30))<sup>1.3</sup> × 0.2085, weight in kg.</p>

<?php elseif ($tab === 'contribute'): ?>
  <h2>Add Fitocracy data</h2>
  <p>Anything real from the old site helps. Please only add numbers you can see on a Fitocracy page. No names or personal details.</p>
  <?php if ($contribMsg): ?><div class="flash ok"><?= e($contribMsg) ?></div><?php endif; ?>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="do" value="contrib"><input type="hidden" name="kind" value="level">
    <h3 style="margin-top:0">A level and its points</h3>
    <p class="muted small">From a profile: "Level 38", "439,309 Total Points", "13,041 pts to Level 39".</p>
    <div class="calc">
      <label>Level <input name="lv" inputmode="numeric" required></label>
      <label>Total points <input name="total" inputmode="numeric"></label>
      <label>Points to next level <input name="next" inputmode="numeric"></label>
      <label>Note (year, where) <input name="note" maxlength="160"></label>
    </div>
    <button class="btn" type="submit">Add level data</button>
  </form>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="do" value="contrib"><input type="hidden" name="kind" value="set">
    <h3 style="margin-top:0">A set and the points it gave</h3>
    <p class="muted small">Dumbbells: the weight of one dumbbell, as logged. Leave weight 0 for bodyweight moves and say the body weight in the note.</p>
    <div class="calc">
      <label>Exercise <input name="ex" maxlength="80" required></label>
      <label>Weight <input name="w" inputmode="decimal" value="0"></label>
      <label>Unit <select name="unit"><option>lb</option><option>kg</option></select></label>
      <label>Reps <input name="reps" inputmode="numeric" required></label>
      <label>Points shown <input name="pts" inputmode="numeric" required></label>
      <label>Note (year, body weight) <input name="note" maxlength="160"></label>
    </div>
    <button class="btn" type="submit">Add set</button>
  </form>

<?php elseif ($tab === 'sources'): ?>
  <h2>What is in the archive</h2>
  <p><?= count($pages) ?> archived pages, grouped by type. The usefulness rating is about finding Fitocracy's points and levels, not about the page being good reading.</p>
  <?php foreach ($catInfo as $c => [$val, $desc]): $list = $cats[$c] ?? []; if (!$list) continue;
      usort($list, fn($a, $b) => $b['rel'] <=> $a['rel']); ?>
    <div class="card">
      <h3 style="margin:0"><?= e($c) ?> <span class="muted small">· <?= count($list) ?> pages · usefulness: <b><?= e($val) ?></b></span></h3>
      <p class="muted small"><?= e($desc) ?></p>
      <?php foreach (array_slice($list, 0, $val === 'Low' ? 5 : 8) as $p):
          $first = trim(preg_replace('/\s+/', ' ', mb_substr($p['body'], 0, 220))); ?>
        <details><summary><b><?= e(preg_replace('#^https?://(www\.)?#', '', preg_replace('/\s*\(archived.*$/', '', $p['head']))) ?></b> <span class="muted small">· <?= (int)$p['rel'] ?> mentions of points/levels</span></summary>
          <p class="small"><?= e($first) ?>…</p>
          <p class="small"><a href="<?= e($lnk('raw', '&q=' . rawurlencode(mb_substr(preg_replace('/\s*\(archived.*$/', '', $p['head']), 0, 60)))) ?>">Open the full text</a></p></details>
      <?php endforeach; ?>
      <?php if (count($list) > ($val === 'Low' ? 5 : 8)): ?><p class="muted small">…and <?= count($list) - ($val === 'Low' ? 5 : 8) ?> more in the raw archive.</p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$pages): ?><p class="muted">No archive pages on this server yet.</p><?php endif; ?>

<?php else: /* raw */
    $q = trim((string)($_GET['q'] ?? ''));
    $shown = $q !== '' ? array_values(array_filter($pages, fn($p) => stripos($p['head'] . ' ' . $p['body'], $q) !== false)) : $pages;
?>
  <h2>Raw archive</h2>
  <p>
    <?php foreach (['digest.txt' => 'All text', 'urls.tsv' => 'URL list', 'images.zip' => 'Images'] as $f => $label): if (is_file($dir . '/' . $f)): ?>
      <a class="btn small ghost" href="?dl=<?= e($f . $kq) ?>"><?= e($label) ?> (<?= (int)(filesize($dir . '/' . $f) / 1024) ?> KB)</a>
    <?php endif; endforeach; ?>
  </p>
  <form method="get"><input type="hidden" name="tab" value="raw"><?php if ($k !== ''): ?><input type="hidden" name="k" value="<?= e($k) ?>"><?php endif; ?>
    <input name="q" value="<?= e($q) ?>" placeholder="Search the archive (e.g. level, achievement, quest)">
  </form>
  <p class="muted small"><?= count($shown) ?> pages<?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?></p>
  <?php foreach ($shown as $p): ?>
    <details class="card"><summary><b><?= e($p['head']) ?></b> <span class="muted small">· <?= e(fa_category($p['head'])) ?></span></summary>
      <pre style="white-space:pre-wrap;font-size:13px"><?= e(mb_substr($p['body'], 0, 6000)) ?></pre></details>
  <?php endforeach; ?>
  <?php if ($imgs && $q === ''): ?>
    <h2>Images (<?= count($imgs) ?>)</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:8px">
      <?php foreach ($imgs as $n): ?><a href="?img=<?= e($n . $kq) ?>"><img loading="lazy" style="max-width:100%;background:#8884" src="?img=<?= e($n . $kq) ?>" alt=""></a><?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
</div>
<?php layout_footer(); ?>
