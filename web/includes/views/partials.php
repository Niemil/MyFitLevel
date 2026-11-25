<?php
declare(strict_types=1);

function icon(string $name): string
{
    static $paths = [
        'feed'   => '<path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'quest'  => '<path d="M12 2l3 6 6 .9-4.5 4.3 1.1 6.3L12 16.8 6.4 19.5l1.1-6.3L3 8.9 9 8z"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'rank'   => '<path d="M6 21V10M12 21V3M18 21v-7"/>',
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'bell'   => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4zM10 21h4"/>',
        'menu'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'prop'   => '<path d="M7 11v9H3v-9zM7 11l4-8c1.6 0 2.5 1.2 2.2 2.8L12.8 9H19a2 2 0 0 1 2 2.4l-1.4 7A2 2 0 0 1 17.7 20H7"/>',
        'chat'   => '<path d="M4 5h16v11H9l-5 4z"/>',
        'trash'  => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'theme'  => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'shield' => '<path d="M12 2.5l8 3v6c0 5-3.4 8.6-8 10-4.6-1.4-8-5-8-10v-6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'medal'  => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14L7 22l5-3 5 3-1.5-8"/>',
        'users'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 3-5.5 6.5-5.5S15.5 16.400 15.500 20M16 4.700a3.500 3.500 0 0 1 0 6.600M18 14.800c2.200.700 3.500 2.400 3.500 5.200"/>',
        'group'  => '<path d="M12 3l8 4.500v9L12 21l-8-4.500v-9z"/><path d="M12 12l8-4.500M12 12v9M12 12L4 7.500"/>',
        'pin'    => '<path d="M12 21s7-6.100 7-11.500A7 7 0 0 0 5 9.500C5 14.900 12 21 12 21z"/><circle cx="12" cy="9.500" r="2.500"/>',
        'cog'    => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.900 4.900l2.100 2.100M17 17l2.100 2.100M4.900 19.100L7 17M17 7l2.100-2.100"/>',
        'help'   => '<circle cx="12" cy="12" r="9"/><path d="M9.500 9.500a2.500 2.500 0 1 1 3.500 2.300c-.700.300-1 .900-1 1.700M12 17h.01"/>',
        'list'   => '<path d="M8 6h13M8 12h13M8 18h13M3.500 6h.01M3.500 12h.01M3.500 18h.01"/>',
        'cal'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.200"/>',
        'clip'   => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9zM9 12l2 2 4-4M9 17h6"/>',
        'sword'  => '<path d="M14.500 3.500L21 3l-.500 6.500L9 21l-6-6zM14 10l-4 4M5 19l-2 2M3 21l3-3"/>',
        'bag'    => '<path d="M5 8h14l1 13H4zM9 8V6a3 3 0 0 1 6 0v2M9 13a3 3 0 0 0 6 0"/>',
        'castle' => '<path d="M4 21V9l2-2 2 2V6l2-2 2 2v3l2-2 2 2 2-2 2 2v12zM10 21v-5h4v5"/>',
        'shoe'   => '<path d="M3 17h18v3H3zM3 17c0-4 1-8 4-9l3 3 3 1 3 2c3 0 5 1 5 3"/><path d="M8 13l-1 3"/>',
        'coin'   => '<circle cx="12" cy="12" r="9"/><path d="M14.500 9.200c-.700-.800-1.700-1.200-2.500-1.200-1.500 0-2.500.800-2.500 2 0 3 5 1.800 5 4.600 0 1.200-1 2.100-2.600 2.100-1 0-2-.400-2.700-1.200M12 6.500V8M12 16v1.500"/>',
        'flag'   => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'chart'  => '<path d="M3 20h18M6 16l4-5 3 3 5-7"/><circle cx="18" cy="7" r="1.200"/>',
        'dumbbell'=> '<path d="M6.500 6.500v11M17.500 6.500v11M3.500 9v6M20.500 9v6M6.500 12h11"/>',
        'sparkle'=> '<path d="M12 3l1.800 5.200L19 10l-5.200 1.800L12 17l-1.800-5.200L5 10l5.200-1.800zM19 16l.800 2.200L22 19l-2.200.800L19 22l-.800-2.200L16 19l2.200-.800z"/>',
        'repeat' => '<path d="M17 2l4 4-4 4M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4M21 13v2a3 3 0 0 1-3 3H3"/>',
    ];
    return '<svg class="ic" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

function site_name(): string
{
    return (string)setting('site_name', APP_NAME_DEFAULT);
}

function site_tagline(): string
{
    return (string)setting('tagline', 'Free workout tracker with points, levels and quests');
}

/** Original mascot "Lev" (inline SVG). mood: happy | cheer | oops */
function mascot(string $mood = 'happy', int $size = 120, string $class = ''): string
{
    static $defs = false;
    $g = '';
    if (!$defs) {
        $defs = true;
        $g = '<defs><linearGradient id="lvBody" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#a98bff"/><stop offset="1" stop-color="#6d3df2"/></linearGradient>'
           . '<linearGradient id="lvBell" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e9edf7"/><stop offset="1" stop-color="#9aa6bf"/></linearGradient></defs>';
    }
    $mouth = $mood === 'cheer'
        ? '<path d="M82 118 Q100 146 118 118 Z" fill="#3a1a7a"/><path d="M90 130 Q100 139 110 130 Q100 134 90 130Z" fill="#ff7aa8"/>'
        : ($mood === 'oops'
            ? '<path d="M88 128 Q100 120 112 128" fill="none" stroke="#3a1a7a" stroke-width="5" stroke-linecap="round"/>'
            : '<path d="M84 120 Q100 138 116 120" fill="none" stroke="#3a1a7a" stroke-width="5" stroke-linecap="round"/>');
    return '<svg class="mascot ' . e($class) . ' m-' . e($mood) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 200 200" role="img" aria-label="Lev, the MyFitLevel mascot">'
        . $g
        . '<ellipse class="m-shadow" cx="100" cy="188" rx="56" ry="8" fill="#000" opacity=".16"/>'
        . '<g class="m-body">'
        . '<g class="m-armL"><ellipse cx="34" cy="132" rx="14" ry="11" fill="#7d51f6" transform="rotate(25 34 132)"/></g>'
        . '<path d="M100 30C150 30 174 72 174 114C174 158 142 180 100 180C58 180 26 158 26 114C26 72 50 30 100 30Z" fill="url(#lvBody)"/>'
        . '<path d="M52 70C60 50 78 40 96 38" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="7" stroke-linecap="round"/>'
        . '<path class="m-bolt" d="M106 6L86 36H103L95 58L122 24H106Z" fill="#ffd23f" stroke="#e9a800" stroke-width="2" stroke-linejoin="round"/>'
        . '<ellipse cx="62" cy="116" rx="11" ry="7" fill="#ff7aa8" opacity=".55"/><ellipse cx="138" cy="116" rx="11" ry="7" fill="#ff7aa8" opacity=".55"/>'
        . '<g class="m-eyes"><ellipse cx="78" cy="94" rx="15" ry="17" fill="#fff"/><ellipse cx="122" cy="94" rx="15" ry="17" fill="#fff"/>'
        . '<circle cx="81" cy="97" r="9" fill="#2a1260"/><circle cx="119" cy="97" r="9" fill="#2a1260"/>'
        . '<circle cx="84" cy="93" r="3.2" fill="#fff"/><circle cx="122" cy="93" r="3.2" fill="#fff"/></g>'
        . $mouth
        . '<ellipse cx="78" cy="180" rx="17" ry="8" fill="#5a2fd0"/><ellipse cx="122" cy="180" rx="17" ry="8" fill="#5a2fd0"/>'
        . '<g class="m-armR"><path d="M166 124C178 118 182 102 180 88" fill="none" stroke="#7d51f6" stroke-width="15" stroke-linecap="round"/>'
        . '<g class="m-bell"><rect x="168" y="76" width="26" height="6" rx="3" fill="#7a869e"/>'
        . '<rect x="164" y="62" width="9" height="34" rx="4" fill="url(#lvBell)"/><rect x="189" y="62" width="9" height="34" rx="4" fill="url(#lvBell)"/></g></g>'
        . '</g></svg>';
}

/**
 * Page header. $meta keys: desc, path (canonical path), index (bool, default false),
 * full_title (bool: use $title as-is), type (og:type), schema (list of JSON-LD arrays).
 */
function layout_header(string $title, string $active = '', array $meta = []): void
{
    $u = me();
    $unread = $u ? unread_count((int)$u['id']) : 0;
    $name = site_name();
    $fullTitle = !empty($meta['full_title']) ? $title : $title . ' · ' . $name;
    $desc = (string)($meta['desc'] ?? (site_tagline() . '. Track workouts, earn points, level up with friends.'));
    $index = !empty($meta['index']) && !$u;
    $canon = site_url((string)($meta['path'] ?? ltrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/')));
    if (isset($meta['path'])) {
        $canon = site_url((string)$meta['path']);
    }
    $img = site_url('assets/og-image.png');
    ?><!DOCTYPE html>
<html lang="en" data-theme="dark" data-skin="<?= e(active_skin($u ?? null)) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="robots" content="<?= $index ? 'index,follow,max-image-preview:large' : 'noindex,nofollow' ?>">
<?php if ($index): ?><link rel="canonical" href="<?= e($canon) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($name) ?>">
<meta property="og:type" content="<?= e($meta['type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canon) ?>">
<meta property="og:image" content="<?= e($img) ?>">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($fullTitle) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<meta name="twitter:image" content="<?= e($img) ?>">
<meta name="color-scheme" content="dark light">
<meta name="theme-color" content="#22303e">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" type="image/png" href="<?= e(asset('icon-192.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('icon-192.png')) ?>">
<script src="<?= e(asset('theme.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<?php if (defined('GAME_CSS')): ?><link rel="stylesheet" href="<?= e(asset('game.css')) ?>">
<?php endif; ?>
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="app-base" content="<?= e(base()) ?>">
<?php $sv = stats_track(); $svc = stats_consent_state(); if ($sv): ?><meta name="sv" content="<?= (int)$sv[0] . '.' . e($sv[1]) ?>">
<?php endif; ?><meta name="sc" content="<?= $svc ?>">
<?php foreach ((array)($meta['schema'] ?? []) as $sc): ?>
<script type="application/ld+json"><?= json_encode($sc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endforeach; ?>
</head>
<body class="<?= $u ? 'in' : 'out' ?>">
<?php if (setting('beta_banner', '1') === '1'): ?>
<div class="betabar" id="betabar" hidden><span>🚧 <b><?= e($name) ?></b> is brand new and still being built. Expect rough edges and tell us what you'd like to see!</span><button type="button" id="betaclose" aria-label="Hide message">×</button></div>
<?php endif; ?>
<header class="top">
  <a class="brand" href="<?= e(url($u ? 'feed' : '')) ?>"><span class="logo"><?= mascot('happy', 30, 'nomotion') ?></span><span class="bname"><?= e($name) ?></span></a>
  <?php if ($u): ?>
  <nav class="topnav">
    <a href="<?= e(url('feed')) ?>" class="<?= $active === 'feed' ? 'on' : '' ?>">Feed</a>
    <a href="<?= e(url('log')) ?>" class="<?= $active === 'log' ? 'on' : '' ?>">Track</a>
    <a href="<?= e(url('quests')) ?>" class="<?= $active === 'quests' ? 'on' : '' ?>">Quests</a>
    <a href="<?= e(url('leaderboard')) ?>" class="<?= $active === 'rank' ? 'on' : '' ?>">Leaders</a>
    <a href="<?= e(url('groups')) ?>" class="<?= $active === 'groups' ? 'on' : '' ?>">Groups</a>
    <a href="<?= e(url('members')) ?>" class="<?= $active === 'members' ? 'on' : '' ?>">Members</a>
    <a href="<?= e(url('gyms')) ?>" class="<?= $active === 'gyms' ? 'on' : '' ?>">Gyms</a>
  </nav>
  <?php endif; ?>
  <div class="topright">
    <button type="button" class="tswitch" id="themebtn" title="Switch light / dark" aria-label="Switch light or dark theme"><span class="tk"></span><i class="ts">☀</i><i class="tm">☾</i></button>
<?php if ($u): ?>
    <?php $dmN = dm_unread((int)$u['id']); ?>
    <a class="iconbtn" href="<?= e(url('messages')) ?>" title="Messages">
      <?= icon('chat') ?><?php if ($dmN): ?><span class="dot"><?= $dmN > 9 ? '9+' : $dmN ?></span><?php endif; ?>
    </a>
    <a class="iconbtn" href="<?= e(url('notifications')) ?>" title="Notifications">
      <?= icon('bell') ?><?php if ($unread): ?><span class="dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
    </a>
    <details class="menu">
      <summary class="iconbtn" title="Menu"><?= icon('menu') ?></summary>
      <div class="menu-pop">
        <?php $mp = level_progress((int)$u['xp']); ?>
        <a class="mhead" href="<?= e(url('u/' . $u['username'])) ?>">
          <?= uavatar($u, 48) ?>
          <span class="mwho"><b><?= e(display_name($u)) ?></b><small>Level <?= (int)$mp['level'] ?> · <?= number_format((int)$u['xp']) ?> pts</small>
            <span class="xpbar"><i style="width:<?= (int)$mp['pct'] ?>%"></i></span></span>
        </a>
        <div class="mgrid">
          <a href="<?= e(url('u/' . $u['username'])) ?>" style="--c:#4dabf7"><?= icon('user') ?><span>Profile</span></a>
          <a href="<?= e(url('messages')) ?>" style="--c:#63e6be"><?= icon('chat') ?><span>Messages</span></a>
          <a href="<?= e(url('badges')) ?>" style="--c:#ffa94d"><?= icon('medal') ?><span>Achievements</span></a>
          <a href="<?= e(url('goals')) ?>" style="--c:#ff6b6b"><?= icon('target') ?><span>Goals &amp; events</span></a>
          <a href="<?= e(url('lists')) ?>" style="--c:#69db7c"><?= icon('clip') ?><span>My lists</span></a>
          <a href="<?= e(url('boss')) ?>" style="--c:#ff922b"><?= icon('sword') ?><span>Weekly boss</span></a>
          <a href="<?= e(url('shop')) ?>" style="--c:#fcc419"><?= icon('bag') ?><span>Shop &amp; gear</span></a>
          <a href="<?= e(url('teams')) ?>" style="--c:#74c0fc"><?= icon('castle') ?><span>Teams</span></a>
          <a href="<?= e(url('steps')) ?>" style="--c:#38d9a9"><?= icon('shoe') ?><span>Steps</span></a>
          <a href="<?= e(url('history')) ?>" style="--c:#ffd43b"><?= icon('coin') ?><span>Points &amp; coins</span></a>
          <a href="<?= e(url('challenges')) ?>" style="--c:#da77f2"><?= icon('flag') ?><span>Challenges</span></a>
          <a href="<?= e(url('members')) ?>" style="--c:#91a7ff"><?= icon('users') ?><span>Members</span></a>
          <a href="<?= e(url('groups')) ?>" style="--c:#a9e34b"><?= icon('group') ?><span>Groups</span></a>
          <a href="<?= e(url('gyms')) ?>" style="--c:#f06595"><?= icon('pin') ?><span>Gyms</span></a>
          <a href="<?= e(url('routines')) ?>" style="--c:#66d9e8"><?= icon('dumbbell') ?><span>Routines</span></a>
          <a href="<?= e(url('routines#schedule')) ?>" style="--c:#e599f7"><?= icon('cal') ?><span>Schedule</span></a>
          <a href="<?= e(url('levels')) ?>" style="--c:#ff8787"><?= icon('chart') ?><span>Levels</span></a>
          <a href="<?= e(url('settings')) ?>" style="--c:#adb5bd"><?= icon('cog') ?><span>Settings</span></a>
        </div>
        <?php $isStaff = is_staff($u); if ($isStaff || $u['is_admin']): $nRep = $isStaff ? (int)val("SELECT COUNT(*) FROM reports WHERE status = 'open'") : 0; ?>
        <div class="mstaff">
          <?php if ($isStaff): ?><a href="<?= e(url('mod')) ?>">Moderation<?= $nRep ? ' · ' . $nRep : '' ?></a><?php endif; ?>
          <?php if ($u['is_admin']): ?><a href="<?= e(url('admin')) ?>">Admin</a><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="mfoot">
          <a href="<?= e(url('feedback')) ?>"><?= icon('help') ?> Send feedback</a>
          <a href="<?= e(url('points')) ?>"><?= icon('help') ?> How points work</a>
          <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit">Log out</button></form>
        </div>
      </div>
    </details>
<?php else: ?>
    <a class="btn ghost small hide-sm" href="<?= e(url('login')) ?>">Log in</a>
    <a class="btn small" href="<?= e(url('register')) ?>">Join free</a>
<?php endif; ?>
  </div>
</header>
<main class="wrap">
<?php foreach (flash_pull() as $f): ?>
  <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php
}

function layout_footer(string $active = '', array $scripts = []): void
{
    $u = me();
    ?>
</main>
<footer class="foot">
  <nav class="footlinks">
    <a href="<?= e(url('points')) ?>">How points work</a>
    <a href="<?= e(url('boss')) ?>">Weekly boss</a>
    <a href="<?= e(url('gyms')) ?>">Gyms</a>
    <a href="<?= e(url('exercises')) ?>">Exercise library</a>
    <a href="<?= e(url('fitocracy-alternative')) ?>">Fitocracy alternative</a>
    <a href="<?= e(url('updates')) ?>">Updates</a>
    <a href="<?= e(url('about')) ?>">About</a>
    <a href="<?= e(url('api-docs')) ?>">API</a>
    <a href="https://github.com/Niemil/myfitlevel" target="_blank" rel="noopener">Open source</a>
    <a href="<?= e(url('widgets')) ?>">Widgets</a>
    <a href="<?= e(url('connect')) ?>">Connect</a>
    <a href="<?= e(url('feedback')) ?>">Feedback</a>
    <a href="<?= e(url('privacy')) ?>">Privacy</a>
  </nav>
  <?= e(site_name()) ?> is a free, non-profit, community-run fitness tracker. Inspired by Fitocracy; not affiliated with or endorsed by it.
</footer>
<?php if ($u): ?>
<nav class="bottomnav">
  <a href="<?= e(url('feed')) ?>" class="<?= $active === 'feed' ? 'on' : '' ?>"><?= icon('feed') ?><span>Feed</span></a>
  <a href="<?= e(url('quests')) ?>" class="<?= $active === 'quests' ? 'on' : '' ?>"><?= icon('quest') ?><span>Quests</span></a>
  <a href="<?= e(url('log')) ?>" class="fab <?= $active === 'log' ? 'on' : '' ?>"><?= icon('plus') ?><span>Track</span></a>
  <a href="<?= e(url('leaderboard')) ?>" class="<?= $active === 'rank' ? 'on' : '' ?>"><?= icon('rank') ?><span>Leaders</span></a>
  <a href="<?= e(url('u/' . $u['username'])) ?>" class="<?= $active === 'me' ? 'on' : '' ?>"><?= icon('user') ?><span>Me</span></a>
</nav>
<?php endif; ?>
<script src="<?= e(asset('app.js')) ?>" defer></script>
<script src="<?= e(asset('photos.js')) ?>" defer></script>
<script src="<?= e(asset('fx.js')) ?>" defer></script>
<script src="<?= e(asset('stats.js')) ?>" defer></script>
<?php foreach ($scripts as $sj): ?>
<script src="<?= e(asset($sj)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

function level_chip(int $level): string
{
    return '<span class="lvl" title="Level ' . $level . ' · ' . level_title($level) . '">Lv ' . $level . '</span>';
}

function xp_bar(int $xp): string
{
    $p = level_progress($xp);
    if (!empty($p['max'])) {
        return '<div class="xpbar"><i style="width:100%"></i></div><div class="xpnote">Max level 🔱 ' . e(level_title($p['level'])) . '</div>';
    }
    return '<div class="xpbar" title="' . $p['into'] . ' / ' . $p['need'] . ' points"><i style="width:' . $p['pct'] . '%"></i></div>'
        . '<div class="xpnote">' . number_format($p['need'] - $p['into']) . ' pts to Level ' . ($p['level'] + 1) . '</div>';
}

function fmt_set(array $s, string $unit, ?string $orig = null): string
{
    if ($s['kind'] === 'cardio') {
        $out = fmt_duration((int)$s['duration_sec']);
        if ($s['distance_m']) {
            $out .= ' · ' . fmt_num($s['distance_m'] / 1000, 2) . ' km';
        }
        return $out;
    }
    if ($s['kind'] === 'duration') {
        return fmt_duration((int)$s['duration_sec']);
    }
    $w = $s['weight_kg'] !== null ? (float)$s['weight_kg'] : null;
    if ($w === null) {
        return $s['reps'] . ' reps';
    }
    $v = $unit === 'lb' ? $w * LB_PER_KG : $w;
    $prefix = $s['kind'] === 'bodyweight' ? '+' : '';
    $out = $prefix . fmt_num($v, 1) . '×' . $s['reps'];
    return $out;
}

/** Tooltip text: what the lifter actually typed, when that was in the other unit. */
function set_orig_title(array $s, string $unit, ?string $orig): string
{
    if ($orig === null || $orig === $unit || ($s['weight_kg'] ?? null) === null || in_array($s['kind'] ?? '', ['cardio', 'duration'], true)) {
        return '';
    }
    $w = (float)$s['weight_kg'];
    $ov = $orig === 'lb' ? round($w * LB_PER_KG * 2) / 2 : round($w * 2) / 2;
    return 'Logged as ' . fmt_num($ov, 1) . ' ' . $orig;
}

/**
 * Adds ['exercises', 'props', 'comments', 'propped'] to each workout row.
 */
function attach_workout_data(array $ws, int $viewerId): array
{
    if (!$ws) {
        return [];
    }
    $ids = array_map(fn($w) => (int)$w['id'], $ws);
    $in = in_clause($ids);

    $sets = rows("SELECT s.*, e.name, e.kind, e.slug FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id
                  WHERE s.workout_id IN ($in) ORDER BY s.workout_id, s.id", $ids);
    $byW = [];
    foreach ($sets as $s) {
        $byW[$s['workout_id']][$s['exercise_id']]['name'] = $s['name'];
        $byW[$s['workout_id']][$s['exercise_id']]['kind'] = $s['kind'];
        $byW[$s['workout_id']][$s['exercise_id']]['sets'][] = $s;
    }
    foreach (rows("SELECT workout_id, exercise_id, note FROM workout_ex_notes WHERE workout_id IN ($in)", $ids) as $nn) {
        if (isset($byW[$nn['workout_id']][$nn['exercise_id']])) {
            $byW[$nn['workout_id']][$nn['exercise_id']]['note'] = $nn['note'];
        }
    }
    $props = array_column(rows("SELECT workout_id, COUNT(*) c FROM props WHERE workout_id IN ($in) GROUP BY workout_id", $ids), 'c', 'workout_id');
    $coms = array_column(rows("SELECT workout_id, COUNT(*) c FROM comments WHERE workout_id IN ($in) GROUP BY workout_id", $ids), 'c', 'workout_id');
    $mine = array_column(rows("SELECT workout_id FROM props WHERE user_id = ? AND workout_id IN ($in)", array_merge([$viewerId], $ids)), 'workout_id');

    $gymIds = array_values(array_unique(array_filter(array_map(fn($w) => (int)($w['gym_id'] ?? 0), $ws))));
    $gyms = $gymIds ? rows('SELECT id, name, is_public, owner_id FROM gyms WHERE id IN (' . in_clause($gymIds) . ')', $gymIds) : [];
    $gymBy = [];
    foreach ($gyms as $gy) {
        $gymBy[(int)$gy['id']] = $gy;
    }

    foreach ($ws as &$w) {
        $gy = $gymBy[(int)($w['gym_id'] ?? 0)] ?? null;
        $w['gym_name'] = $gy['name'] ?? null;
        $w['gym_public'] = $gy['is_public'] ?? 0;
        $w['gym_owner'] = $gy['owner_id'] ?? 0;
        $w['exercises'] = array_values($byW[$w['id']] ?? []);
        $w['props'] = (int)($props[$w['id']] ?? 0);
        $w['comments'] = (int)($coms[$w['id']] ?? 0);
        $w['propped'] = in_array($w['id'], $mine);
    }
    return $ws;
}

function workout_card(array $w, ?array $viewer, bool $full = false): void
{
    $unit = $viewer['unit'] ?? 'kg';
    $name = display_name($w);
    $href = url('workout/' . $w['id']);
    ?>
<article class="card workout" id="w<?= (int)$w['id'] ?>">
  <header class="whead">
    <a href="<?= e(url('u/' . $w['username'])) ?>"><?= uavatar($w, 42) ?></a>
    <div class="who">
      <a class="uname" href="<?= e(url('u/' . $w['username'])) ?>"><?= e($name) ?></a> <?= level_chip((int)$w['level']) ?><?= role_badge($w) ?>
      <div class="when"><a href="<?= e($href) ?>"><?= e(rel_time($w['performed_at'])) ?></a><?= gym_chip($w, $viewer) !== '' ? ' · ' . gym_chip($w, $viewer) : '' ?></div>
    </div>
    <div class="pts"><b>+<?= number_format((int)$w['points']) ?></b><small>pts</small></div>
  </header>
  <?php if (!empty($w['title'])): ?><h3 class="wtitle"><?= e($w['title']) ?></h3><?php endif; ?>
  <?php
  $nSets = 0;
  foreach ($w['exercises'] as $ex0) { $nSets += count($ex0['sets']); }
  $fold = !$full && count($w['exercises']) > 0;
  if ($fold):
      $exNames = array_map(fn($x) => $x['name'], $w['exercises']);
  ?>
  <details class="exfold">
    <summary><span class="exsum"><b><?= e(pluralize(count($w['exercises']), 'exercise')) ?></b> · <?= e(pluralize($nSets, 'set')) ?>
      <small class="muted"><?= e(implode(', ', array_slice($exNames, 0, 3))) ?><?= count($exNames) > 3 ? '…' : '' ?></small></span>
      <span class="exmore">Show</span></summary>
  <?php endif; ?>
  <div class="exlist">
  <?php foreach ($w['exercises'] as $ex): ?>
    <div class="ex">
      <div class="exname"><?= e($ex['name']) ?>
        <?php foreach ($ex['sets'] as $s) { if ($s['is_pr']) { echo '<span class="pr" title="Personal record">PR</span>'; break; } } ?>
      </div>
      <?php if (!empty($ex['note'])): ?><div class="exnote">📝 <?= e($ex['note']) ?></div><?php endif; ?>
      <div class="sets">
        <?php
        $parts = [];
        foreach ($ex['sets'] as $s) {
            $s['kind'] = $ex['kind'];
            $tip = trim((!empty($s['is_warmup']) ? 'Warm-up set (half points)' : '') . ' ' . set_orig_title($s, $unit, $w['unit'] ?? null));
            $parts[] = '<span class="set' . ($s['is_pr'] ? ' pr' : '') . (!empty($s['is_warmup']) ? ' wu' : '') . '"'
                . ($tip !== '' ? ' title="' . e($tip) . '"' : '') . '>' . (!empty($s['is_warmup']) ? '<i>W</i>' : '') . e(fmt_set($s, $unit, $w['unit'] ?? null))
                . ((int)$s['points'] > 0 ? ' <small class="sp">+' . (int)$s['points'] . '</small>' : '') . '</span>';
        }
        echo implode('', $parts);
        ?><?php
        $hasW = false;
        foreach ($ex['sets'] as $s2) { if ($s2['weight_kg'] !== null) { $hasW = true; break; } }
        if ($hasW): ?><em class="unit"><?= e($unit) ?></em><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <?php if ($fold): ?></details><?php endif; ?>
  <?php if ($full && !empty($w['notes'])): ?><p class="notes"><?= nl2br(e($w['notes'])) ?></p><?php endif; ?>
  <footer class="wfoot">
    <?php if ($viewer): ?>
    <form method="post" action="<?= e(url('act/props')) ?>" data-ajax class="inl">
      <?= csrf_field() ?><input type="hidden" name="workout_id" value="<?= (int)$w['id'] ?>">
      <input type="hidden" name="back" value="<?= e(($_SERVER['REQUEST_URI'] ?? '/') . '#w' . $w['id']) ?>">
      <button class="act <?= $w['propped'] ? 'on' : '' ?>" type="submit" data-propbtn><?= icon('prop') ?> <span data-count><?= (int)$w['props'] ?></span> Props</button>
    </form>
    <?php else: ?>
    <a class="act" href="<?= e(url('login')) ?>"><?= icon('prop') ?> <?= (int)$w['props'] ?> Props</a>
    <?php endif; ?>
    <a class="act" href="<?= e($href) ?>#comments"><?= icon('chat') ?> <?= (int)$w['comments'] ?> Comments</a>
    <?php if ($viewer && (int)$w['user_id'] === (int)$viewer['id']): ?>
      <a class="act" href="<?= e(url('log?edit=' . $w['id'])) ?>">✏️ Edit</a>
      <a class="act" href="<?= e(url('log?repeat=' . $w['id'])) ?>"><?= icon('repeat') ?> Repeat</a>
    <?php endif; ?>
    <?= report_button($viewer, 'workout', (int)$w['id'], (int)$w['user_id']) ?>
  </footer>
</article>
<?php
}
