<?php
declare(strict_types=1);

/* POST-only actions: props, follow, comments, group actions, workout delete. */

$u = require_login();
if (!is_post()) {
    redirect('feed');
}
csrf_check();
$uid = (int)$u['id'];
$action = $args[0];
$json = wants_json();
$result = ['ok' => true];
$goBack = true;

switch ($action) {
    case 'props':
        $wid = (int)($_POST['workout_id'] ?? 0);
        $w = row('SELECT w.id, w.user_id, us.visibility FROM workouts w JOIN users us ON us.id = w.user_id WHERE w.id = ? AND us.is_banned = 0', [$wid]);
        if (!$w || !can_see_member(['id' => $w['user_id'], 'visibility' => $w['visibility']], $u)) {
            $result = ['ok' => false, 'error' => 'Workout not found'];
            break;
        }
        $has = row('SELECT 1 FROM props WHERE workout_id = ? AND user_id = ?', [$wid, $uid]);
        if ($has) {
            q('DELETE FROM props WHERE workout_id = ? AND user_id = ?', [$wid, $uid]);
        } else {
            if (!rate_ok('props:' . $uid, 200, 3600)) {
                $result = ['ok' => false, 'error' => 'Slow down'];
                break;
            }
            q('INSERT IGNORE INTO props (workout_id, user_id, created_at) VALUES (?,?,?)', [$wid, $uid, now()]);
            rate_hit('props:' . $uid);
            notify((int)$w['user_id'], $uid, 'props', $wid);
            check_awards($uid);
        }
        $result['on'] = !$has;
        $result['count'] = (int)val('SELECT COUNT(*) FROM props WHERE workout_id = ?', [$wid]);
        break;

    case 'follow':
        $target = (int)($_POST['user_id'] ?? 0);
        if ($target === $uid || !row('SELECT id FROM users WHERE id = ? AND is_banned = 0', [$target])) {
            $result = ['ok' => false, 'error' => 'Invalid member'];
            break;
        }
        $has = row('SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?', [$uid, $target]);
        if ($has) {
            q('DELETE FROM follows WHERE follower_id = ? AND followee_id = ?', [$uid, $target]);
        } else {
            q('INSERT IGNORE INTO follows (follower_id, followee_id, created_at) VALUES (?,?,?)', [$uid, $target, now()]);
            notify($target, $uid, 'follow');
            check_awards($uid);
            check_awards($target);
        }
        $result['on'] = !$has;
        break;

    case 'comment':
        $wid = (int)($_POST['workout_id'] ?? 0);
        $body = clean_text((string)($_POST['body'] ?? ''), 500);
        $w = row('SELECT w.id, w.user_id, us.visibility FROM workouts w JOIN users us ON us.id = w.user_id WHERE w.id = ?', [$wid]);
        if (!$w || $body === '' || !can_see_member(['id' => $w['user_id'], 'visibility' => $w['visibility']], $u)) {
            flash('Write something first.', 'err');
            break;
        }
        if (!rate_ok('comment:' . $uid, 30, 600)) {
            flash('Too many comments. Try again in a few minutes.', 'err');
            break;
        }
        q('INSERT INTO comments (workout_id, user_id, body, created_at) VALUES (?,?,?,?)', [$wid, $uid, $body, now()]);
        rate_hit('comment:' . $uid);
        notify((int)$w['user_id'], $uid, 'comment', $wid);
        check_awards($uid);
        break;

    case 'comment_delete':
        $cid = (int)($_POST['comment_id'] ?? 0);
        $c = row('SELECT c.id, c.user_id, w.user_id AS owner FROM comments c JOIN workouts w ON w.id = c.workout_id WHERE c.id = ?', [$cid]);
        if ($c && ((int)$c['user_id'] === $uid || (int)$c['owner'] === $uid || is_staff($u))) {
            q('DELETE FROM comments WHERE id = ?', [$cid]);
        }
        break;

    case 'report':
        $kind = (string)($_POST['kind'] ?? '');
        $tid = (int)($_POST['id'] ?? 0);
        $reason = array_key_exists($_POST['reason'] ?? '', REPORT_REASONS) ? $_POST['reason'] : 'other';
        $exists = match ($kind) {
            'post' => (bool)row('SELECT 1 FROM posts WHERE id = ?', [$tid]),
            'workout' => (bool)row('SELECT 1 FROM workouts WHERE id = ?', [$tid]),
            'comment' => (bool)row('SELECT 1 FROM comments WHERE id = ?', [$tid]),
            'pcomment' => (bool)row('SELECT 1 FROM post_comments WHERE id = ?', [$tid]),
            'user' => (bool)row('SELECT 1 FROM users WHERE id = ?', [$tid]),
            default => false,
        };
        if (!$exists) {
            flash('Nothing to report.', 'err');
        } elseif (!rate_ok('report:' . $uid, 15, 86400)) {
            flash('You have sent many reports today. A moderator will look at them.', 'err');
        } else {
            q('INSERT IGNORE INTO reports (reporter_id, kind, target_id, reason, created_at) VALUES (?,?,?,?,?)', [$uid, $kind, $tid, $reason, now()]);
            rate_hit('report:' . $uid);
            flash('Thanks. A moderator will take a look.');
        }
        break;

    case 'avatar':
        $fl = files_list('photo');
        if (!$fl) {
            flash('Pick a photo first.', 'err');
            break;
        }
        $old = $u['avatar_token'] ?? null;
        $r = image_store($fl[0], $uid, 'avatar');
        if ($r['ok']) {
            q('UPDATE users SET avatar_token = ? WHERE id = ?', [$r['token'], $uid]);
            foreach (rows("SELECT * FROM images WHERE user_id = ? AND kind = 'avatar' AND token <> ?", [$uid, $r['token']]) as $im) {
                image_delete($im);
            }
            flash('Profile photo updated.');
        } else {
            flash($r['error'], 'err');
        }
        break;

    case 'avatar_remove':
        foreach (rows("SELECT * FROM images WHERE user_id = ? AND kind = 'avatar'", [$uid]) as $im) {
            image_delete($im);
        }
        q('UPDATE users SET avatar_token = NULL WHERE id = ?', [$uid]);
        flash('Profile photo removed.');
        break;

    case 'gallery_add':
        $have = (int)val("SELECT COUNT(*) FROM images WHERE user_id = ? AND kind = 'gallery'", [$uid]);
        $bad = null;
        $n = 0;
        foreach (array_slice(files_list('photos'), 0, max(0, min(6, 24 - $have))) as $f) {
            $r = image_store($f, $uid, 'gallery');
            $r['ok'] ? $n++ : $bad = $r['error'];
        }
        flash($bad ?: ($n ? 'Photo' . ($n > 1 ? 's' : '') . ' added.' : 'Nothing was added.'), $bad || !$n ? 'err' : 'ok');
        break;

    case 'gallery_del':
        $im = row("SELECT * FROM images WHERE id = ? AND kind = 'gallery'", [(int)($_POST['image_id'] ?? 0)]);
        if ($im && ((int)$im['user_id'] === $uid || is_staff($u))) {
            image_delete($im);
            flash('Photo deleted.');
        }
        break;

    case 'post_new':
        $body = clean_text((string)($_POST['body'] ?? ''), 1000);
        $files = array_slice(files_list('photos'), 0, 4);
        if ($body === '' && !$files) {
            flash('Write something or add a photo first.', 'err');
        } elseif (!rate_ok('post:' . $uid, 20, 600)) {
            flash('Too many posts. Try again in a few minutes.', 'err');
        } else {
            q('INSERT INTO posts (user_id, body, created_at) VALUES (?,?,?)', [$uid, $body, now()]);
            $newPost = (int)db()->lastInsertId();
            rate_hit('post:' . $uid);
            $bad = null;
            foreach ($files as $f) {
                $r = image_store($f, $uid, 'post', $newPost);
                if (!$r['ok']) {
                    $bad = $r['error'];
                }
            }
            flash($bad ? 'Posted, but a photo was skipped: ' . $bad : 'Posted!', $bad ? 'err' : 'ok');
        }
        break;

    case 'post_delete':
        $po = row('SELECT id, user_id FROM posts WHERE id = ?', [(int)($_POST['post_id'] ?? 0)]);
        if ($po && ((int)$po['user_id'] === $uid || is_staff($u))) {
            foreach (rows('SELECT * FROM images WHERE post_id = ?', [$po['id']]) as $im) {
                image_delete($im);
            }
            q('DELETE FROM posts WHERE id = ?', [$po['id']]);
            flash('Post deleted.');
        }
        break;

    case 'post_props':
        $pid = (int)($_POST['post_id'] ?? 0);
        $po = row('SELECT p.id, p.user_id, us.visibility FROM posts p JOIN users us ON us.id = p.user_id WHERE p.id = ? AND us.is_banned = 0', [$pid]);
        if (!$po || !can_see_member($po + ['id' => $po['user_id']], $u)) {
            $result = ['ok' => false, 'error' => 'Post not found'];
            break;
        }
        $has = row('SELECT 1 FROM post_props WHERE post_id = ? AND user_id = ?', [$pid, $uid]);
        if ($has) {
            q('DELETE FROM post_props WHERE post_id = ? AND user_id = ?', [$pid, $uid]);
        } elseif (rate_ok('props:' . $uid, 200, 3600)) {
            q('INSERT IGNORE INTO post_props (post_id, user_id, created_at) VALUES (?,?,?)', [$pid, $uid, now()]);
            rate_hit('props:' . $uid);
            notify((int)$po['user_id'], $uid, 'post_props', $pid);
        }
        $result['on'] = !$has;
        $result['count'] = (int)val('SELECT COUNT(*) FROM post_props WHERE post_id = ?', [$pid]);
        break;

    case 'post_comment':
        $pid = (int)($_POST['post_id'] ?? 0);
        $body = clean_text((string)($_POST['body'] ?? ''), 500);
        $po = row('SELECT p.id, p.user_id, us.visibility FROM posts p JOIN users us ON us.id = p.user_id WHERE p.id = ? AND us.is_banned = 0', [$pid]);
        if (!$po || $body === '' || !can_see_member($po + ['id' => $po['user_id']], $u)) {
            flash('Write something first.', 'err');
            break;
        }
        if (!rate_ok('comment:' . $uid, 30, 600)) {
            flash('Too many comments. Try again in a few minutes.', 'err');
            break;
        }
        q('INSERT INTO post_comments (post_id, user_id, body, created_at) VALUES (?,?,?,?)', [$pid, $uid, $body, now()]);
        rate_hit('comment:' . $uid);
        notify((int)$po['user_id'], $uid, 'post_comment', $pid);
        break;

    case 'post_comment_delete':
        $c = row('SELECT c.id, c.user_id, p.user_id AS owner FROM post_comments c JOIN posts p ON p.id = c.post_id WHERE c.id = ?', [(int)($_POST['comment_id'] ?? 0)]);
        if ($c && ((int)$c['user_id'] === $uid || (int)$c['owner'] === $uid || is_staff($u))) {
            q('DELETE FROM post_comments WHERE id = ?', [$c['id']]);
        }
        break;

    case 'workout_delete':
        $wid = (int)($_POST['workout_id'] ?? 0);
        delete_workout($wid, $uid);
        flash('Workout deleted.');
        redirect('feed');

    case 'group_join':
    case 'group_leave':
        $g = row('SELECT id, slug FROM groups_ WHERE id = ?', [(int)($_POST['group_id'] ?? 0)]);
        if ($g) {
            if ($action === 'group_join') {
                q('INSERT IGNORE INTO group_members (group_id, user_id, joined_at) VALUES (?,?,?)', [$g['id'], $uid, now()]);
                check_awards($uid);
            } else {
                q('DELETE FROM group_members WHERE group_id = ? AND user_id = ?', [$g['id'], $uid]);
            }
            redirect('g/' . $g['slug']);
        }
        break;

    case 'group_post':
        $g = row('SELECT id, slug FROM groups_ WHERE id = ?', [(int)($_POST['group_id'] ?? 0)]);
        $body = clean_text((string)($_POST['body'] ?? ''), 1000);
        if ($g && $body !== '' && row('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?', [$g['id'], $uid])) {
            if (rate_ok('gpost:' . $uid, 20, 600)) {
                q('INSERT INTO group_posts (group_id, user_id, body, created_at) VALUES (?,?,?,?)', [$g['id'], $uid, $body, now()]);
                rate_hit('gpost:' . $uid);
            } else {
                flash('Too many posts. Try again in a few minutes.', 'err');
            }
            redirect('g/' . $g['slug']);
        }
        break;

    case 'group_post_delete':
        $p = row('SELECT p.id, p.user_id, g.slug, g.owner_id FROM group_posts p JOIN groups_ g ON g.id = p.group_id WHERE p.id = ?', [(int)($_POST['post_id'] ?? 0)]);
        if ($p && ((int)$p['user_id'] === $uid || (int)$p['owner_id'] === $uid || is_staff($u))) {
            q('DELETE FROM group_posts WHERE id = ?', [$p['id']]);
            redirect('g/' . $p['slug']);
        }
        break;

    default:
        $result = ['ok' => false, 'error' => 'Unknown action'];
}

if ($json) {
    json_out($result, $result['ok'] ? 200 : 400);
}
header('Location: ' . safe_back('feed'));
exit;
