<?php
declare(strict_types=1);

/* Weekly goal: pick how many training days per week, collect a growing bonus every week you hit it. */

$u = require_login();
$uid = (int)$u['id'];

if (is_post()) {
    csrf_check();
    $n = max(1, min(7, (int)($_POST['target'] ?? WEEKLY_DEFAULT)));
    set_setting('wgoal:' . $uid, (string)$n);
    flash('Weekly goal set to ' . $n . ' training day' . ($n > 1 ? 's' : '') . '.');
    weekly_check($uid);
    recalc_xp($uid);
    redirect('goals');
}

$mon = bonus_week_start();
$target = weekly_goal($uid);
$done = week_days_done($uid, $mon);
$streak = weekly_streak($uid, $mon);
$got = (int)val("SELECT COUNT(*) FROM quest_steps WHERE user_id = ? AND quest = 'weekly' AND step = ?", [$uid, bonus_week_key($mon)]) > 0;
$pct = (int)min(100, round($done / max(1, $target) * 100));
$hist = rows("SELECT step, reward, done_at FROM quest_steps WHERE user_id = ? AND quest = 'weekly' ORDER BY step DESC LIMIT 12", [$uid]);
$live = event_rows('starts_at <= ? AND ends_at > ?', [now(), now()]);

layout_header('Weekly goal', 'feed');
?>
<div class="prose">
  <h1>Weekly goal</h1>
  <p class="lead" style="margin-left:0">Pick how many days a week you want to train. Every week you hit it you collect a bonus, and the bonus grows for every week in a row (up to 12). Miss a week and the streak starts over, no hard feelings.</p>

  <div class="card">
    <h3>This week</h3>
    <div class="xpbar"><i style="width:<?= $pct ?>%"></i></div>
    <p><b><?= min($done, $target) ?> / <?= $target ?></b> training days
      <?php if ($got): ?> · <b>bonus collected ✔</b><?php else: ?> · <b>+<?= number_format(weekly_bonus_for($target, $streak)) ?> pts</b> when you get there<?php endif; ?>
      <?php if ($streak > 0): ?> · 🔥 <?= $streak ?> week<?= $streak > 1 ? 's' : '' ?> in a row<?php endif; ?></p>
    <form method="post" class="inline">
      <?= csrf_field() ?>
      <label>My goal: <select name="target">
        <?php for ($i = 1; $i <= 7; $i++): ?><option value="<?= $i ?>" <?= $i === $target ? 'selected' : '' ?>><?= $i ?> day<?= $i > 1 ? 's' : '' ?> a week · +<?= weekly_bonus_for($i, 0) ?> pts</option><?php endfor; ?>
      </select></label>
      <button class="btn small">Save</button>
    </form>
    <p class="muted small">Bonus = 100 + 60 × your goal days + 20 × weeks in a row (max 12). Several workouts on one day count as one day. A higher goal pays more.</p>
  </div>

  <?php foreach ($live as $e): ?>
  <div class="card"><h3>⚡ <?= e($e['title']) ?></h3>
    <p><?= e(event_label($e)) ?> · until <?= e(date('M j H:i', strtotime($e['ends_at']))) ?></p>
    <?php if ($e['description'] !== ''): ?><p class="muted"><?= e($e['description']) ?></p><?php endif; ?></div>
  <?php endforeach; ?>
  <p><a href="<?= e(url('events')) ?>">All events →</a></p>

  <?php if ($hist): ?>
  <div class="card tablewrap"><h3>Collected</h3>
    <table class="tbl"><tr><th>Week</th><th>Bonus</th></tr>
      <?php foreach ($hist as $h): ?>
      <tr><td><?= e(substr((string)$h['step'], 0, 4)) ?> week <?= (int)substr((string)$h['step'], 4) ?></td><td>+<?= number_format((int)$h['reward']) ?> pts</td></tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
  <p class="muted small">More ways to earn: <a href="<?= e(url('quests')) ?>">quests</a>, <a href="<?= e(url('badges')) ?>">achievements</a>, <a href="<?= e(url('challenges')) ?>">challenges</a> and personal records (+<?= PR_BONUS ?> each).</p>
</div>
<?php layout_footer(); ?>
