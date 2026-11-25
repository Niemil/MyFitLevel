<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
$qs = clean_text((string)($_GET['q'] ?? ''), 30);

if ($qs !== '') {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $qs) . '%';
    $list = rows('SELECT id, username, display_name, level, xp FROM users WHERE is_banned = 0 AND (username LIKE ? OR display_name LIKE ?) ORDER BY xp DESC LIMIT 50', [$like, $like]);
} else {
    $list = rows('SELECT id, username, display_name, level, xp FROM users WHERE is_banned = 0 ORDER BY last_seen DESC, id DESC LIMIT 50');
}
$following = array_map('intval', array_column(rows('SELECT followee_id FROM follows WHERE follower_id = ?', [$uid]), 'followee_id'));

layout_header('Members', 'members');
?>
<h1 class="pagetitle">Members</h1>
<form method="get" class="searchrow"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search by name…"><button class="btn small">Search</button></form>
<div class="card">
  <?php if (!$list): ?><p class="muted">No members found.</p><?php endif; ?>
  <?php foreach ($list as $m): ?>
    <div class="person">
      <a href="<?= e(url('u/' . $m['username'])) ?>"><?= uavatar($m, 40) ?></a>
      <div class="grow">
        <a class="uname" href="<?= e(url('u/' . $m['username'])) ?>"><?= e(display_name($m)) ?></a> <?= level_chip((int)$m['level']) ?><?= role_badge($m) ?>
        <div class="muted small">@<?= e($m['username']) ?> · <?= number_format((int)$m['xp']) ?> pts</div>
      </div>
      <?php if ((int)$m['id'] !== $uid): $f = in_array((int)$m['id'], $following, true); ?>
      <form method="post" action="<?= e(url('act/follow')) ?>" data-ajax class="inl">
        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
        <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
        <button class="btn small <?= $f ? 'ghost' : '' ?>" type="submit" data-followbtn><?= $f ? 'Following' : 'Follow' ?></button>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php layout_footer('members'); ?>
