<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
$list = rows('SELECT n.*, us.username, us.display_name, us.avatar_token FROM notifications n
              LEFT JOIN users us ON us.id = n.actor_id
              WHERE n.user_id = ? ORDER BY n.id DESC LIMIT 60', [$uid]);
q('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$uid]);

layout_header('Notifications');
?>
<h1 class="pagetitle">Notifications</h1>
<div class="card">
  <?php if (!$list): ?><p class="muted center">Nothing yet. Props, comments and new followers show up here.</p><?php endif; ?>
  <?php foreach ($list as $n):
      $who = $n['username'] ? '<a href="' . e(url('u/' . $n['username'])) . '">' . e(display_name($n)) . '</a>' : '';
      $isPost = in_array($n['type'], ['post_props', 'post_comment'], true);
      $wlink = $n['workout_id'] ? url(($isPost ? 'post/' : 'workout/') . $n['workout_id']) : '';
      switch ($n['type']) {
          case 'props':   $msg = "$who gave props to your workout"; break;
          case 'comment': $msg = "$who commented on your workout"; break;
          case 'post_props':   $msg = "$who gave props to your post"; break;
          case 'post_comment': $msg = "$who commented on your post"; break;
          case 'event_props':   $msg = '<a href="' . e(url('event?k=' . rawurlencode((string)$n['text']))) . '">' . $who . ' gave props to your update</a>'; break;
          case 'event_comment': $msg = '<a href="' . e(url('event?k=' . rawurlencode((string)$n['text']))) . '#comments">' . $who . ' commented on your update</a>'; break;
          case 'dm':      $msg = '<a href="' . e(url('messages/' . $n['username'])) . '">' . $who . ' sent you a message</a>'; break;
          case 'follow':  $msg = "$who started following you"; break;
          case 'boss': $msg = '<a href="' . e(url('boss')) . '">' . e((string)$n['text']) . '</a>'; break;
          case 'team': $msg = '<a href="' . e(url('teams')) . '">' . e((string)$n['text']) . '</a>'; break;
          case 'feedback': $msg = '<a href="' . e(url('admin?tab=feedback')) . '">' . e((string)$n['text']) . '</a>'; break;
          default:        $msg = e((string)$n['text']);
      }
  ?>
    <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>">
      <div class="grow"><?= $wlink ? '<a href="' . e($wlink) . '">' . $msg . '</a>' : $msg ?></div>
      <span class="muted small"><?= e(rel_time($n['created_at'])) ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php layout_footer(); ?>
