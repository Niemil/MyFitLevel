<?php
declare(strict_types=1);

$u = me();
$uid = $u ? (int)$u['id'] : 0;
$gid = (int)$args[0];
$g = row('SELECT g.*, o.username AS owner_name, o.display_name AS owner_display FROM gyms g JOIN users o ON o.id = g.owner_id WHERE g.id = ?', [$gid]);
if (!$g || !gym_can_view($g, $u)) {
    http_response_code(404);
    layout_header('Not found');
    echo '<div class="card center"><h2>Gym not found</h2><p class="muted">It may be private or deleted.</p></div>';
    layout_footer();
    exit;
}

if (is_post() && $u) {
    csrf_check();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'save') {
        q('INSERT IGNORE INTO gym_saved (user_id, gym_id) VALUES (?, ?)', [$uid, $gid]);
        flash('Saved. It now shows in your gym list when you track a workout.');
    } elseif ($act === 'unsave') {
        q('DELETE FROM gym_saved WHERE user_id = ? AND gym_id = ?', [$uid, $gid]);
        flash('Removed from your gyms.');
    } elseif ($act === 'default') {
        q('UPDATE users SET default_gym_id = ? WHERE id = ?', [$gid, $uid]);
        q('INSERT IGNORE INTO gym_saved (user_id, gym_id) VALUES (?, ?)', [$uid, $gid]);
        flash('This is now your default gym.');
    }
    redirect('gym/' . $gid);
}

$items = rows('SELECT * FROM gym_items WHERE gym_id = ? ORDER BY cat, name, id', [$gid]);
$byCat = [];
foreach ($items as $it) {
    $byCat[$it['cat']][] = $it;
}
$unit = $u['unit'] ?? 'kg';
$isSaved = $u && (bool)val('SELECT 1 FROM gym_saved WHERE user_id = ? AND gym_id = ?', [$uid, $gid]);
$isDefault = $u && (int)($u['default_gym_id'] ?? 0) === $gid;
$members = (int)val('SELECT COUNT(DISTINCT user_id) FROM workouts WHERE gym_id = ?', [$gid]);

[$vs, $vp] = visibility_sql($u);
$ws = rows("SELECT w.*, us.username, us.display_name, us.avatar_token, us.level, us.visibility FROM workouts w JOIN users us ON us.id = w.user_id
            WHERE w.gym_id = ? AND us.is_banned = 0 AND $vs ORDER BY w.performed_at DESC LIMIT 10", array_merge([$gid], $vp));
$ws = attach_workout_data($ws, $uid);

$mapQ = trim($g['address'] . ' ' . $g['city']);

$isGym = $g['kind'] === 'gym' && (int)$g['is_public'] === 1;
$place = trim($g['city']);
$gTitle = $g['name'] . ($place !== '' ? ' gym in ' . $place : ' gym') . ': equipment, weights & workouts';
$eqNames = array_slice(array_values(array_unique(array_column($items, 'name'))), 0, 8);
$gDesc = $g['name'] . ($place !== '' ? ' in ' . $place : '') . ($g['address'] !== '' ? ' (' . $g['address'] . ')' : '') . '. '
    . ($eqNames ? 'Equipment: ' . implode(', ', $eqNames) . '. ' : '')
    . ($members ? pluralize($members, 'member') . ' track workouts here on MyFitLevel. ' : '')
    . 'Log your training at this gym and earn points.';
$gSchema = [];
if ($isGym) {
    $biz = ['@context' => 'https://schema.org', '@type' => 'ExerciseGym', 'name' => $g['name'], 'url' => site_url('gym/' . $gid)];
    if ($g['address'] !== '' || $place !== '') {
        $biz['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $g['address'], 'addressLocality' => $place]);
    }
    if ($g['lat'] !== null && $g['lng'] !== null) {
        $biz['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float)$g['lat'], 'longitude' => (float)$g['lng']];
    }
    if ($g['website'] !== '') {
        $biz['sameAs'] = [$g['website']];
    }
    if ($eqNames) {
        $biz['amenityFeature'] = array_map(fn($n) => ['@type' => 'LocationFeatureSpecification', 'name' => $n, 'value' => true], $eqNames);
    }
    $gSchema[] = $biz;
    $gSchema[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_url('')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Gyms', 'item' => site_url('gyms')],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $g['name'], 'item' => site_url('gym/' . $gid)],
    ]];
}
layout_header($gTitle, 'gyms', ['index' => $isGym, 'path' => 'gym/' . $gid, 'desc' => mb_substr($gDesc, 0, 300), 'schema' => $gSchema]);
?>
<div class="narrowcol">
<section class="card gymhead">
  <div class="gymico big"><?= $g['kind'] === 'home' ? '🏠' : '🏋️' ?></div>
  <div class="grow">
    <h1 class="pagetitle"><?= e($g['name']) ?><?= $place !== '' ? ' <span class="muted" style="font-weight:500">· ' . e($place) . '</span>' : '' ?></h1>
    <div class="muted small">
      <?= $g['kind'] === 'home' ? 'Home gym' : 'Gym' ?><?= $g['city'] !== '' ? ' · ' . e($g['city']) : '' ?>
      <?= !$g['is_public'] ? ' · private' : '' ?>
      <?= $members ? ' · ' . pluralize($members, 'member') . ' trained here' : '' ?>
    </div>
    <div class="small muted">Added by <a href="<?= e(url('u/' . $g['owner_name'])) ?>"><?= e($g['owner_display'] ?: $g['owner_name']) ?></a></div>
  </div>
</section>

<?php if ($g['address'] !== '' || $g['website'] !== '' || $g['info']): ?>
<section class="card">
  <?php if ($g['address'] !== ''): ?><p>📍 <?= e($g['address']) ?><?= $g['city'] !== '' ? ', ' . e($g['city']) : '' ?>
    · <a href="https://www.openstreetmap.org/search?query=<?= e(rawurlencode($mapQ)) ?>" target="_blank" rel="noopener noreferrer">Show on map</a></p><?php endif; ?>
  <?php if ($g['website'] !== ''): ?><p>🔗 <a href="<?= e($g['website']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e(preg_replace('#^https?://#', '', $g['website'])) ?></a></p><?php endif; ?>
  <?php if ($g['info']): ?><p><?= nl2br(e($g['info'])) ?></p><?php endif; ?>
</section>
<?php endif; ?>

<?php if (!$u && $isGym): ?>
<section class="card center"><h3>Train at <?= e($g['name']) ?>?</h3><p class="muted">Log your workouts here, earn points and level up with the people who train at this gym.</p><a class="btn" href="<?= e(url('register')) ?>">Join free</a></section>
<?php endif; ?>
<?php if ($u): ?>
<div class="gymactions">
  <form method="post" class="inl"><?= csrf_field() ?>
    <?php if ((int)$g['owner_id'] !== $uid): ?>
      <input type="hidden" name="act" value="<?= $isSaved ? 'unsave' : 'save' ?>">
      <button class="btn small <?= $isSaved ? 'ghost' : '' ?>" type="submit"><?= $isSaved ? '✓ Saved' : '+ Save to my gyms' ?></button>
    <?php endif; ?>
  </form>
  <?php if (!$isDefault): ?>
  <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="act" value="default"><button class="btn ghost small" type="submit">Make default</button></form>
  <?php else: ?><span class="muted small">Your default gym</span><?php endif; ?>
  <?php if (gym_can_edit($g, $u)): ?><a class="btn ghost small" href="<?= e(url('gym/' . $gid . '/edit')) ?>">Edit</a><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($byCat): ?>
<section class="card">
  <h3>Equipment</h3>
  <?php foreach (GYM_CATS as $k => $lbl): if (empty($byCat[$k])) continue; ?>
    <h4><?= e($lbl) ?></h4>
    <?php foreach ($byCat[$k] as $it): $w = gym_weights_display($it['weights'], $unit); ?>
      <div class="gitem"><div class="grow"><b><?= e($it['name']) ?></b>
        <?php if ($w): ?><div class="small muted"><?= e(implode(', ', array_slice($w, 0, 60))) ?> <?= e($unit) ?></div><?php endif; ?>
        <?php if ($it['note'] !== ''): ?><div class="small muted"><?= e($it['note']) ?></div><?php endif; ?></div></div>
    <?php endforeach; ?>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<h3 class="sechead">Recent workouts here</h3>
<?php if (!$ws): ?><div class="card muted">No workouts logged here yet.</div><?php endif; ?>
<?php foreach ($ws as $w) workout_compact($w, $u); ?>
</div>
<?php layout_footer('gyms'); ?>
