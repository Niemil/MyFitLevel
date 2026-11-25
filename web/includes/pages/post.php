<?php
declare(strict_types=1);

$u = me();
$uid = $u ? (int)$u['id'] : 0;
$pid = (int)$args[0];
$p = row('SELECT p.*, us.username, us.display_name, us.avatar_token, us.level, us.visibility FROM posts p JOIN users us ON us.id = p.user_id WHERE p.id = ? AND us.is_banned = 0', [$pid]);
if (!$p || !can_see_member($p, $u)) {
    http_response_code($p ? 403 : 404);
    layout_header('Not found');
    echo '<div class="card center"><h2>' . ($p ? 'This post is private' : 'Post not found') . '</h2></div>';
    layout_footer();
    exit;
}
$p = attach_post_data([$p], $uid)[0];
$comments = rows('SELECT c.*, us.username, us.display_name, us.avatar_token FROM post_comments c JOIN users us ON us.id = c.user_id WHERE c.post_id = ? ORDER BY c.id', [$pid]);

layout_header('Post', $u ? 'feed' : '');
?>
<div class="narrowcol">
<?php post_card($p, $u, true); ?>
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
      <?php if ($u && ((int)$c['user_id'] === $uid || is_staff($u) || (int)$p['user_id'] === $uid)): ?>
      <form method="post" action="<?= e(url('act/post_comment_delete')) ?>" class="inl">
        <?= csrf_field() ?><input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
        <input type="hidden" name="back" value="<?= e(url('post/' . $pid) . '#comments') ?>">
        <button class="linkbtn" title="Delete comment"><?= icon('trash') ?></button>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($u): ?>
  <form method="post" action="<?= e(url('act/post_comment')) ?>" class="commentform">
    <?= csrf_field() ?><input type="hidden" name="post_id" value="<?= $pid ?>">
    <input type="hidden" name="back" value="<?= e(url('post/' . $pid) . '#comments') ?>">
    <input name="body" maxlength="500" placeholder="Write a comment…" required>
    <button class="btn small" type="submit">Post</button>
  </form>
  <?php else: ?><p class="muted small"><a href="<?= e(url('login')) ?>">Log in</a> to comment.</p><?php endif; ?>
</div>
<?php if ($u && (int)$p['user_id'] === $uid): ?>
<form method="post" action="<?= e(url('act/post_delete')) ?>" class="center" data-confirm="Delete this post?">
  <?= csrf_field() ?><input type="hidden" name="post_id" value="<?= $pid ?>"><input type="hidden" name="back" value="feed">
  <button class="btn danger ghost small" type="submit"><?= icon('trash') ?> Delete post</button>
</form>
<?php endif; ?>
</div>
<?php layout_footer($u ? 'feed' : ''); ?>
