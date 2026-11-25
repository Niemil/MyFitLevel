<?php
declare(strict_types=1);

/* Posts, visibility rules and the mixed feed (workouts + posts). */

/** Can $viewer (null = not logged in) see this member's workouts and posts? */
function can_see_member(array $owner, ?array $viewer): bool
{
    if ($viewer && ((int)$viewer['id'] === (int)$owner['id'] || !empty($viewer['is_admin']))) {
        return true;
    }
    switch ($owner['visibility'] ?? 'public') {
        case 'public':
            return true;
        case 'members':
            return $viewer !== null;
        default:
            return $viewer !== null
                && (bool)row('SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?', [(int)$viewer['id'], (int)$owner['id']]);
    }
}

/** SQL fragment limiting rows to members the viewer may see. $a = users alias. */
function visibility_sql(?array $viewer, string $a = 'us'): array
{
    if (!$viewer) {
        return ["$a.visibility = 'public'", []];
    }
    $vid = (int)$viewer['id'];
    return ["($a.id = ? OR $a.visibility IN ('public','members') OR ($a.visibility = 'followers' AND $a.id IN (SELECT followee_id FROM follows WHERE follower_id = ?)))", [$vid, $vid]];
}

/**
 * Mixed list of workouts and posts, newest first.
 * $mode: 'everyone' | 'following' | 'user' (then $userId; the caller has already checked visibility).
 * Returns ['items' => [['type'=>'workout'|'post', 'time'=>..., 'row'=>...]], 'more' => bool]
 */
function feed_items(?array $viewer, string $mode, ?string $before, int $limit, ?int $userId = null): array
{
    $vid = $viewer ? (int)$viewer['id'] : 0;
    // conditions on the member (alias us) shared by every kind of feed item
    $uc = ['us.is_banned = 0'];
    $up = [];
    if ($mode === 'user') {
        $uc[] = 'us.id = ?'; $up[] = $userId;
    } else {
        if ($mode === 'following' && $viewer) {
            $uc[] = '(us.id = ? OR us.id IN (SELECT followee_id FROM follows WHERE follower_id = ?))';
            array_push($up, $vid, $vid);
        }
        [$vs, $vparams] = visibility_sql($viewer);
        $uc[] = $vs;
        $up = array_merge($up, $vparams);
    }
    $lim = $limit + 1;
    $kinds = [
        'workout' => ['SELECT w.*, us.username, us.display_name, us.avatar_token, us.level FROM workouts w JOIN users us ON us.id = w.user_id', 'w.performed_at', 'w.id'],
        'post'    => ['SELECT p.*, us.username, us.display_name, us.avatar_token, us.level FROM posts p JOIN users us ON us.id = p.user_id', 'p.created_at', 'p.id'],
        'quest'   => ['SELECT q.code, q.reward, q.completed_at, us.id AS user_id, us.username, us.display_name, us.avatar_token, us.level FROM user_quests q JOIN users us ON us.id = q.user_id', 'q.completed_at', 'q.code'],
        'badge'   => ['SELECT b.code, b.earned_at, us.id AS user_id, us.username, us.display_name, us.avatar_token, us.level FROM user_badges b JOIN users us ON us.id = b.user_id', 'b.earned_at', 'b.code'],
    ];
    if ($mode === 'user' && setting('game_tables_ok9') === '1') {
        // Profile only: what the member did besides workouts (steps, boss hits, loot).
        $ugrp = 'us.id, us.username, us.display_name, us.avatar_token, us.level';
        $ucols = 'us.id AS user_id, us.username, us.display_name, us.avatar_token, us.level';
        $kinds['steps'] = ["SELECT d.day, d.steps, d.distance_m, d.source, d.updated_at AS at, $ucols FROM daily_steps d JOIN users us ON us.id = d.user_id AND d.steps >= 1000", 'd.updated_at', 'd.day'];
        $kinds['loot'] = ["SELECT i.item, i.source, i.got_at AS at, $ucols FROM game_items i JOIN users us ON us.id = i.user_id AND i.source <> 'shop'", 'i.got_at', 'i.item'];
        if (setting('boss_log_ok1') === '1') {
            $kinds['boss'] = ["SELECT l.boss_id, b.name AS boss_name, b.boss_key, b.team_id AS raid_team, SUM(l.damage) AS dmg, COUNT(*) AS n, MAX(l.created_at) AS at, GROUP_CONCAT(DISTINCT l.src) AS srcs, $ucols FROM boss_log l JOIN bosses b ON b.id = l.boss_id JOIN users us ON us.id = l.user_id",
                'l.created_at', 'l.boss_id', "l.boss_id, DATE(l.created_at), l.user_id, b.name, b.boss_key, b.team_id, $ugrp", 'MAX(l.created_at)'];
        }
    }
    $got = [];
    foreach ($kinds as $type => $def) {
        [$sql, $tcol, $idcol] = $def;
        $grp = $def[3] ?? '';
        $ord = $def[4] ?? $tcol;
        $cond = $uc;
        $par = $up;
        if ($before) {
            $cond[] = "$tcol < ?"; $par[] = $before;
        }
        $got[$type] = rows($sql . ' WHERE ' . implode(' AND ', $cond) . ($grp ? " GROUP BY $grp" : '') . " ORDER BY $ord DESC LIMIT $lim", $par);
    }
    $got['workout'] = attach_workout_data($got['workout'], $vid);
    $got['post'] = attach_post_data($got['post'], $vid);
    $items = [];
    foreach ($got as $type => $list) {
        foreach ($list as $row) {
            $t = isset($row['at']) ? $row['at'] : ($type === 'workout' ? $row['performed_at'] : ($type === 'post' ? $row['created_at'] : ($type === 'quest' ? $row['completed_at'] : $row['earned_at'])));
            $items[] = ['type' => $type, 'time' => $t, 'row' => $row];
        }
    }
    usort($items, fn($a, $b) => strcmp($b['time'], $a['time']) ?: strcmp($b['type'], $a['type']));
    $more = count($items) > $limit;
    return ['items' => array_slice($items, 0, $limit), 'more' => $more];
}

function attach_post_data(array $ps, int $viewerId): array
{
    if (!$ps) {
        return [];
    }
    $ids = array_map(fn($p) => (int)$p['id'], $ps);
    $in = in_clause($ids);
    $props = array_column(rows("SELECT post_id, COUNT(*) c FROM post_props WHERE post_id IN ($in) GROUP BY post_id", $ids), 'c', 'post_id');
    $coms = array_column(rows("SELECT post_id, COUNT(*) c FROM post_comments WHERE post_id IN ($in) GROUP BY post_id", $ids), 'c', 'post_id');
    $mine = array_column(rows("SELECT post_id FROM post_props WHERE user_id = ? AND post_id IN ($in)", array_merge([$viewerId], $ids)), 'post_id');
    $imgs = post_images($ids);
    foreach ($ps as &$p) {
        $p['images'] = $imgs[(int)$p['id']] ?? [];
        $p['props'] = (int)($props[$p['id']] ?? 0);
        $p['comments'] = (int)($coms[$p['id']] ?? 0);
        $p['propped'] = in_array($p['id'], $mine);
    }
    return $ps;
}

function render_feed_items(array $items, ?array $viewer): void
{
    foreach ($items as $it) {
        render_feed_item($it, $viewer);
    }
}

/** Write-a-post box. */
function post_composer(array $viewer, string $back): void
{
    ?>
<form class="card composer" method="post" action="<?= e(url('act/post_new')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
  <textarea name="body" maxlength="1000" rows="2" placeholder="Share something with the community…"></textarea>
  <div class="photoprev" data-preview hidden></div>
  <div class="composer-foot">
    <label class="photobtn" title="Add photos">📷 Photo<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-photos hidden></label>
    <span class="muted small"><?= $viewer['visibility'] === 'public' ? '🌍 Everyone can see this' : ($viewer['visibility'] === 'members' ? '👥 Members only' : '🔒 Followers only') ?> · <a href="<?= e(url('settings')) ?>">change</a></span>
    <button class="btn small" type="submit">Post</button>
  </div>
</form>
<?php
}

function post_card(array $p, ?array $viewer, bool $full = false): void
{
    $name = display_name($p);
    $href = url('post/' . $p['id']);
    $mine = $viewer && (int)$p['user_id'] === (int)$viewer['id'];
    ?>
<article class="card post" id="p<?= (int)$p['id'] ?>">
  <header class="whead">
    <a href="<?= e(url('u/' . $p['username'])) ?>"><?= uavatar($p, 42) ?></a>
    <div class="who">
      <a class="uname" href="<?= e(url('u/' . $p['username'])) ?>"><?= e($name) ?></a> <?= level_chip((int)$p['level']) ?><?= role_badge($p) ?>
      <div class="when"><a href="<?= e($href) ?>"><?= e(rel_time($p['created_at'])) ?></a></div>
    </div>
  </header>
  <?php if ($p['body'] !== ''): ?><p class="postbody"><?= nl2br(e($p['body'])) ?></p><?php endif; ?>
  <?= photo_grid($p['images'] ?? []) ?>
  <footer class="wfoot">
    <?php if ($viewer): ?>
    <form method="post" action="<?= e(url('act/post_props')) ?>" data-ajax class="inl">
      <?= csrf_field() ?><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
      <input type="hidden" name="back" value="<?= e(($_SERVER['REQUEST_URI'] ?? '/') . '#p' . $p['id']) ?>">
      <button class="act <?= $p['propped'] ? 'on' : '' ?>" type="submit" data-propbtn><?= icon('prop') ?> <span data-count><?= (int)$p['props'] ?></span> Props</button>
    </form>
    <?php else: ?>
    <a class="act" href="<?= e(url('login')) ?>"><?= icon('prop') ?> <?= (int)$p['props'] ?> Props</a>
    <?php endif; ?>
    <a class="act" href="<?= e($href) ?>#comments"><?= icon('chat') ?> <?= (int)$p['comments'] ?> Comments</a>
    <?= report_button($viewer, 'post', (int)$p['id'], (int)$p['user_id']) ?>
    <?php if ($mine && !$full): ?>
    <form method="post" action="<?= e(url('act/post_delete')) ?>" class="inl" data-confirm="Delete this post?">
      <?= csrf_field() ?><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
      <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
      <button class="act" type="submit" title="Delete post"><?= icon('trash') ?></button>
    </form>
    <?php endif; ?>
  </footer>
</article>
<?php
}

/** Short workout row for profiles: click through to the full workout. */
function workout_compact(array $w, ?array $viewer): void
{
    $href = url('workout/' . $w['id']);
    $names = array_map(fn($e) => $e['name'], $w['exercises']);
    $nEx = count($names);
    $shown = array_slice($names, 0, 3);
    $unit = $viewer['unit'] ?? 'kg';
    $vol = (float)$w['volume_kg'] * ($unit === 'lb' ? LB_PER_KG : 1);
    ?>
<a class="card wcompact" href="<?= e($href) ?>">
  <div class="wc-top">
    <div class="grow">
      <b><?= e($w['title'] ?: 'Workout') ?></b>
      <div class="muted small"><?= e(rel_time($w['performed_at'])) ?> · <?= pluralize($nEx, 'exercise') ?> · <?= pluralize((int)$w['set_count'], 'set') ?><?= $vol > 0 ? ' · ' . e(fmt_big($vol)) . ' ' . e($unit) : '' ?><?= !empty($w['gym_name']) ? ' · 📍 ' . e($w['gym_name']) : '' ?></div>
    </div>
    <div class="pts"><b>+<?= number_format((int)$w['points']) ?></b><small>pts</small></div>
  </div>
  <?php if ($shown): ?><div class="wc-ex muted small"><?= e(implode(' · ', $shown)) ?><?= $nEx > 3 ? ' · +' . ($nEx - 3) . ' more' : '' ?></div><?php endif; ?>
</a>
<?php
}

function render_feed_item(array $it, ?array $viewer, bool $compactWorkouts = false): void
{
    switch ($it['type']) {
        case 'post': post_card($it['row'], $viewer); break;
        case 'quest':
        case 'badge': event_card($it, $viewer); break;
        case 'steps':
        case 'boss':
        case 'loot': activity_card($it); break;
        default: $compactWorkouts ? workout_compact($it['row'], $viewer) : workout_card($it['row'], $viewer);
    }
}

/** "Completed a Quest for 350 pts" / "Achievement unlocked" lines, like the old Fitocracy feed. */
function event_card(array $it, ?array $viewer): void
{
    $r = $it['row'];
    $name = display_name($r);
    if ($it['type'] === 'quest') {
        $d = quest_defs()[$r['code']] ?? null;
        if (!$d) { return; }
        $head = 'Completed a Quest for <b>' . (int)$r['reward'] . ' pts</b>';
        $title = $d[0]; $desc = $d[1]; $icon = '⚔️';
    } else {
        $d = badge_defs()[$r['code']] ?? null;
        if (!$d) { return; }
        $head = 'Achievement unlocked';
        $title = $d[0]; $desc = $d[1]; $icon = $d[2];
    }
    ?>
<article class="card eventcard">
  <a href="<?= e(url('u/' . $r['username'])) ?>"><?= uavatar($r, 42) ?></a>
  <div class="evbadge" aria-hidden="true"><?= $icon ?></div>
  <div class="grow">
    <div class="small"><a class="uname" href="<?= e(url('u/' . $r['username'])) ?>"><?= e($name) ?></a> <span class="muted"><?= e(rel_time($it['time'])) ?></span></div>
    <div class="evhead"><?= $head ?></div>
    <b><?= e($title) ?></b>
    <div class="muted small"><?= e($desc) ?></div>
  </div>
</article>
<?php
}


/** Profile activity lines for steps, boss hits and loot (coins are never shown). */
function activity_card(array $it): void
{
    $r = $it['row'];
    $name = display_name($r);
    if ($it['type'] === 'steps') {
        $per = max(1, (int)game_cfg('steps_per_point'));
        $pts = intdiv(min((int)$r['steps'], max(0, (int)game_cfg('steps_cap_day'))), $per);
        $icon = '👟';
        $head = 'Walked <b>' . number_format((int)$r['steps']) . ' steps</b> on ' . e(date('M j', strtotime((string)$r['day'])));
        $title = '+' . $pts . ' pts from steps';
        $desc = ((int)$r['distance_m'] > 0 ? number_format((int)$r['distance_m'] / 1000, 1) . ' km · ' : '') . 'synced from ' . ($r['source'] === 'manual' ? 'a manual entry' : (string)$r['source']);
    } elseif ($it['type'] === 'boss') {
        $icon = game_roster_by_key((string)$r['boss_key'])[2];
        $head = $r['raid_team'] ? 'Fought in a team raid' : 'Hit the weekly boss';
        $title = (string)$r['boss_name'];
        $srcs = array_map(fn($x) => $x === 'steps' ? 'steps' : 'workouts', explode(',', (string)$r['srcs']));
        $desc = number_format((int)$r['dmg']) . ' damage · ' . (int)$r['n'] . ' hit' . ((int)$r['n'] === 1 ? '' : 's') . ' from ' . implode(' and ', array_unique($srcs));
    } else {
        $cat = game_items();
        $d = $cat[$r['item']] ?? null;
        if (!$d) { return; }
        $icon = $d[1];
        $head = ['drop' => 'Won a boss drop', 'relic' => 'Found a legendary relic', 'advent' => 'Opened an advent door'][$r['source']] ?? 'Earned a rare item';
        $title = $d[0];
        $desc = ($d[3] ? '+' . $d[3] . '% damage' : '') . ($d[3] && $d[4] ? ' · ' : '') . ($d[4] ? '+' . $d[4] . '% coins' : '');
    }
    ?>
<article class="card eventcard">
  <a href="<?= e(url('u/' . $r['username'])) ?>"><?= uavatar($r, 42) ?></a>
  <div class="evbadge" aria-hidden="true"><?= $icon ?></div>
  <div class="grow">
    <div class="small"><a class="uname" href="<?= e(url('u/' . $r['username'])) ?>"><?= e($name) ?></a> <span class="muted"><?= e(rel_time($it['time'])) ?></span></div>
    <div class="evhead"><?= $head ?></div>
    <b><?= e($title) ?></b>
    <?php if ($desc !== ''): ?><div class="muted small"><?= e($desc) ?></div><?php endif; ?>
  </div>
</article>
<?php
}

/* ================= Private messages ================= */

function dm_ensure(): void
{
    if (setting('dm_ok1') === '1') {
        return;
    }
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    db()->exec("CREATE TABLE IF NOT EXISTS dm_messages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, from_id INT UNSIGNED NOT NULL, to_id INT UNSIGNED NOT NULL,
        body VARCHAR(1200) NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME NULL,
        KEY idx_to (to_id, read_at), KEY idx_pair (from_id, to_id, id)) $t");
    db()->exec("CREATE TABLE IF NOT EXISTS dm_blocks (
        user_id INT UNSIGNED NOT NULL, blocked_id INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (user_id, blocked_id)) $t");
    set_setting('dm_ok1', '1');
}

function dm_unread(int $uid): int
{
    if (setting('dm_ok1') !== '1') {
        return 0;
    }
    return (int)val('SELECT COUNT(*) FROM dm_messages WHERE to_id = ? AND read_at IS NULL', [$uid]);
}

function dm_blocked(int $by, int $who): bool
{
    return (bool)row('SELECT 1 FROM dm_blocks WHERE user_id = ? AND blocked_id = ?', [$by, $who]);
}

/** Returns '' on success, otherwise a short error for the sender. */
function dm_send(int $from, int $to, string $body): string
{
    dm_ensure();
    $body = trim(preg_replace("/\r\n?/", "\n", $body));
    $body = preg_replace("/\n{3,}/", "\n\n", $body);
    if ($body === '' || mb_strlen($body) > 1000) {
        return 'Write a message of 1 to 1000 characters.';
    }
    $t = row('SELECT id FROM users WHERE id = ? AND is_banned = 0', [$to]);
    if (!$t || $to === $from) {
        return 'You cannot message that member.';
    }
    if (dm_blocked($to, $from)) {
        return 'This member is not taking messages from you.';
    }
    if (dm_blocked($from, $to)) {
        return 'You have blocked this member. Unblock them to write.';
    }
    if (!rate_ok('dm:' . $from, 40, 3600) || !rate_ok('dmp:' . $from . ':' . $to, 15, 600)) {
        return 'Slow down a little, try again in a few minutes.';
    }
    rate_hit('dm:' . $from);
    rate_hit('dmp:' . $from . ':' . $to);
    q('INSERT INTO dm_messages (from_id, to_id, body, created_at) VALUES (?,?,?,?)', [$from, $to, $body, now()]);
    if (!(int)val("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND actor_id = ? AND type = 'dm' AND is_read = 0", [$to, $from])) {
        q("INSERT INTO notifications (user_id, actor_id, type, text, created_at) VALUES (?,?,'dm','sent you a message',?)", [$to, $from, now()]);
    }
    return '';
}
