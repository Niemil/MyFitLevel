<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
game_ensure();
$w = game_wallet($uid);
$prog = level_progress((int)$u['xp']);
$look = fito_look($uid);
$hr = (int)date('G');
$hello = $hr < 5 ? 'Late night' : ($hr < 12 ? 'Good morning' : ($hr < 18 ? 'Good afternoon' : 'Good evening'));

define('GAME_CSS', true);
layout_header('Today', 'today');
?>
<section class="card todayhero">
  <div class="th-fito"><?= fito_dressed($look, 200) ?></div>
  <div class="th-txt">
    <div class="muted small"><?= e($hello) ?></div>
    <h1 class="pagetitle" style="margin:2px 0 4px"><?= e(display_name($u)) ?></h1>
    <div class="muted">Level <?= (int)$prog['level'] ?> · <?= number_format((int)$u['xp']) ?> pts · <?= number_format((int)$w['coins']) ?> coins</div>
    <div class="th-links">
      <a class="btn" href="<?= e(url('log')) ?>">Track a workout</a>
      <a class="btn ghost small" href="<?= e(url('wardrobe')) ?>">Wardrobe</a>
      <a class="btn ghost small" href="<?= e(url('boss')) ?>">Boss</a>
    </div>
  </div>
</section>
<?php verify_banner($u); game_today_card($u); fito_coach_card($u); onboard_card($u); week_card($u); game_feed_card($u); game_boss_arena($u); game_steps_bar($u); today_card($u); ?>
<p class="center"><a class="btn ghost" href="<?= e(url('feed')) ?>">Open the community feed</a></p>
<?php layout_footer(); ?>
