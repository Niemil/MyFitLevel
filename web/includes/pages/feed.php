<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
$followsAny = (int)val('SELECT COUNT(*) FROM follows WHERE follower_id = ?', [$uid]) > 0;
$tab = $_GET['tab'] ?? ($followsAny ? 'following' : 'everyone');
$tab = $tab === 'following' ? 'following' : 'everyone';
$before = isset($_GET['before']) && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', (string)$_GET['before']) ? (string)$_GET['before'] : null;
$per = 15;
$feed = feed_items($u, $tab, $before, $per);
$list = $feed['items'];
$more = $feed['more'];
$nextBefore = $list ? end($list)['time'] : null;

$stats = user_stats($uid);
$prog = level_progress((int)$u['xp']);
$suggest = [];
if (!$before) {
    $suggest = rows('SELECT id, username, display_name, level, xp FROM users
                     WHERE id <> ? AND is_banned = 0 AND id NOT IN (SELECT followee_id FROM follows WHERE follower_id = ?)
                     ORDER BY xp DESC LIMIT 4', [$uid, $uid]);
}

define('GAME_CSS', true);
layout_header('Feed', 'feed');
?>
<div class="cols">
<section class="main">
  <div class="tabs">
    <a class="<?= $tab === 'following' ? 'on' : '' ?>" href="<?= e(url('feed?tab=following')) ?>">Following</a>
    <a class="<?= $tab === 'everyone' ? 'on' : '' ?>" href="<?= e(url('feed?tab=everyone')) ?>">Everyone</a>
  </div>

  <?php if (!$before) { verify_banner($u); game_today_card($u); fito_coach_card($u); onboard_card($u); week_card($u); game_feed_card($u); game_steps_bar($u); today_card($u); } ?>
  <?php if (!$before) { post_composer($u, 'feed?tab=' . $tab); } ?>

  <?php if (!$list): ?>
    <div class="card center">
      <h3>Nothing here yet</h3>
      <p class="muted">Write a post, log your first workout, or follow some members to see theirs.</p>
      <a class="btn" href="<?= e(url('log')) ?>">Track a workout</a>
    </div>
  <?php endif; ?>

  <?php render_feed_items($list, $u); ?>

  <?php if ($more && $nextBefore): ?>
    <p class="center"><a class="btn ghost" href="<?= e(url('feed?tab=' . $tab . '&before=' . urlencode($nextBefore))) ?>">Older</a></p>
  <?php endif; ?>
  <?php if ($before): ?>
    <p class="center"><a href="<?= e(url('feed?tab=' . $tab)) ?>">← Newest</a></p>
  <?php endif; ?>
</section>

<aside class="side">
  <div class="card me">
    <div class="mehead"><?= uavatar($u, 52) ?>
      <div><a class="uname" href="<?= e(url('u/' . $u['username'])) ?>"><?= e(display_name($u)) ?></a>
      <div class="muted">Level <?= (int)$prog['level'] ?> · <?= number_format((int)$u['xp']) ?> pts</div></div>
    </div>
    <?= xp_bar((int)$u['xp']) ?>
    <div class="mini">
      <div><b><?= $stats['workouts'] ?></b><span>workouts</span></div>
      <div><b><?= $stats['streak'] ?></b><span>day streak</span></div>
      <div><b><?= $stats['prs'] ?></b><span>PRs</span></div>
    </div>
    <a class="btn wide" href="<?= e(url('log')) ?>">Track a workout</a>
  </div>

  <?php if ($suggest): ?>
  <div class="card">
    <h4>Members to follow</h4>
    <?php foreach ($suggest as $s): ?>
      <div class="person">
        <a href="<?= e(url('u/' . $s['username'])) ?>"><?= uavatar($s, 34) ?></a>
        <div class="grow"><a href="<?= e(url('u/' . $s['username'])) ?>"><?= e(display_name($s)) ?></a>
          <div class="muted small">Level <?= (int)$s['level'] ?></div></div>
        <form method="post" action="<?= e(url('act/follow')) ?>" data-ajax class="inl">
          <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
          <button class="btn small ghost" type="submit" data-followbtn>Follow</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</aside>
</div>
<?php layout_footer('feed', ['game.js']); ?>
