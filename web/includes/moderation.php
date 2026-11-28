<?php
declare(strict_types=1);

/* Roles (admin / moderator), reports, suspensions and the moderation log. */

function is_staff(?array $u): bool
{
    return $u && (!empty($u['is_admin']) || !empty($u['is_mod']));
}

/** Small shield icon next to the name of an admin or moderator. Looks the role up once per request. */
/** Columns for the member's own choices: avatar_mode ('photo' or 'hero') and hide_icons (comma list of: team, title, founder, role). */
function prof_look_ensure(): void
{
    static $done = false;
    if ($done || setting('prof_look_ok') === '1') { $done = true; return; }
    $done = true;
    if (!row("SHOW COLUMNS FROM users LIKE 'avatar_mode'")) {
        q("ALTER TABLE users ADD COLUMN avatar_mode VARCHAR(6) NOT NULL DEFAULT 'photo', ADD COLUMN hide_icons VARCHAR(60) NOT NULL DEFAULT ''");
    }
    set_setting('prof_look_ok', '1');
}

/** Icons a member chose to hide next to their name: [username => ['team','role',...]] */
function name_icons_hidden(string $username): array
{
    static $map = null;
    if ($map === null) {
        prof_look_ensure();
        $map = [];
        foreach (rows("SELECT username, hide_icons FROM users WHERE hide_icons <> ''") as $r) {
            $map[$r['username']] = explode(',', $r['hide_icons']);
        }
    }
    return $map[$username] ?? [];
}

function role_badge(array $r): string
{
    static $staff = null, $founder = null;
    if ($founder === null) {
        $founder = (string)val('SELECT username FROM users WHERE is_admin = 1 ORDER BY id ASC LIMIT 1');
    }
    if ($staff === null) {
        $staff = [];
        foreach (rows('SELECT username, is_admin, is_mod FROM users WHERE is_admin = 1 OR is_mod = 1') as $s) {
            $staff[$s['username']] = $s['is_admin'] ? 'admin' : 'mod';
        }
    }
    $hid = name_icons_hidden((string)($r['username'] ?? ''));
    $extra = game_user_badges((string)($r['username'] ?? ''));
    $role = $staff[$r['username'] ?? ''] ?? null;
    if (!$role) {
        return $extra;
    }
    $crown = ($role === 'admin' && ($r['username'] ?? '') === $founder && !in_array('founder', $hid, true)) ? '<span class="role founder" title="Founder of MyFitLevel">' . icon('crown') . '</span>' : '';
    return $extra . $crown . (in_array('role', $hid, true) ? '' : '<span class="role ' . $role . '" title="' . ($role === 'admin' ? 'Site admin' : 'Moderator') . '">' . icon('shield') . '</span>');
}

function mod_log(int $actor, string $action, ?int $targetUser, string $detail = ''): void
{
    q('INSERT INTO mod_log (actor_id, action, target_user_id, detail, created_at) VALUES (?,?,?,?,?)',
      [$actor, $action, $targetUser, mb_substr($detail, 0, 300), now()]);
}

/** Lifts suspensions that have run out (at most once a minute). */
function ban_sweep(): void
{
    if ((int)setting('ban_sweep', '0') > time() - 60) {
        return;
    }
    set_setting('ban_sweep', (string)time());
    q('UPDATE users SET is_banned = 0, banned_until = NULL, ban_reason = NULL WHERE is_banned = 1 AND banned_until IS NOT NULL AND banned_until < ?', [now()]);
}

/** $days = null for permanent. Staff can never suspend an admin; mods cannot suspend other mods. */
function ban_user(array $actor, int $targetId, ?int $days, string $reason): string
{
    $t = row('SELECT id, username, is_admin, is_mod FROM users WHERE id = ?', [$targetId]);
    if (!$t || (int)$t['id'] === (int)$actor['id']) {
        return 'Cannot do that.';
    }
    if ($t['is_admin'] || ($t['is_mod'] && empty($actor['is_admin']))) {
        return 'You cannot suspend that member.';
    }
    $until = $days ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
    q('UPDATE users SET is_banned = 1, banned_until = ?, ban_reason = ? WHERE id = ?', [$until, mb_substr($reason, 0, 200) ?: null, $targetId]);
    mod_log((int)$actor['id'], $days ? "suspend {$days}d" : 'suspend permanent', $targetId, $reason);
    return '';
}

const REPORT_REASONS = ['spam' => 'Spam or ads', 'abuse' => 'Harassment or abuse', 'offensive' => 'Offensive or inappropriate', 'fake' => 'Fake or misleading', 'other' => 'Something else'];

/** "Report" button + tiny form for someone else's content. */
function report_button(?array $viewer, string $kind, int $id, int $ownerId): string
{
    if (!$viewer || (int)$viewer['id'] === $ownerId) {
        return '';
    }
    $h = '<details class="reportbox"><summary class="act" title="Report"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg></summary>'
       . '<form method="post" action="' . e(url('act/report')) . '" class="reportform">' . csrf_field()
       . '<input type="hidden" name="kind" value="' . e($kind) . '"><input type="hidden" name="id" value="' . $id . '">'
       . '<input type="hidden" name="back" value="' . e($_SERVER['REQUEST_URI'] ?? '/') . '"><select name="reason">';
    foreach (REPORT_REASONS as $k => $lbl) {
        $h .= '<option value="' . e($k) . '">' . e($lbl) . '</option>';
    }
    return $h . '</select><button class="btn small" type="submit">Send report</button></form></details>';
}
