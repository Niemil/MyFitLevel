<?php
declare(strict_types=1);

$u = me();
$uid = $u ? (int)$u['id'] : 0;
$search = trim((string)($_GET['q'] ?? ''));

$mine = !$u ? [] : rows("SELECT g.*, (SELECT COUNT(*) FROM workouts w WHERE w.gym_id = g.id AND w.user_id = ?) AS my_n
              FROM gyms g WHERE g.owner_id = ? ORDER BY g.kind = 'home' DESC, g.name", [$uid, $uid]);
$saved = !$u ? [] : rows("SELECT g.*, (SELECT COUNT(*) FROM workouts w WHERE w.gym_id = g.id AND w.user_id = ?) AS my_n
               FROM gyms g JOIN gym_saved s ON s.gym_id = g.id AND s.user_id = ?
               WHERE g.owner_id <> ? AND g.is_public = 1 ORDER BY g.name", [$uid, $uid, $uid]);

$found = [];
if ($search !== '') {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
    $found = rows("SELECT g.*, (SELECT COUNT(DISTINCT w.user_id) FROM workouts w WHERE w.gym_id = g.id) AS members
                   FROM gyms g WHERE g.is_public = 1 AND g.kind = 'gym' AND (g.name LIKE ? OR g.city LIKE ?)
                   ORDER BY members DESC, g.name LIMIT 30", [$like, $like]);
} else {
    $found = rows("SELECT g.*, (SELECT COUNT(DISTINCT w.user_id) FROM workouts w WHERE w.gym_id = g.id) AS members
                   FROM gyms g WHERE g.is_public = 1 AND g.kind = 'gym'
                   ORDER BY members DESC, g.id DESC LIMIT 60");
}

function gym_row(array $g): void
{
    ?>
  <a class="gymrow" href="<?= e(url('gym/' . $g['id'])) ?>">
    <span class="gymico"><?= $g['kind'] === 'home' ? '🏠' : '🏋️' ?></span>
    <span class="grow"><b><?= e($g['name']) ?></b>
      <span class="muted small"><?= $g['city'] !== '' ? ' · ' . e($g['city']) : '' ?><?= $g['kind'] === 'home' ? ' · home gym' : '' ?><?= !$g['is_public'] ? ' · private' : '' ?><?= isset($g['members']) ? ' · ' . pluralize((int)$g['members'], 'member') : '' ?></span>
    </span>
  </a>
<?php
}

layout_header('Gyms and fitness centres', 'gyms', [
    'index' => $search === '', 'path' => 'gyms',
    'desc' => 'Find your gym on MyFitLevel: equipment lists, weights, members and recent workouts for local gyms. Add yours free and earn points for every workout you log there.',
    'schema' => [['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Gyms on MyFitLevel', 'url' => site_url('gyms')]],
]);
?>
<div class="narrowcol">
<div class="logtop"><h1 class="pagetitle">Gyms</h1>
  <?php if ($u): ?><a class="btn small" href="<?= e(url('gym/new')) ?>">+ New gym</a><?php else: ?><a class="btn small" href="<?= e(url('register')) ?>">Join free</a><?php endif; ?></div>
<p class="muted">Add the place you train and pick it when you log a workout. Gyms are created once and shared, so a friend can just pick yours.</p>

<?php if ($u): ?>
<section class="card">
  <h3>My gyms</h3>
  <?php if (!$mine): ?>
    <p class="muted">None yet. <a href="<?= e(url('gym/new')) ?>">Create a gym</a> or <a href="<?= e(url('gym/new?kind=home')) ?>">a home gym</a>.</p>
  <?php endif; ?>
  <?php foreach ($mine as $g) gym_row($g); ?>
</section>
<?php endif; ?>

<?php if ($saved): ?>
<section class="card"><h3>Saved gyms</h3><?php foreach ($saved as $g) gym_row($g); ?></section>
<?php endif; ?>

<section class="card">
  <h3>Find a gym by name or town</h3>
  <form method="get" class="commentform">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Name or town" maxlength="60">
    <button class="btn small" type="submit">Search</button>
  </form>
  <?php if (!$found): ?><p class="muted">No published gyms match yet.</p><?php endif; ?>
  <?php foreach ($found as $g) gym_row($g); ?>
</section>
</div>
<?php layout_footer('gyms'); ?>
