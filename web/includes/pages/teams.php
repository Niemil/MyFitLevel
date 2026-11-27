<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
game_ensure();
game_team_cleanup();
$mine = game_team_of($uid);
$createCost = (int)game_cfg('team_create_cost');
$wallet = game_wallet($uid);
$emojis = ['🛡️', '⚔️', '🐺', '🦁', '🐉', '🔥', '⚡', '🌲', '🏔️', '🦅', '🐻', '💪'];
$viewId = isset($args[0]) ? (int)$args[0] : 0;

function team_free_slots(int $tid): int
{
    $t = row('SELECT level FROM teams WHERE id = ?', [$tid]);
    return $t ? game_team_slots((int)$t['level']) - (int)val('SELECT COUNT(*) FROM team_members WHERE team_id = ?', [$tid]) : 0;
}

function team_staff_ids(int $tid): array
{
    return array_map('intval', array_column(rows("SELECT user_id FROM team_members WHERE team_id = ? AND role IN ('leader','officer')", [$tid]), 'user_id'));
}

function team_chat_ensure(): void
{
    static $ok = false;
    if ($ok) { return; }
    db()->exec("CREATE TABLE IF NOT EXISTS team_chat (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, team_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
        body VARCHAR(400) NOT NULL, created_at DATETIME NOT NULL, KEY idx_tc (team_id, id),
        CONSTRAINT fk_tc_t FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        CONSTRAINT fk_tc_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ok = true;
}

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $tid = (int)($_POST['tid'] ?? 0);
    $team = $tid ? row('SELECT * FROM teams WHERE id = ?', [$tid]) : null;
    $target = (int)($_POST['uid'] ?? 0);
    $back = $tid ? 'team/' . $tid : 'teams';

    if ($do === 'chat_say' || $do === 'chat_del') {
        // team chat: members only, plain text, light on the server
        team_chat_ensure();
        if (!$mine || (int)$mine['id'] !== $tid) {
            flash('Only team members can use the team chat.', 'err');
        } elseif ($do === 'chat_say') {
            $msg = clean_text((string)($_POST['msg'] ?? ''), 400);
            if ($msg === '') {
                flash('Write a message first.', 'err');
            } elseif (!rate_ok('tchat:' . $uid, 12, 60)) {
                flash('Slow down a little, try again in a minute.', 'err');
            } else {
                q('INSERT INTO team_chat (team_id, user_id, body, created_at) VALUES (?,?,?,?)', [$tid, $uid, $msg, now()]);
                q('DELETE FROM team_chat WHERE team_id = ? AND id < COALESCE((SELECT m FROM (SELECT MIN(id) AS m FROM (SELECT id FROM team_chat WHERE team_id = ? ORDER BY id DESC LIMIT 200) z) y), 0)', [$tid, $tid]);
            }
        } else {
            $cid = (int)($_POST['cid'] ?? 0);
            $c = row('SELECT user_id FROM team_chat WHERE id = ? AND team_id = ?', [$cid, $tid]);
            if ($c && ((int)$c['user_id'] === $uid || in_array($mine['role'], ['leader', 'officer'], true))) {
                q('DELETE FROM team_chat WHERE id = ?', [$cid]);
            }
        }
        redirect($back . '#chat');
    }

    if ($do === 'create') {
        $name = clean_text((string)($_POST['name'] ?? ''), 30);
        $desc = clean_text((string)($_POST['description'] ?? ''), 300);
        $em = in_array($_POST['emoji'] ?? '', $emojis, true) ? $_POST['emoji'] : '🛡️';
        if ($mine) {
            flash('Leave your current team first.', 'err');
        } elseif (mb_strlen($name) < 3) {
            flash('Give the team a name (at least 3 characters).', 'err');
        } elseif (row('SELECT id FROM teams WHERE name = ?', [$name])) {
            flash('That team name is taken.', 'err');
        } elseif (!rate_ok('tcreate:' . $uid, 3, 86400)) {
            flash('You can create up to 3 teams per day.', 'err');
        } elseif ((int)game_wallet($uid)['coins'] < $createCost) {
            flash('Starting a team costs ' . $createCost . ' coins and you have ' . (int)game_wallet($uid)['coins'] . '. Join an open team, or ask a friend to start one and invite you.', 'err');
        } else {
            game_coins_add($uid, -$createCost, 'team_create', 'Founded ' . $name);
            q('INSERT INTO teams (name, emoji, description, owner_id, is_open, created_at) VALUES (?,?,?,?,?,?)', [$name, $em, $desc ?: null, $uid, empty($_POST['closed']) ? 1 : 0, now()]);
            $tid = (int)db()->lastInsertId();
            q("INSERT INTO team_members (team_id, user_id, role, joined_at) VALUES (?,?,'leader',?)", [$tid, $uid, now()]);
            q('DELETE FROM team_requests WHERE user_id = ?', [$uid]);
            check_awards($uid);
            flash('Team created. Invite friends or let people join.');
            redirect('team/' . $tid);
        }
        redirect('teams');
    }

    if ($team) {
        $role = ($mine && (int)$mine['id'] === $tid) ? $mine['role'] : null;
        $staff = in_array($role, ['leader', 'officer'], true);
        $tname = $team['name'];

        if ($do === 'join' && !$mine && (int)$team['is_open'] === 1) {
            if (team_free_slots($tid) <= 0) {
                flash('That team is full right now.', 'err');
            } else {
                q("INSERT INTO team_members (team_id, user_id, role, joined_at) VALUES (?,?,'member',?)", [$tid, $uid, now()]);
                q('DELETE FROM team_requests WHERE user_id = ?', [$uid]);
                game_team_notify_all($tid, 'team', '👋 ' . display_name($u) . ' joined ' . $tname . '.', $uid);
                check_awards($uid);
                flash('Welcome to ' . $tname . '!');
            }
        } elseif ($do === 'apply' && !$mine) {
            if ((int)$team['is_open'] === 1) {
                flash('That team is open: just join it.');
            } elseif (!rate_ok('tapply:' . $uid, 10, 86400)) {
                flash('Too many requests today.', 'err');
            } else {
                q("INSERT IGNORE INTO team_requests (team_id, user_id, kind, created_at) VALUES (?,?,'apply',?)", [$tid, $uid, now()]);
                foreach (team_staff_ids($tid) as $s) {
                    game_notify($s, 'team', '📨 ' . display_name($u) . ' wants to join ' . $tname . '.');
                }
                flash('Request sent. The team leaders will see it.');
            }
        } elseif ($do === 'cancel_apply') {
            q("DELETE FROM team_requests WHERE team_id = ? AND user_id = ? AND kind = 'apply'", [$tid, $uid]);
        } elseif ($do === 'accept_invite' && !$mine && row("SELECT 1 FROM team_requests WHERE team_id = ? AND user_id = ? AND kind = 'invite'", [$tid, $uid])) {
            if (team_free_slots($tid) <= 0) {
                flash('That team is full right now.', 'err');
            } else {
                q("INSERT INTO team_members (team_id, user_id, role, joined_at) VALUES (?,?,'member',?)", [$tid, $uid, now()]);
                q('DELETE FROM team_requests WHERE user_id = ?', [$uid]);
                game_team_notify_all($tid, 'team', '👋 ' . display_name($u) . ' accepted the invite and joined ' . $tname . '.', $uid);
                check_awards($uid);
                flash('Welcome to ' . $tname . '!');
            }
        } elseif ($do === 'decline_invite') {
            q("DELETE FROM team_requests WHERE team_id = ? AND user_id = ? AND kind = 'invite'", [$tid, $uid]);
        } elseif ($staff && in_array($do, ['approve', 'deny'], true) && $target) {
            $req = row("SELECT * FROM team_requests WHERE team_id = ? AND user_id = ? AND kind = 'apply'", [$tid, $target]);
            if ($req) {
                if ($do === 'approve') {
                    if (game_team_of($target)) {
                        q('DELETE FROM team_requests WHERE team_id = ? AND user_id = ?', [$tid, $target]);
                        flash('They already joined another team.', 'err');
                    } elseif (team_free_slots($tid) <= 0) {
                        flash('The team is full. Level it up for more room.', 'err');
                    } else {
                        q("INSERT INTO team_members (team_id, user_id, role, joined_at) VALUES (?,?,'member',?)", [$tid, $target, now()]);
                        q('DELETE FROM team_requests WHERE user_id = ?', [$target]);
                        game_notify($target, 'team', '✅ You were accepted into ' . $tname . '!');
                        check_awards($target);
                        flash('Member added.');
                    }
                } else {
                    q('DELETE FROM team_requests WHERE team_id = ? AND user_id = ?', [$tid, $target]);
                    game_notify($target, 'team', $tname . ' could not take you this time.');
                }
            }
        } elseif ($staff && $do === 'invite') {
            $inv = row('SELECT id, username FROM users WHERE username = ? AND is_banned = 0', [trim((string)($_POST['username'] ?? ''))]);
            if (!$inv) {
                flash('No member with that username.', 'err');
            } elseif (game_team_of((int)$inv['id'])) {
                flash('They are already in a team.', 'err');
            } elseif (!rate_ok('tinvite:' . $uid, 20, 86400)) {
                flash('Too many invites today.', 'err');
            } else {
                q("INSERT INTO team_requests (team_id, user_id, kind, created_at) VALUES (?,?,'invite',?) ON DUPLICATE KEY UPDATE kind = 'invite', created_at = VALUES(created_at)", [$tid, $inv['id'], now()]);
                game_notify((int)$inv['id'], 'team', '💌 ' . display_name($u) . ' invited you to ' . $tname . '. Open Teams to answer.');
                flash('Invite sent to @' . $inv['username'] . '.');
            }
        } elseif ($staff && $do === 'kick' && $target && $target !== $uid) {
            $m = row('SELECT role FROM team_members WHERE team_id = ? AND user_id = ?', [$tid, $target]);
            if ($m && $m['role'] !== 'leader' && ($role === 'leader' || $m['role'] === 'member')) {
                q('DELETE FROM team_members WHERE team_id = ? AND user_id = ?', [$tid, $target]);
                game_notify($target, 'team', 'You were removed from ' . $tname . '.');
                flash('Member removed.');
            }
        } elseif ($role === 'leader' && in_array($do, ['promote', 'demote'], true) && $target) {
            q('UPDATE team_members SET role = ? WHERE team_id = ? AND user_id = ? AND role <> ?', [$do === 'promote' ? 'officer' : 'member', $tid, $target, 'leader']);
        } elseif ($role === 'leader' && $do === 'make_leader' && $target && $target !== $uid && row('SELECT 1 FROM team_members WHERE team_id = ? AND user_id = ?', [$tid, $target])) {
            q("UPDATE team_members SET role = 'officer' WHERE team_id = ? AND user_id = ?", [$tid, $uid]);
            q("UPDATE team_members SET role = 'leader' WHERE team_id = ? AND user_id = ?", [$tid, $target]);
            q('UPDATE teams SET owner_id = ? WHERE id = ?', [$target, $tid]);
            game_notify($target, 'team', '👑 You are now the leader of ' . $tname . '.');
            flash('Leadership handed over.');
        } elseif ($role === 'leader' && $do === 'settings') {
            $em = in_array($_POST['emoji'] ?? '', $emojis, true) ? $_POST['emoji'] : $team['emoji'];
            q('UPDATE teams SET description = ?, is_open = ?, emoji = ? WHERE id = ?', [clean_text((string)($_POST['description'] ?? ''), 300) ?: null, empty($_POST['closed']) ? 1 : 0, $em, $tid]);
            flash('Team updated.');
        } elseif ($role && $do === 'donate') {
            $amt = (int)($_POST['amount'] ?? 0);
            if ($amt < 10 || $amt > 5000) {
                flash('Give between 10 and 5000 coins at a time.', 'err');
            } elseif ((int)game_wallet($uid)['coins'] < $amt) {
                flash('You do not have that many coins.', 'err');
            } else {
                game_coins_add($uid, -$amt, 'team_gift', 'Gift to ' . $tname);
                q('UPDATE teams SET vault = vault + ? WHERE id = ?', [$amt, $tid]);
                q('INSERT INTO team_gifts (team_id, user_id, amount, created_at) VALUES (?,?,?,?)', [$tid, $uid, $amt, now()]);
                game_team_xp_add($tid, intdiv($amt, 10), 'gift');
                flash('You gave ' . $amt . ' coins to the team vault. Thank you!');
            }
        } elseif ($staff && $do === 'pick_boss') {
            $key = (string)($_POST['boss'] ?? '');
            $idx = -1;
            foreach (game_roster() as $i => $r0) {
                if ($r0[0] === $key) {
                    $idx = $i;
                }
            }
            if ($idx < 0 || (int)$team['level'] < game_raid_unlocks()[$idx]) {
                flash('Your team has not unlocked that boss yet.', 'err');
            } elseif (game_boss_team($tid)) {
                flash('The team already has a boss to fight.', 'err');
            } else {
                $ref = row('SELECT * FROM bosses WHERE team_id = ? ORDER BY id DESC LIMIT 1', [$tid]);
                $wait = $ref ? strtotime((string)($ref['ended_at'] ?: $ref['started_at'])) + (int)game_cfg('respawn_hours') * 3600 : 0;
                if ($wait > time()) {
                    flash('The next raid boss can be picked in ' . max(1, (int)ceil(($wait - time()) / 3600)) . ' h.', 'err');
                } else {
                    $nb = game_boss_spawn($tid, $key);
                    if ($nb) {
                        game_team_notify_all($tid, 'team', '⚔️ ' . display_name($u) . ' called ' . $nb['name'] . '. Time to train!');
                        flash('The raid has begun!');
                    }
                }
            }
        } elseif ($role === 'leader' && $do === 'vault_who') {
            q('UPDATE teams SET vault_who = ? WHERE id = ?', [($_POST['who'] ?? '') === 'leader' ? 'leader' : 'officer', $tid]);
            flash('Vault permission saved.');
        } elseif ($role && $do === 'buy_buff' && ($role === 'leader' || $team['vault_who'] === 'officer')) {
            $defs = game_team_buffs_def();
            $code = (string)($_POST['code'] ?? '');
            if (isset($defs[$code])) {
                [$bn, $bi, $kind, $pct, $hrs, $cost] = $defs[$code];
                $paid = q('UPDATE teams SET vault = vault - ? WHERE id = ? AND vault >= ?', [$cost, $tid, $cost])->rowCount();
                if (!$paid) {
                    flash('The vault needs ' . $cost . ' coins for that.', 'err');
                } else {
                    q('INSERT INTO team_buffs (team_id, code, until) VALUES (?,?,?) ON DUPLICATE KEY UPDATE until = DATE_ADD(GREATEST(until, ?), INTERVAL ? HOUR)',
                      [$tid, $code, date('Y-m-d H:i:s', time() + $hrs * 3600), now(), $hrs]);
                    game_team_notify_all($tid, 'team', $bi . ' ' . display_name($u) . ' activated ' . $bn . ' for the team: ' . $defs[$code][6] . '.');
                    flash($bn . ' is active.');
                }
            }
        } elseif ($role && $do === 'leave') {
            $others = (int)val('SELECT COUNT(*) FROM team_members WHERE team_id = ? AND user_id <> ?', [$tid, $uid]);
            if (!$others) {
                q('DELETE FROM teams WHERE id = ?', [$tid]);
                flash('You left and the empty team was closed.');
                redirect('teams');
            }
            if ($role === 'leader') {
                $next = row("SELECT user_id FROM team_members WHERE team_id = ? AND user_id <> ? ORDER BY (role = 'officer') DESC, joined_at ASC LIMIT 1", [$tid, $uid]);
                q("UPDATE team_members SET role = 'leader' WHERE team_id = ? AND user_id = ?", [$tid, $next['user_id']]);
                q('UPDATE teams SET owner_id = ? WHERE id = ?', [$next['user_id'], $tid]);
                game_notify((int)$next['user_id'], 'team', '👑 You are now the leader of ' . $tname . '.');
            }
            q('DELETE FROM team_members WHERE team_id = ? AND user_id = ?', [$tid, $uid]);
            flash('You left ' . $tname . '.');
            redirect('teams');
        } elseif ($role === 'leader' && $do === 'disband' && ($_POST['sure'] ?? '') === '1') {
            q('DELETE FROM teams WHERE id = ?', [$tid]);
            flash('Team closed.');
            redirect('teams');
        }
    }
    redirect($back);
}

define('GAME_CSS', true);

/* ---------- One team ---------- */
if ($viewId) {
    $team = row('SELECT * FROM teams WHERE id = ?', [$viewId]);
    if (!$team) {
        http_response_code(404);
        layout_header('Team not found', 'groups');
        echo '<div class="card center"><h2>Team not found</h2><a class="btn" href="' . e(url('teams')) . '">All teams</a></div>';
        layout_footer();
        exit;
    }
    $tid = (int)$team['id'];
    $isMember = $mine && (int)$mine['id'] === $tid;
    $role = $isMember ? $mine['role'] : null;
    $staff = in_array($role, ['leader', 'officer'], true);
    if ($isMember) {
        game_team_check($tid);
        $team = row('SELECT * FROM teams WHERE id = ?', [$tid]);
    }
    $members = rows("SELECT m.*, us.username, us.display_name, us.level FROM team_members m JOIN users us ON us.id = m.user_id WHERE m.team_id = ?
                     ORDER BY (m.role = 'leader') DESC, (m.role = 'officer') DESC, us.xp DESC", [$tid]);
    $lvl = (int)$team['level'];
    $max = game_team_max_level();
    $slotsMax = game_team_slots($lvl);
    $xpNow = (int)$team['xp'];
    $from = game_team_xp_need($lvl);
    $to = $lvl >= $max ? $from : game_team_xp_need($lvl + 1);
    $pct = $lvl >= $max ? 100 : (int)round(($xpNow - $from) / max(1, $to - $from) * 100);
    $raid = $isMember ? game_boss_team($tid) : null;
    $canVault = $role === 'leader' || ($staff && ($team['vault_who'] ?? 'officer') === 'officer');
    $tstats = $isMember ? game_team_stats($tid) : null;
    $lastRaid = $isMember && !$raid ? row('SELECT * FROM bosses WHERE team_id = ? ORDER BY id DESC LIMIT 1', [$tid]) : null;
    $waitTs = $lastRaid ? strtotime((string)($lastRaid['ended_at'] ?: $lastRaid['started_at'])) + (int)game_cfg('respawn_hours') * 3600 : 0;
    $raidHits = $raid ? array_column(rows('SELECT user_id, damage FROM boss_hits WHERE boss_id = ?', [$raid['id']]), 'damage', 'user_id') : [];
    $quests = game_team_quests($team);
    $reqs = $staff ? rows("SELECT r.*, us.username, us.display_name, us.level FROM team_requests r JOIN users us ON us.id = r.user_id WHERE r.team_id = ? AND r.kind = 'apply'", [$tid]) : [];
    $myReq = !$isMember ? row('SELECT * FROM team_requests WHERE team_id = ? AND user_id = ?', [$tid, $uid]) : null;
    layout_header($team['name'] . ' (team)', 'groups');
    ?>
<p><a href="<?= e(url('teams')) ?>">← All teams</a></p>
<div class="card tm-head">
  <span class="tm-emoji"><?= e($team['emoji']) ?></span>
  <div class="grow">
    <h1 style="margin:0"><?= e($team['name']) ?> <small class="tm-badge"><?= (int)$team['is_open'] ? 'Open' : 'Closed' ?></small></h1>
    <?php if ($team['description']): ?><p class="muted" style="margin:.2rem 0"><?= e($team['description']) ?></p><?php endif; ?>
    <div class="tm-lvl"><b>Team level <?= $lvl ?><?= $lvl >= $max ? ' (max)' : '' ?></b> · <?= count($members) ?>/<?= $slotsMax ?> members</div>
    <div class="xpbar"><i style="width:<?= $pct ?>%"></i></div>
    <div class="xpnote"><?= $lvl >= $max ? 'Max level reached 🏰' : number_format($xpNow) . ' / ' . number_format($to) . ' team points to level ' . ($lvl + 1) . ' (+' . (game_team_slots($lvl + 1) - $slotsMax) . ' slots)' ?></div>
  </div>
</div>

<?php if (!$isMember): ?>
<div class="card center">
  <?php if ($mine): ?><p class="muted">You are in another team. Leave it first to join this one.</p>
  <?php elseif ($myReq && $myReq['kind'] === 'invite'): ?>
    <p><b>You were invited!</b></p>
    <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="accept_invite"><button class="btn" type="submit">Accept</button></form>
    <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="decline_invite"><button class="btn ghost" type="submit">Decline</button></form>
  <?php elseif ($myReq): ?>
    <p class="muted">Request sent. Waiting for a team leader.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="cancel_apply"><button class="btn ghost small" type="submit">Cancel request</button></form>
  <?php elseif (count($members) >= $slotsMax): ?><p class="muted">This team is full right now.</p>
  <?php else: ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="<?= (int)$team['is_open'] ? 'join' : 'apply' ?>">
      <button class="btn big" type="submit"><?= (int)$team['is_open'] ? 'Join this team' : 'Ask to join' ?></button></form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($isMember): team_chat_ensure();
    $chat = array_reverse(rows('SELECT c.id, c.user_id, c.body, c.created_at, us.username, us.display_name FROM team_chat c JOIN users us ON us.id = c.user_id WHERE c.team_id = ? ORDER BY c.id DESC LIMIT 40', [$tid])); ?>
<div class="card" id="chat">
  <h3>Team chat</h3>
  <?php if (!$chat): ?><p class="muted small">No messages yet. Say hello to your team.</p><?php endif; ?>
  <div class="tchat">
  <?php foreach ($chat as $c): $mineMsg = (int)$c['user_id'] === $uid; ?>
    <div class="tc-msg<?= $mineMsg ? ' me' : '' ?>">
      <div class="tc-h"><a href="<?= e(url('u/' . $c['username'])) ?>"><b><?= e($c['display_name'] ?: $c['username']) ?></b></a> <small class="muted"><?= e(rel_time($c['created_at'])) ?></small>
        <?php if ($mineMsg || $staff): ?><form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="chat_del"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><button class="tc-x" type="submit" title="Delete">Delete</button></form><?php endif; ?></div>
      <div class="tc-b"><?= nl2br(e($c['body'])) ?></div>
    </div>
  <?php endforeach; ?>
  </div>
  <form method="post" class="tc-form"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="chat_say">
    <input name="msg" maxlength="400" placeholder="Message your team" autocomplete="off" required><button class="btn small" type="submit">Send</button></form>
  <p class="muted small" style="margin-bottom:0">Only your team can see this. The last 200 messages are kept. Reload the page to see new ones.</p>
</div>
<?php endif; ?>

<?php if ($isMember): ?>
<div class="card">
  <h3>⚔️ Team raid</h3>
  <p class="muted small" style="margin-top:0">A boss only your team fights. Your workouts also hit the public boss at the same time. Defeat it for coins and team points.</p>
  <?php game_boss_card($raid, 'full', $raid ? (int)($raidHits[$uid] ?? 0) : null); ?>
  <?php if ($raid) { game_boss_log_card((int)$raid['id'], $uid, true); } ?>
  <?php if (!$raid): ?>
    <h4 style="margin:.8rem 0 .3rem">Choose the next raid boss</h4>
    <?php if ($waitTs > time()): ?><p class="muted small">The next boss can be called in about <?= max(1, (int)ceil(($waitTs - time()) / 3600)) ?> h. Rest up!</p>
    <?php else: ?><p class="muted small">Bigger bosses have more health and pay more coins and team points. If nobody picks within a day, the next one in the cycle appears by itself.<?= $staff ? '' : ' A leader or officer picks.' ?></p>
      <div class="gshop">
      <?php foreach (game_roster() as $i => $rb): $need = game_raid_unlocks()[$i]; $open = $lvl >= $need; ?>
        <form method="post" class="gitem<?= $open ? '' : ' locked' ?>"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="pick_boss"><input type="hidden" name="boss" value="<?= e($rb[0]) ?>">
          <span class="gi-ic"><?= e($rb[2]) ?></span><b><?= e($rb[1]) ?></b>
          <small>Rewards ×<?= number_format(game_raid_diff($rb[0]), 1) ?></small>
          <?php if (!$open): ?><small>🔒 Team level <?= $need ?></small><?php elseif ($staff): ?><button class="btn small" type="submit">Fight</button><?php endif; ?>
        </form>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<div class="card">
  <h3>🗺️ This week's team quests</h3>
  <?php foreach ($quests as $qq): $p = (int)min(100, round($qq['progress'] / max(1, $qq['target']) * 100)); ?>
    <div class="tm-quest<?= $qq['done'] ? ' done' : '' ?>">
      <span class="tq-ic"><?= e($qq['icon']) ?></span>
      <div class="grow"><b><?= e($qq['title']) ?></b> <small class="muted"><?= e($qq['desc']) ?></small>
        <div class="xpbar"><i style="width:<?= $p ?>%"></i></div>
        <div class="xpnote"><?= $qq['done'] ? 'Done ✔ +60 team points and coins' : number_format(min($qq['progress'], $qq['target']), $qq['code'] === 'km' ? 1 : 0) . ' / ' . number_format($qq['target']) ?></div></div>
    </div>
  <?php endforeach; ?>
  <p class="muted small" style="margin-bottom:0">New quests every Monday. Any member can add progress, and walks and runs count.</p>
</div>
<?php endif; ?>

<?php if ($isMember): ?>
<div class="card">
  <h3>📊 Team stats</h3>
  <div class="gb-facts">
    <div><b>🏋️ <?= number_format($tstats['kg']) ?> kg</b><span>total lifted</span></div>
    <div><b><?= number_format($tstats['wo']) ?></b><span>workouts</span></div>
    <div><b><?= number_format($tstats['pts']) ?></b><span>points</span></div>
  </div>
  <div class="gb-facts" style="margin-top:8px">
    <div><b>🏃 <?= number_format($tstats['km'], 1) ?> km</b><span>run / walked</span></div>
    <div><b>🏅 <?= number_format($tstats['prs']) ?></b><span>personal records</span></div>
    <div><b>💀 <?= $tstats['kills'] ?></b><span>raid bosses slain</span></div>
  </div>
  <p class="muted small" style="margin-bottom:0">Counted from the day each member joined. <?= number_format($tstats['dmg']) ?> raid damage dealt so far. <a href="<?= e(url('teams')) ?>#ranking">See the team ranking</a>.</p>
</div>
<?php endif; ?>

<?php if ($isMember):
    $bonus = game_team_bonus($tid);
    $buffDefs = game_team_buffs_def();
    $gifters = rows('SELECT us.username, us.display_name, SUM(g.amount) AS amt FROM team_gifts g JOIN users us ON us.id = g.user_id WHERE g.team_id = ? GROUP BY g.user_id ORDER BY amt DESC LIMIT 3', [$tid]);
    $myCoins = (int)game_wallet($uid)['coins']; ?>
<div class="card">
  <h3>🏦 Team vault &amp; buffs</h3>
  <p class="muted small" style="margin-top:0">Members give coins to the vault, <?= $role === 'leader' || ($team['vault_who'] ?? 'officer') === 'officer' ? 'leaders and officers' : 'the leader' ?> spend it on buffs that help everyone. Coins in the vault stay there, they can not be taken back out. Team level also gives permanent perks.</p>
  <div class="gb-facts">
    <div><b>🪙 <?= number_format((int)$team['vault']) ?></b><span>in the vault</span></div>
    <div><b>+<?= $bonus['passive_dmg'] ?>% dmg</b><span>perk from team level</span></div>
    <div><b>+<?= $bonus['passive_coin'] ?>% coins</b><span>perk from team level</span></div>
  </div>
  <form method="post" class="chalform"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="donate">
    <label>Give coins (you have <?= $myCoins ?>) <input name="amount" type="number" min="10" max="5000" value="<?= min(50, max(10, $myCoins)) ?>"></label>
    <button class="btn" type="submit" <?= $myCoins < 10 ? 'disabled' : '' ?>>Give to the vault</button></form>
  <p class="muted small">Every 10 coins given also adds 1 team points.<?php if ($gifters): ?> Top givers: <?php foreach ($gifters as $i => $gf): ?><?= $i ? ', ' : '' ?><?= e(display_name($gf)) ?> (<?= (int)$gf['amt'] ?>)<?php endforeach; ?>.<?php endif; ?></p>
  <div class="gshop">
  <?php foreach ($buffDefs as $code => [$bn, $bi, $kind, $pct, $hrs, $cost, $desc]): $on = $bonus['active'][$code] ?? null; ?>
    <form method="post" class="gitem<?= $on ? ' on' : '' ?>"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="buy_buff"><input type="hidden" name="code" value="<?= e($code) ?>">
      <span class="gi-ic"><?= e($bi) ?></span><b><?= e($bn) ?></b><small><?= e($desc) ?></small>
      <?php if ($on): ?><small class="ok">Active, <?= e(game_fmt_left((string)$on)) ?> left</small><?php endif; ?>
      <?php if ($canVault): ?><button class="btn small" type="submit" <?= (int)$team['vault'] < $cost ? 'disabled' : '' ?>>🪙 <?= $cost ?><?= $on ? ' (extend)' : '' ?></button><?php else: ?><small>Cost 🪙 <?= $cost ?></small><?php endif; ?>
    </form>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($isMember):
    $wkStart = game_week_key();
    $board = rows('SELECT us.username, us.display_name, COALESCE(SUM(w.points),0) AS pts, COUNT(w.id) AS wo FROM team_members m JOIN users us ON us.id = m.user_id LEFT JOIN workouts w ON w.user_id = m.user_id AND w.performed_at >= ? AND w.performed_at >= m.joined_at WHERE m.team_id = ? GROUP BY us.id ORDER BY pts DESC LIMIT 10', [$wkStart . ' 00:00:00', $tid]);
    $contest = game_contest_scores($wkStart);
    $pos = 0; foreach ($contest as $i => $ct) { if ((int)$ct['id'] === $tid) { $pos = $i + 1; } } ?>
<div class="card">
  <h3>🔥 This week in the team</h3>
  <?php foreach ($board as $i => $bm): ?>
    <div class="gb-row"><span class="grow"><b><?= ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) . '.' ?> <?= e(display_name($bm)) ?></b></span><span><?= number_format((int)$bm['pts']) ?> pts · <?= (int)$bm['wo'] ?> workouts</span></div>
  <?php endforeach; ?>
  <p class="muted small" style="margin-bottom:0">Weekly team contest (points per member, teams of 2+): <?= $pos ? 'your team is <b>#' . $pos . '</b> right now.' : 'train to get on the board.' ?> Top 3 teams win 300 / 150 / 75 coins for their vault every Monday.</p>
</div>
<?php endif; ?>

<div class="card">
  <h3>Members</h3>
  <?php foreach ($members as $m): $mid = (int)$m['user_id']; ?>
    <div class="gb-row">
      <a class="grow" href="<?= e(url('u/' . $m['username'])) ?>"><?= e(display_name($m)) ?> <small class="muted">Lv <?= (int)$m['level'] ?></small>
        <?php if ($m['role'] !== 'member'): ?><span class="tm-badge"><?= $m['role'] === 'leader' ? '👑 Leader' : '⭐ Officer' ?></span><?php endif; ?></a>
      <?php if ($isMember && $raid): ?><span class="muted small"><?= number_format((int)($raidHits[$mid] ?? 0)) ?> dmg</span><?php endif; ?>
      <?php if ($staff && $mid !== $uid && $m['role'] !== 'leader'): ?>
        <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="uid" value="<?= $mid ?>">
          <?php if ($role === 'leader'): ?>
            <button class="btn ghost small" name="do" value="<?= $m['role'] === 'officer' ? 'demote' : 'promote' ?>"><?= $m['role'] === 'officer' ? 'Demote' : 'Make officer' ?></button>
            <button class="btn ghost small" name="do" value="make_leader" onclick="return confirm('Hand over leadership?')">Make leader</button>
          <?php endif; ?>
          <?php if ($role === 'leader' || $m['role'] === 'member'): ?><button class="btn ghost small" name="do" value="kick" onclick="return confirm('Remove this member?')">Remove</button><?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($staff): ?>
<div class="card">
  <h3>Recruit</h3>
  <?php foreach ($reqs as $r): ?>
    <div class="gb-row"><a class="grow" href="<?= e(url('u/' . $r['username'])) ?>"><?= e(display_name($r)) ?> <small class="muted">Lv <?= (int)$r['level'] ?> wants to join</small></a>
      <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="uid" value="<?= (int)$r['user_id'] ?>">
        <button class="btn small" name="do" value="approve">Accept</button> <button class="btn ghost small" name="do" value="deny">Deny</button></form></div>
  <?php endforeach; ?>
  <form method="post" class="chalform"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="invite">
    <label>Invite by username <input name="username" required maxlength="30" placeholder="e.g. joey"></label>
    <button class="btn" type="submit">Send invite</button></form>
</div>
<?php endif; ?>

<?php if ($role === 'leader'): ?>
<div class="card">
  <h3>Team settings</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="settings">
    <label>Description <input name="description" maxlength="300" value="<?= e((string)$team['description']) ?>"></label>
    <label>Icon <select name="emoji"><?php foreach ($emojis as $em): ?><option <?= $em === $team['emoji'] ? 'selected' : '' ?>><?= e($em) ?></option><?php endforeach; ?></select></label>
    <label><input type="checkbox" name="closed" value="1" <?= (int)$team['is_open'] ? '' : 'checked' ?>> Closed team (invite or approval only)</label>
    <button class="btn" type="submit">Save</button></form>
  <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="vault_who">
    <label>Who may spend the vault <select name="who"><option value="officer" <?= ($team['vault_who'] ?? 'officer') === 'officer' ? 'selected' : '' ?>>Leader and officers</option><option value="leader" <?= ($team['vault_who'] ?? '') === 'leader' ? 'selected' : '' ?>>Only the leader</option></select></label>
    <button class="btn ghost small" type="submit">Save</button></form>
  <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="disband"><input type="hidden" name="sure" value="1">
    <button class="btn ghost small" type="submit" onclick="return confirm('Close this team for everyone?')">Close the team</button></form>
</div>
<?php endif; ?>
<?php if ($isMember): ?>
<form method="post" class="center"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= $tid ?>"><input type="hidden" name="do" value="leave">
  <button class="btn ghost small" type="submit" onclick="return confirm('Leave this team?')">Leave team</button></form>
<?php endif; ?>
<?php layout_footer('', ['game.js']); exit;
}

/* ---------- Team list ---------- */
$invites = rows("SELECT t.*, (SELECT COUNT(*) FROM team_members m WHERE m.team_id = t.id) AS n FROM team_requests r JOIN teams t ON t.id = r.team_id WHERE r.user_id = ? AND r.kind = 'invite'", [$uid]);
$list = rows('SELECT t.*, (SELECT COUNT(*) FROM team_members m WHERE m.team_id = t.id) AS n FROM teams t ORDER BY t.level DESC, t.xp DESC, t.id ASC LIMIT 60');
layout_header('Teams', 'groups');
?>
<h1 class="pagetitle">🛡️ Teams</h1>
<p class="muted">Train together. A team has its own weekly raid boss and team quests, levels up as it clears them and gains room for more members. Totally optional.</p>

<?php if ($mine): ?>
<div class="card tm-mine"><span class="tm-emoji"><?= e($mine['emoji']) ?></span><div class="grow"><b>Your team: <?= e($mine['name']) ?></b><div class="muted small">Level <?= (int)$mine['level'] ?> · <?= $mine['role'] === 'leader' ? 'Leader' : ucfirst($mine['role']) ?></div></div>
  <a class="btn" href="<?= e(url('team/' . $mine['id'])) ?>">Open</a></div>
<?php endif; ?>

<?php foreach ($invites as $t): ?>
<div class="card tm-mine"><span class="tm-emoji"><?= e($t['emoji']) ?></span><div class="grow"><b>Invite: <?= e($t['name']) ?></b><div class="muted small">Level <?= (int)$t['level'] ?> · <?= (int)$t['n'] ?> members</div></div>
  <form method="post" class="inl"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= (int)$t['id'] ?>"><button class="btn small" name="do" value="accept_invite">Accept</button> <button class="btn ghost small" name="do" value="decline_invite">Decline</button></form></div>
<?php endforeach; ?>

<?php if (!$mine): ?>
<div class="card">
  <h3>Start a team</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="create">
    <label>Name <input name="name" required minlength="3" maxlength="30" placeholder="e.g. Iron Wolves"></label>
    <label>Description <input name="description" maxlength="300" placeholder="What is your team about?"></label>
    <label>Icon <select name="emoji"><?php foreach ($emojis as $em): ?><option><?= e($em) ?></option><?php endforeach; ?></select></label>
    <label><input type="checkbox" name="closed" value="1"> Closed team (people must be invited or ask to join)</label>
    <p class="muted small">Starting a team costs <b>🪙 <?= $createCost ?></b> (you have <?= (int)$wallet['coins'] ?>). Joining is free. A team that never gets a second member closes after <?= (int)game_cfg('team_solo_days') ?> days. Easiest way to start with friends: one of you founds the team and invites the rest by username.</p>
    <button class="btn" type="submit" <?= (int)$wallet['coins'] < $createCost ? 'disabled' : '' ?>><?= (int)$wallet['coins'] < $createCost ? 'Need ' . ($createCost - (int)$wallet['coins']) . ' more coins' : 'Create team' ?></button></form>
</div>
<?php endif; ?>

<?php
$sorts = ['level' => 'Level', 'kg' => 'Weight lifted', 'wo' => 'Workouts', 'km' => 'Distance', 'pts' => 'Points', 'kills' => 'Boss kills'];
$sort = isset($sorts[$_GET['sort'] ?? '']) ? (string)$_GET['sort'] : 'level';
foreach ($list as &$t) {
    $t['st'] = game_team_stats((int)$t['id']);
}
unset($t);
usort($list, fn($a, $b) => $sort === 'level' ? [(int)$b['level'], (int)$b['xp']] <=> [(int)$a['level'], (int)$a['xp']] : $b['st'][$sort] <=> $a['st'][$sort]);
$medals = ['🥇', '🥈', '🥉'];
?>
<div class="card" id="ranking">
  <h3>🏆 Team ranking</h3>
  <p class="muted small" style="margin-top:0">
    <?php foreach ($sorts as $k => $lab): ?><a class="tm-badge" style="<?= $k === $sort ? 'font-weight:700' : '' ?>" href="<?= e(url('teams') . '?sort=' . $k . '#ranking') ?>"><?= e($lab) ?></a> <?php endforeach; ?></p>
  <?php if (!$list): ?><p class="muted">No teams yet. Start the first one!</p><?php endif; ?>
  <?php foreach ($list as $i => $t): $slots = game_team_slots((int)$t['level']); $st = $t['st'];
    $big = match ($sort) { 'kg' => number_format($st['kg']) . ' kg', 'wo' => number_format($st['wo']) . ' workouts', 'km' => number_format($st['km'], 1) . ' km', 'pts' => number_format($st['pts']) . ' pts', 'kills' => $st['kills'] . ' kills', default => 'Lv ' . (int)$t['level'] }; ?>
    <a class="gb-row tm-row" href="<?= e(url('team/' . $t['id'])) ?>"><span class="tm-emoji sm"><?= e($t['emoji']) ?></span>
      <span class="grow"><b><?= $medals[$i] ?? ($i + 1) . '.' ?> <?= e($t['name']) ?></b> <small class="tm-badge"><?= (int)$t['is_open'] ? 'Open' : 'Closed' ?></small><br>
        <small class="muted">Lv <?= (int)$t['level'] ?> · <?= (int)$t['n'] ?>/<?= $slots ?> members · 🏋️ <?= number_format($st['kg']) ?> kg · <?= number_format($st['wo']) ?> workouts · 🏃 <?= number_format($st['km'], 1) ?> km · 💀 <?= $st['kills'] ?></small></span>
      <b><?= e($big) ?></b></a>
  <?php endforeach; ?>
</div>
<?php layout_footer(); ?>
