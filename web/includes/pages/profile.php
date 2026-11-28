<?php
declare(strict_types=1);

$viewer = me();   // may be null: public profiles can be seen without logging in
$vid = $viewer ? (int)$viewer['id'] : 0;
$p = row('SELECT * FROM users WHERE username = ? AND is_banned = 0', [$args[0]]);
if (!$p) {
    http_response_code(404);
    layout_header('Not found');
    echo '<div class="card center"><h2>Member not found</h2></div>';
    layout_footer();
    exit;
}
$pid = (int)$p['id'];
$isMe = $viewer && $pid === $vid;
if ($isMe && is_post() && ($_POST['do'] ?? '') === 'ba_save') {
    csrf_check();
    $b = (int)($_POST['before'] ?? 0); $a = (int)($_POST['after'] ?? 0);
    $own = fn(int $i) => $i > 0 && row("SELECT 1 FROM images WHERE id = ? AND user_id = ? AND kind = 'gallery'", [$i, $pid]);
    if (($_POST['clear'] ?? '') !== '') {
        set_setting('ba:' . $pid, '');
        flash('Before and after removed.');
    } elseif ($b !== $a && $own($b) && $own($a)) {
        set_setting('ba:' . $pid, $b . ',' . $a);
        flash('Before and after saved.');
    } else {
        flash('Pick two different photos.', 'err');
    }
    redirect('u/' . $p['username'] . '#photos');
}
$stats = user_stats($pid);
$prog = level_progress((int)$p['xp']);
$isFollowing = $viewer && (bool)row('SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?', [$vid, $pid]);

$badges = rows('SELECT code, earned_at FROM user_badges WHERE user_id = ? ORDER BY earned_at DESC', [$pid]);
$defs = badge_defs();
$questsDone = (int)val('SELECT COUNT(*) FROM user_quests WHERE user_id = ?', [$pid]);

$showWorkouts = can_see_member($p, $viewer);
$list = [];
$more = false;
$nextBefore = null;
if ($showWorkouts) {
    $before = isset($_GET['before']) && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', (string)$_GET['before']) ? (string)$_GET['before'] : null;
    $feed = feed_items($viewer, 'user', $before, 12, $pid);
    $list = $feed['items'];
    $more = $feed['more'];
    $nextBefore = $list ? end($list)['time'] : null;
}
$top = rows('SELECT e.name, COUNT(*) c FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.user_id = ? GROUP BY e.id, e.name ORDER BY c DESC LIMIT 5', [$pid]);
$groups = rows('SELECT g.slug, g.name FROM group_members m JOIN groups_ g ON g.id = m.group_id WHERE m.user_id = ? ORDER BY m.joined_at DESC LIMIT 6', [$pid]);

$heatDays = in_array((int)($_GET['heat'] ?? 30), [7, 30, 90], true) ? (int)($_GET['heat'] ?? 30) : 30;
$heat = $showWorkouts ? muscle_heat($pid, $heatDays) : [];
$heatMax = $heat ? max($heat) : 0;
$heatData = ['heat' => [], 'tips' => []];
foreach ($heat as $m => $v) {
    $heatData['heat'][$m] = round($v / $heatMax, 3);
    $heatData['tips'][$m] = (string)(round($v * 2) / 2) . ' sets';
}
arsort($heat);

layout_header(display_name($p), $isMe ? 'me' : '');
?>
<?php
$photoTok = $p['avatar_token'] ?: null;
$gallery = $showWorkouts ? rows("SELECT id, token FROM images WHERE user_id = ? AND kind = 'gallery' ORDER BY id DESC LIMIT 24", [$pid]) : [];
$back = 'u/' . $p['username'];
?>
<?php $cs = cosm_all()[$p['username']] ?? []; ?>
<section class="phero">
  <div class="pphoto<?= !empty($cs['frame']) ? ' pfrm frm-' . e($cs['frame']) : '' ?>">
    <?php if ($photoTok): ?>
      <a href="<?= e(img_url($photoTok)) ?>" data-lightbox><img alt="<?= e(display_name($p)) ?>" src="<?= e(img_url($photoTok)) ?>"></a>
    <?php else: ?>
      <div class="pph" style="background:<?= e(avatar_color(display_name($p))) ?>"><?= e(mb_strtoupper(mb_substr(display_name($p), 0, 1))) ?></div>
    <?php endif; ?>
    <?php if ($isMe): ?>
    <form method="post" action="<?= e(url('act/avatar')) ?>" enctype="multipart/form-data" data-autosubmit class="pchange">
      <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
      <label class="btn small">📷 <?= $photoTok ? 'Change photo' : 'Add profile photo' ?>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photos data-max="1" hidden></label>
    </form>
    <?php endif; ?>
  </div>
  <div class="pinfo">
    <?php if (!empty($cs['banner'])): ?><div class="pbanner bn-<?= e($cs['banner']) ?>"></div><?php endif; ?>
    <h1><span class="<?= !empty($cs['namefx']) ? 'nfx nfx-' . e($cs['namefx']) : '' ?>"><?= e(display_name($p)) ?></span> <?= level_chip((int)$prog['level']) ?><?= role_badge($p) ?></h1>
    <div class="muted">@<?= e($p['username']) ?> · joined <?= e(date('M Y', strtotime($p['created_at']))) ?></div>
    <?= xp_bar((int)$p['xp']) ?>
    <?php if ($p['bio']): ?><p class="pbio"><?= e($p['bio']) ?></p><?php endif; ?>
    <div class="pbtns">
      <?php if ($isMe): ?>
        <a class="btn ghost small" href="<?= e(url('settings')) ?>">Edit profile</a>
        <?php if ($photoTok): ?>
        <form method="post" action="<?= e(url('act/avatar_remove')) ?>" class="inl" data-confirm="Remove your profile photo?">
          <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn ghost small" type="submit">Remove photo</button></form>
        <?php endif; ?>
      <?php elseif (!$viewer): ?>
        <a class="btn small" href="<?= e(url('login')) ?>">Log in to follow</a>
      <?php else: ?>
        <form method="post" action="<?= e(url('act/follow')) ?>" data-ajax class="inl">
          <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $pid ?>">
          <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
          <button class="btn small <?= $isFollowing ? 'ghost' : '' ?>" type="submit" data-followbtn><?= $isFollowing ? 'Following' : 'Follow' ?></button>
        </form>
        <a class="btn small ghost" href="<?= e(url('messages/' . $p['username'])) ?>"><?= icon('chat') ?> Message</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="pband">
    <div><b><?= (int)$prog['level'] ?></b><span>Level</span></div>
    <div><b><?= number_format((int)$p['xp']) ?></b><span>Total points</span></div>
    <div><b><?= $stats['workouts'] ?></b><span>Workouts</span></div>
    <div><b><?= count($badges) ?></b><span>Achievements</span></div>
    <div><b><?= $stats['followers'] ?></b><span>Followers</span></div>
    <div><b><?= $stats['streak'] ?></b><span>Day streak</span></div>
  </div>
</section>
<div class="card charcard" style="padding:6px 0"><div class="charstage" style="min-height:220px"><?= fito_dressed(fito_look((int)$p['id']), 210) ?></div></div>
<nav class="ptabs">
  <a href="#feed">Feed</a><a href="#photos">Photos</a><a href="#ach">Achievements</a><a href="#stats">Stats</a>
</nav>

<?php
$vu = ($viewer['unit'] ?? 'kg') === 'lb' ? 'lb' : 'kg';
$calDays = $byWeek = $lifts = [];
if ($showWorkouts) {
    foreach (rows('SELECT DATE(performed_at) d, SUM(points) p FROM workouts WHERE user_id = ? AND performed_at >= ? GROUP BY d', [$pid, date('Y-m-d', strtotime('-365 days'))]) as $r) { $calDays[$r['d']] = (int)$r['p']; }
    $wk = [];
    for ($i = 25; $i >= 0; $i--) { $wk[date('o-\\WW', strtotime("monday this week -$i weeks"))] = 0; }
    foreach (rows('SELECT performed_at, points FROM workouts WHERE user_id = ? AND performed_at >= ?', [$pid, date('Y-m-d', strtotime('monday this week -25 weeks'))]) as $r) {
        $k = date('o-\\WW', strtotime($r['performed_at'])); if (isset($wk[$k])) { $wk[$k] += (int)$r['points']; }
    }
    foreach ($wk as $k => $v) { $byWeek[] = [$k, $v]; }
    $topEx = rows("SELECT e.id, e.name FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.user_id = ? AND e.kind IN ('weight') AND s.is_warmup = 0 AND s.weight_kg > 0 GROUP BY e.id, e.name ORDER BY COUNT(DISTINCT s.workout_id) DESC LIMIT 6", [$pid]);
    foreach ($topEx as $te) {
        $pts = [];
        foreach (rows('SELECT DATE(w.performed_at) d, MAX(s.weight_kg * (1 + s.reps / 30)) e FROM workout_sets s JOIN workouts w ON w.id = s.workout_id WHERE s.user_id = ? AND s.exercise_id = ? AND s.is_warmup = 0 AND s.weight_kg > 0 AND s.reps > 0 GROUP BY w.id, d ORDER BY w.performed_at DESC LIMIT 40', [$pid, $te['id']]) as $r) {
            array_unshift($pts, [date('j M', strtotime($r['d'])), ($vu === 'lb' ? (float)$r['e'] * LB_PER_KG : (float)$r['e'])]);
        }
        $lifts[] = ['name' => $te['name'], 'pts' => $pts];
    }
}
?>
<?php if ($showWorkouts): ?>
<div class="card" id="charts">
  <h4>Training charts</h4>
  <details class="fold" open><summary>Training calendar <small class="muted">last 12 months</small></summary><div class="calwrap"><?= chart_calendar($calDays) ?></div>
    <div class="calleg"><span>less</span><i class="c0"></i><i class="c1"></i><i class="c2"></i><i class="c3"></i><i class="c4"></i><span>more</span></div></details>
  <details class="fold"><summary>Points per week <small class="muted">last 26 weeks</small></summary><?= chart_bars($byWeek) ?></details>
  <?php foreach ($lifts as $li): ?>
  <details class="fold"><summary><?= e($li['name']) ?> <small class="muted">est. one-rep max (<?= $vu ?>)</small></summary><?= chart_line($li['pts'], ' ' . $vu) ?></details>
  <?php endforeach; ?>
  <?php if (!$lifts): ?><p class="muted small">Lift graphs appear here once you have logged weighted exercises.</p><?php endif; ?>
</div>
<?php endif; ?>

<div class="card" id="stats">
  <h4>Stats</h4>
  <div class="statgrid">
    <div><b><?= e(fmt_big(($viewer['unit'] ?? 'kg') === 'lb' ? $stats['volume_kg'] * LB_PER_KG : $stats['volume_kg'])) ?></b><span><?= e($viewer['unit'] ?? 'kg') ?> lifted</span></div>
    <div><b><?= $stats['prs'] ?></b><span>PRs</span></div>
    <div><b><?= (int)$questsDone ?></b><span>quests done</span></div>
    <div><b><?= (int)$stats['following'] ?></b><span>following</span></div>
  </div>
</div>

<?php
$baPair = [];
if ($showWorkouts && preg_match('/^(\d+),(\d+)$/', (string)setting('ba:' . $pid, ''), $bm)) {
    $baPair = [rows("SELECT id, token, created_at FROM images WHERE id = ? AND user_id = ? AND kind = 'gallery'", [(int)$bm[1], $pid])[0] ?? null,
               rows("SELECT id, token, created_at FROM images WHERE id = ? AND user_id = ? AND kind = 'gallery'", [(int)$bm[2], $pid])[0] ?? null];
    if (!$baPair[0] || !$baPair[1]) { $baPair = []; }
}
?>
<?php if ($baPair || ($isMe && count($gallery) >= 2)): ?>
<div class="card" id="beforeafter">
  <h4>Before &amp; after</h4>
  <?php if ($baPair): ?>
  <div class="ba" data-ba>
    <img class="baimg" alt="Before" src="<?= e(img_url($baPair[0]['token'])) ?>">
    <img class="baimg baafter" alt="After" src="<?= e(img_url($baPair[1]['token'])) ?>">
    <span class="balab l">Before · <?= e(date('j M Y', strtotime($baPair[0]['created_at']))) ?></span><span class="balab r">After · <?= e(date('j M Y', strtotime($baPair[1]['created_at']))) ?></span>
    <div class="baline"></div>
    <input type="range" min="0" max="100" value="50" class="barange" aria-label="Drag to compare before and after">
  </div>
  <p class="muted small">Drag the slider to compare.</p>
  <?php endif; ?>
  <?php if ($isMe && count($gallery) >= 2): ?>
  <details<?= $baPair ? '' : ' open' ?>><summary class="small"><?= $baPair ? 'Change photos' : 'Choose your before and after photos' ?></summary>
  <form method="post" class="baform"><?= csrf_field() ?><input type="hidden" name="do" value="ba_save">
    <p class="small muted">Photos are listed newest first. Pick the older one as Before.</p>
    <div class="bapick"><?php foreach (['before' => 'Before', 'after' => 'After'] as $fk => $fl): ?>
      <fieldset><legend><?= $fl ?></legend>
      <?php foreach ($gallery as $gi): ?><label class="bathumb"><input type="radio" name="<?= $fk ?>" value="<?= (int)$gi['id'] ?>" <?= $baPair && (int)$baPair[$fk === 'before' ? 0 : 1]['id'] === (int)$gi['id'] ? 'checked' : '' ?>><img loading="lazy" alt="" src="<?= e(img_url($gi['token'], true)) ?>"></label><?php endforeach; ?>
      </fieldset><?php endforeach; ?></div>
    <button class="btn small" type="submit">Save</button>
    <?php if ($baPair): ?><button class="btn small ghost danger" name="clear" value="1" type="submit">Remove comparison</button><?php endif; ?>
  </form></details>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($gallery || $isMe): ?>
<div class="card" id="photos">
  <h4>Photos <span class="muted">(<?= count($gallery) ?>)</span></h4>
  <?php if ($gallery): ?>
  <div class="gallery">
    <?php foreach ($gallery as $gi): ?>
      <div class="gal">
        <a href="<?= e(img_url($gi['token'])) ?>" data-lightbox><img loading="lazy" alt="Photo" src="<?= e(img_url($gi['token'], true)) ?>"></a>
        <?php if ($isMe): ?>
        <form method="post" action="<?= e(url('act/gallery_del')) ?>" data-confirm="Delete this photo?"><?= csrf_field() ?>
          <input type="hidden" name="back" value="<?= e($back . '#photos') ?>"><input type="hidden" name="image_id" value="<?= (int)$gi['id'] ?>">
          <button class="galx" type="submit" aria-label="Delete photo">×</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php elseif ($isMe): ?><p class="muted small">Add progress pics or gym shots. They follow your profile privacy setting.</p><?php endif; ?>
  <?php if ($isMe && count($gallery) < 24): ?>
  <form method="post" action="<?= e(url('act/gallery_add')) ?>" enctype="multipart/form-data" data-autosubmit>
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back . '#photos') ?>">
    <label class="btn small">＋ Add photos <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-photos data-max="6" hidden></label>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($showWorkouts): ?>
<div class="card heatcard center">
  <h3>Muscle heat map</h3>
  <div class="heatrange">
    <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $lbl): ?>
      <a class="chip<?= $heatDays === $d ? ' on' : '' ?>" href="<?= e(url('u/' . $p['username'])) ?>?heat=<?= $d ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($heat): ?>
    <div class="bodybox" id="heatbox"></div>
    <div class="heatscale"><span>less</span><i></i><span>more</span></div>
    <div class="heatlist">
      <?php foreach ($heat as $m => $v): ?><span><?= e(muscle_regions()[$m] ?? $m) ?> <b><?= e((string)(round($v * 2) / 2)) ?></b></span><?php endforeach; ?>
    </div>
    <p class="muted small">Working sets in the last <?= $heatDays ?> days (warm-ups don't count). Main muscles count fully, helpers count half. Cardio isn't included.</p>
    <script type="application/json" id="heat-data"><?= json_encode($heatData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <?php else: ?>
    <p class="muted">No strength sets logged in this period yet. Track a workout and your muscles light up here.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="cols">
<section class="main">
  <?php if ($isMe && !isset($before)) { post_composer($viewer, 'u/' . $p['username']); } ?>
  <h3 id="feed">Activity</h3>
  <?php if (!$showWorkouts): ?>
    <div class="card center muted"><?= ($p['visibility'] ?? '') === 'members' ? 'This member shares their activity with logged-in members only. <a href="' . e(url('login')) . '">Log in</a> to see it.' : 'This member keeps their activity for followers only.' ?></div>
  <?php elseif (!$list): ?>
    <div class="card center muted">Nothing here yet.</div>
  <?php endif; ?>
  <?php event_prefetch($list, $viewer); foreach ($list as $it) { render_feed_item($it, $viewer, true); } ?>
  <?php if ($more && $nextBefore): ?>
    <p class="center"><a class="btn ghost" href="<?= e(url('u/' . $p['username'] . '?before=' . urlencode($nextBefore))) ?>">Older</a></p>
  <?php endif; ?>
</section>
<aside class="side">
  <div class="card" id="ach">
    <h4>Achievements <span class="muted">(<?= count($badges) ?>/<?= count($defs) ?>)</span></h4>
    <?php if (!$badges): ?><p class="muted small">No achievements yet.</p><?php endif; ?>
    <div class="badgerow">
      <?php foreach (array_slice($badges, 0, 12) as $b): if (!isset($defs[$b['code']])) continue; $d = $defs[$b['code']]; ?>
        <span class="badge" title="<?= e($d[0] . ': ' . $d[1]) ?>"><?= $d[2] ?></span>
      <?php endforeach; ?>
    </div>
    <?php if ($isMe): ?><a class="small" href="<?= e(url('badges')) ?>">See all achievements</a><?php endif; ?>
  </div>
  <?php $str = $showWorkouts ? strength_levels($pid, $p['bodyweight_kg'] !== null ? (float)$p['bodyweight_kg'] : null) : []; ?>
  <?php if ($str): ?>
  <div class="card">
    <h4>Strength levels</h4>
    <?php foreach ($str as $sl): ?>
    <div class="strow tier<?= (int)$sl['tier'] + 1 ?>">
      <div class="kv"><span><?= e($sl['lift']) ?></span><b><?= e($sl['name']) ?></b></div>
      <div class="xpbar"><i style="width:<?= (int)$sl['pct'] ?>%"></i></div>
      <div class="xpnote">Est. max <?= e(fmt_weight($sl['kg'], $viewer['unit'] ?? 'kg')) ?> · <?= e(number_format($sl['ratio'], 2)) ?>× bodyweight<?= $sl['next_kg'] ? ' · next tier ' . e(fmt_weight($sl['next_kg'], $viewer['unit'] ?? 'kg')) : '' ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php elseif ($isMe && $p['bodyweight_kg'] === null): ?>
  <div class="card"><h4>Strength levels</h4><p class="muted small">Add your bodyweight in <a href="<?= e(url('settings')) ?>">Settings</a> to see how your big lifts rank, from Beginner to Elite.</p></div>
  <?php endif; ?>
  <?php if ($top): ?>
  <div class="card">
    <h4>Favourite exercises</h4>
    <?php foreach ($top as $t): ?><div class="kv"><span><?= e($t['name']) ?></span><b><?= (int)$t['c'] ?> sets</b></div><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($groups): ?>
  <div class="card">
    <h4>Groups</h4>
    <?php foreach ($groups as $g): ?><div><a href="<?= e(url('g/' . $g['slug'])) ?>"><?= e($g['name']) ?></a></div><?php endforeach; ?>
  </div>
  <?php endif; ?>
</aside>
</div>
<?php layout_footer($isMe ? 'me' : '', $heat ? ['body.js', 'heat.js', 'charts.js', 'compare.js'] : ['charts.js', 'compare.js']); ?>
