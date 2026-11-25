<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
$g = row('SELECT * FROM groups_ WHERE slug = ?', [$args[0]]);
if (!$g) {
    http_response_code(404);
    layout_header('Not found');
    echo '<div class="card center"><h2>Group not found</h2></div>';
    layout_footer();
    exit;
}
$gid = (int)$g['id'];
$isMember = (bool)row('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?', [$gid, $uid]);
$members = rows('SELECT us.id, us.username, us.display_name, us.avatar_token, us.level FROM group_members m JOIN users us ON us.id = m.user_id
                 WHERE m.group_id = ? AND us.is_banned = 0 ORDER BY us.xp DESC LIMIT 30', [$gid]);
$count = (int)val('SELECT COUNT(*) FROM group_members WHERE group_id = ?', [$gid]);
$posts = rows('SELECT p.*, us.username, us.display_name, us.avatar_token FROM group_posts p JOIN users us ON us.id = p.user_id
               WHERE p.group_id = ? ORDER BY p.id DESC LIMIT 50', [$gid]);

layout_header($g['name'], 'groups');
?>
<div class="card">
  <div class="phtop">
    <div class="grow">
      <h1><?= e($g['name']) ?></h1>
      <?php if ($g['description']): ?><p><?= e($g['description']) ?></p><?php endif; ?>
      <div class="muted small"><?= pluralize($count, 'member') ?></div>
    </div>
    <form method="post" action="<?= e(url($isMember ? 'act/group_leave' : 'act/group_join')) ?>" class="inl">
      <?= csrf_field() ?><input type="hidden" name="group_id" value="<?= $gid ?>">
      <button class="btn <?= $isMember ? 'ghost' : '' ?>" type="submit"><?= $isMember ? 'Leave group' : 'Join group' ?></button>
    </form>
  </div>
</div>

<div class="cols">
<section class="main">
  <?php if ($isMember): ?>
  <div class="card">
    <form method="post" action="<?= e(url('act/group_post')) ?>">
      <?= csrf_field() ?><input type="hidden" name="group_id" value="<?= $gid ?>">
      <textarea name="body" rows="3" maxlength="1000" placeholder="Share something with the group…" required></textarea>
      <button class="btn small" type="submit">Post</button>
    </form>
  </div>
  <?php else: ?>
  <div class="card center muted">Join the group to post.</div>
  <?php endif; ?>

  <?php foreach ($posts as $p): ?>
    <div class="card gpost">
      <div class="comment">
        <a href="<?= e(url('u/' . $p['username'])) ?>"><?= uavatar($p, 38) ?></a>
        <div class="grow">
          <a class="uname" href="<?= e(url('u/' . $p['username'])) ?>"><?= e(display_name($p)) ?></a>
          <span class="muted small"><?= e(rel_time($p['created_at'])) ?></span>
          <div><?= nl2br(e($p['body'])) ?></div>
        </div>
        <?php if ((int)$p['user_id'] === $uid || (int)$g['owner_id'] === $uid || is_staff($u)): ?>
        <form method="post" action="<?= e(url('act/group_post_delete')) ?>" class="inl" data-confirm="Delete this post?">
          <?= csrf_field() ?><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
          <button class="linkbtn" title="Delete"><?= icon('trash') ?></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
<aside class="side">
  <div class="card">
    <h4>Members</h4>
    <?php foreach ($members as $m): ?>
      <div class="person"><a href="<?= e(url('u/' . $m['username'])) ?>"><?= uavatar($m, 30) ?></a>
        <a class="grow" href="<?= e(url('u/' . $m['username'])) ?>"><?= e(display_name($m)) ?></a><?= level_chip((int)$m['level']) ?></div>
    <?php endforeach; ?>
  </div>
</aside>
</div>
<?php layout_footer('groups'); ?>
