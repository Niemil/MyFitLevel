<?php
declare(strict_types=1);

$u = me();
$uid = $u ? (int)$u['id'] : 0;
$key = (string)($_GET['k'] ?? '');
$notFound = function (): void {
    http_response_code(404);
    layout_header('Not found');
    echo '<div class="card center"><h2>Not found</h2></div>';
    layout_footer();
    exit;
};
if (!preg_match('/^([qbslx]):(\d+):([\w.\-]{1,60})$/', $key, $m)) { $notFound(); }
[, $t, $ouid, $ref] = $m;
$ouid = (int)$ouid;
$ucols = 'us.id AS user_id, us.username, us.display_name, us.avatar_token, us.level, us.visibility';
switch ($t) {
    case 'q': $type = 'quest'; $r = row("SELECT q.code, q.reward, q.completed_at AS at, $ucols FROM user_quests q JOIN users us ON us.id = q.user_id WHERE q.user_id = ? AND q.code = ? AND us.is_banned = 0", [$ouid, $ref]); break;
    case 'b': $type = 'badge'; $r = row("SELECT b.code, b.earned_at AS at, $ucols FROM user_badges b JOIN users us ON us.id = b.user_id WHERE b.user_id = ? AND b.code = ? AND us.is_banned = 0", [$ouid, $ref]); break;
    case 's': $type = 'steps'; $r = row("SELECT d.day, d.steps, d.distance_m, d.source, d.updated_at AS at, $ucols FROM daily_steps d JOIN users us ON us.id = d.user_id WHERE d.user_id = ? AND d.day = ? AND us.is_banned = 0", [$ouid, $ref]); break;
    case 'l': $type = 'loot'; $r = row("SELECT i.item, i.source, i.got_at AS at, $ucols FROM game_items i JOIN users us ON us.id = i.user_id WHERE i.user_id = ? AND i.item = ? AND us.is_banned = 0", [$ouid, $ref]); break;
    default:
        $type = 'boss';
        $r = row("SELECT l.boss_id, b.name AS boss_name, b.boss_key, b.team_id AS raid_team, SUM(l.damage) AS dmg, COUNT(*) AS n, MAX(l.created_at) AS at, GROUP_CONCAT(DISTINCT l.src) AS srcs, $ucols
                  FROM boss_log l JOIN bosses b ON b.id = l.boss_id JOIN users us ON us.id = l.user_id WHERE l.user_id = ? AND l.boss_id = ? AND us.is_banned = 0 GROUP BY l.boss_id", [$ouid, (int)$ref]);
}
if (!$r || !can_see_member($r + ['id' => $r['user_id']], $u) || !event_parts($type, $r)) { $notFound(); }
event_ensure();
$comments = rows('SELECT c.*, us.username, us.display_name, us.avatar_token FROM event_comments c JOIN users us ON us.id = c.user_id WHERE c.ekey = ? ORDER BY c.id', [$key]);

layout_header('Update', $u ? 'feed' : '');
?>
<div class="narrowcol">
<?php event_row($type, $r, (string)$r['at'], $u, true); ?>
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
      <?php if ($u && ((int)$c['user_id'] === $uid || is_staff($u) || $ouid === $uid)): ?>
      <form method="post" action="<?= e(url('act/event_comment_delete')) ?>" class="inl">
        <?= csrf_field() ?><input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
        <input type="hidden" name="back" value="<?= e(url('event?k=' . rawurlencode($key)) . '#comments') ?>">
        <button class="linkbtn" title="Delete comment"><?= icon('trash') ?></button>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($u): ?>
  <form method="post" action="<?= e(url('act/event_comment')) ?>" class="commentform">
    <?= csrf_field() ?><input type="hidden" name="ekey" value="<?= e($key) ?>">
    <input type="hidden" name="back" value="<?= e(url('event?k=' . rawurlencode($key)) . '#comments') ?>">
    <input name="body" maxlength="500" placeholder="Write a comment…" required>
    <button class="btn small" type="submit">Post</button>
  </form>
  <?php else: ?><p class="muted small"><a href="<?= e(url('login')) ?>">Log in</a> to comment.</p><?php endif; ?>
</div>
</div>
<?php layout_footer(); ?>
