<?php
declare(strict_types=1);

$u = me();   // guests may view public workouts
$uid = $u ? (int)$u['id'] : 0;
$wid = (int)$args[0];

$w = row('SELECT w.*, us.username, us.display_name, us.avatar_token, us.level, us.visibility FROM workouts w JOIN users us ON us.id = w.user_id WHERE w.id = ? AND us.is_banned = 0', [$wid]);
if (!$w) {
    http_response_code(404);
    layout_header('Not found');
    echo '<div class="card center"><h2>Workout not found</h2></div>';
    layout_footer();
    exit;
}
if (!can_see_member($w, $u)) {
    http_response_code(403);
    layout_header('Private');
    echo '<div class="card center"><h2>This workout is private</h2><p class="muted">' . ($u ? 'The member shares it with their followers only.' : '<a href="' . e(url('login')) . '">Log in</a> to see it.') . '</p></div>';
    layout_footer();
    exit;
}
$list = attach_workout_data([$w], $uid);
$w = $list[0];
$comments = rows('SELECT c.*, us.username, us.display_name, us.avatar_token FROM comments c JOIN users us ON us.id = c.user_id WHERE c.workout_id = ? ORDER BY c.id', [$wid]);
$propsBy = rows('SELECT us.username, us.display_name, us.avatar_token FROM props p JOIN users us ON us.id = p.user_id WHERE p.workout_id = ? ORDER BY p.created_at DESC LIMIT 12', [$wid]);

$done = null;
if ($u && !empty($_SESSION['complete']) && (int)$_SESSION['complete']['wid'] === $wid && (int)$w['user_id'] === $uid) {
    $done = $_SESSION['complete'];
    unset($_SESSION['complete']);
}
layout_header('Workout', $u ? 'feed' : '');
?>
<div class="narrowcol">
<?php if ($done): $pr = level_progress((int)$u['xp']); ?>
<section class="card complete">
  <?= mascot('cheer', 84, 'nomotion') ?>
  <div class="cbadge"><span>Workout complete</span></div>
  <h2>Nice work!</h2>
  <div class="cpts"><b><?= number_format($done['points']) ?></b> pts</div>
  <p class="muted">Level <?= (int)$pr['level'] ?> · <?= e(level_title((int)$pr['level'])) ?><?= empty($pr['max']) ? ' · ' . number_format($pr['need'] - $pr['into']) . ' pts to Level ' . ((int)$pr['level'] + 1) : ' · max level 🔱' ?></p>
  <?= xp_bar((int)$u['xp']) ?>
  <?php if ($done['level_up']): ?><p class="cwin">🎉 Level up! You are now level <?= (int)$done['level_up'] ?>.</p><?php endif; ?>
  <?php foreach ($done['quest_steps'] ?? [] as $qs): ?><p class="cwin">⚔️ <?= e($qs['quest']) ?>: <b><?= e($qs['task']) ?></b> (+<?= (int)$qs['reward'] ?> pts)</p><?php endforeach; ?>
  <?php foreach ($done['quest_done'] ?? [] as $qd): ?><p class="cwin">🏆 Quest complete: <b><?= e($qd['name']) ?></b> (+<?= (int)$qd['reward'] ?> pts bonus)</p><?php endforeach; ?>
  <?php foreach ($done['quests'] as $qd): ?><p class="cwin">🎖️ Milestone reached: <b><?= e($qd['name']) ?></b> (+<?= (int)$qd['reward'] ?> pts)</p><?php endforeach; ?>
  <?php foreach ($done['badges'] as $bd): ?><p class="cwin"><?= e($bd['icon']) ?> Achievement unlocked: <b><?= e($bd['name']) ?></b></p><?php endforeach; ?>
</section>
<?php endif; ?>
<?php workout_card($w, $u, true); ?>
<?php if ($u && (int)$w['user_id'] === $uid):
    $unit = $u['unit'] ?? 'kg';
    $shareEx = [];
    foreach (array_slice($w['exercises'], 0, 5) as $ex) {
        $bestS = null; $bestV = -1.0;
        foreach ($ex['sets'] as $s0) {
            if (!empty($s0['is_warmup'])) continue;
            $v0 = (float)($s0['weight_kg'] ?? 0) * (int)($s0['reps'] ?? 1) + (int)($s0['points'] ?? 0) / 1000;
            if ($v0 > $bestV) { $bestV = $v0; $bestS = $s0; }
        }
        if ($bestS === null) continue;
        $bestS['kind'] = $ex['kind'];
        $shareEx[] = ['n' => (string)$ex['name'], 's' => fmt_set($bestS, $unit, $w['unit'] ?? null) . ($unit !== 'kg' || $bestS['weight_kg'] === null ? '' : ' kg'), 'pr' => !empty($bestS['is_pr'])];
    }
    $shareData = ['title' => (string)($w['title'] ?: 'Workout'), 'pts' => (int)$w['points'], 'date' => date('j M Y', strtotime((string)$w['performed_at'])),
        'vol' => fmt_big((float)$w['volume_kg'] * ($unit === 'lb' ? LB_PER_KG : 1)) . ' ' . $unit, 'sets' => (int)$w['set_count'], 'ex' => $shareEx, 'user' => (string)$w['username']];
    $svgShare = preg_replace('/(<svg[^>]*>)/', '$1' . fito_defs(true), fito('cheer', 400, 'sh'), 1);
    $svgShare = str_replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" ', $svgShare);
?>
<div class="card sharebox" id="sharebox" data-share="<?= e(json_encode($shareData, JSON_UNESCAPED_UNICODE)) ?>">
  <b>Share this workout</b>
  <p class="muted small">Makes a picture you can post anywhere. Nothing is uploaded.</p>
  <button type="button" class="btn" id="sharebtn">Make picture</button>
  <img id="shareimg" alt="Workout picture" hidden>
  <template id="sharefito"><?= $svgShare ?></template>
</div>
<?php endif; ?>

<?php if ($propsBy): ?>
  <p class="muted small">Props from: <?php
    echo implode(', ', array_map(fn($p) => '<a href="' . e(url('u/' . $p['username'])) . '">' . e(display_name($p)) . '</a>', $propsBy));
  ?></p>
<?php endif; ?>

<div class="card" id="comments">
  <h4><?= pluralize(count($comments), 'comment') ?></h4>
  <?php foreach ($comments as $c): ?>
    <div class="comment">
      <a href="<?= e(url('u/' . $c['username'])) ?>"><?= uavatar($c, 32) ?></a>
      <div class="grow">
        <a class="uname" href="<?= e(url('u/' . $c['username'])) ?>"><?= e(display_name($c)) ?></a>
        <span class="muted small"><?= e(rel_time($c['created_at'])) ?></span>
        <div><?= nl2br(e($c['body'])) ?></div>
      </div>
      <?php if ($u && ((int)$c['user_id'] === $uid || is_staff($u) || (int)$w['user_id'] === $uid)): ?>
      <form method="post" action="<?= e(url('act/comment_delete')) ?>" class="inl">
        <?= csrf_field() ?><input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
        <input type="hidden" name="back" value="<?= e(url('workout/' . $wid) . '#comments') ?>">
        <button class="linkbtn" title="Delete comment"><?= icon('trash') ?></button>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($u): ?>
  <form method="post" action="<?= e(url('act/comment')) ?>" class="commentform">
    <?= csrf_field() ?><input type="hidden" name="workout_id" value="<?= $wid ?>">
    <input type="hidden" name="back" value="<?= e(url('workout/' . $wid) . '#comments') ?>">
    <input name="body" maxlength="500" placeholder="Write a comment…" required>
    <button class="btn small" type="submit">Post</button>
  </form>
  <?php else: ?><p class="muted small"><a href="<?= e(url('login')) ?>">Log in</a> to comment.</p><?php endif; ?>
</div>

<?php if ($u && (int)$w['user_id'] === $uid): ?>
<form method="post" action="<?= e(url('act/workout_delete')) ?>" class="center" data-confirm="Delete this workout? Its points will be removed.">
  <?= csrf_field() ?><input type="hidden" name="workout_id" value="<?= $wid ?>">
  <button class="btn danger ghost small" type="submit"><?= icon('trash') ?> Delete workout</button>
</form>
<?php endif; ?>
</div>
<?php layout_footer('feed', ['sharecard.js']); ?>
