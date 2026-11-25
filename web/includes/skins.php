<?php
declare(strict_types=1);

/* Skins (colour themes). Light/dark stays a separate switch; a skin changes the palette. */

function skin_list(): array
{
    // key => [label, header colour, accent colour, accent 2, description]
    return [
        'auto'      => ['Automatic (seasons)', '#2c3e50', '#36a85c', '#d94f4f', 'Follows the season: festive at Christmas and Halloween, standard otherwise'],
        'levelup'   => ['Level Up (standard, no seasons)', '#2c3e50', '#36a85c', '#2c3e50', 'The standard look all year. Pick this to opt out of the seasonal themes'],
        'classic'   => ['Classic blue', '#2a76b0', '#f58220', '#2a76b0', 'Blue header, orange highlights and light grey pages, like the old days of gamified lifting sites'],
        'ocean'     => ['Ocean', '#0d3b66', '#1fa2c9', '#f4a259', 'Deep blue and teal'],
        'sunset'    => ['Sunset', '#4a2040', '#ff6b4a', '#ffb347', 'Warm orange and plum'],
        'forest'    => ['Forest', '#16352a', '#4caf50', '#c9a227', 'Deep green and gold'],
        'purple'    => ['Purple', '#2a1f4d', '#8b5cff', '#2ad4a1', 'The original purple look'],
        'christmas' => ['Christmas', '#14452f', '#c8102e', '#2e8b57', 'Red, green and gently falling snow'],
        'halloween' => ['Halloween', '#1b1026', '#ff7a18', '#7b2fbf', 'Pumpkin orange and midnight purple'],
    ];
}

/** The season-based skin for a date (Dec 1 - Jan 6 Christmas, Oct 18 - Nov 1 Halloween). */
function season_skin(?DateTimeInterface $d = null): string
{
    $d = $d ?: new DateTimeImmutable('now');
    $md = (int)$d->format('nd');   // month*100+day -> e.g. 1201
    $md = (int)$d->format('n') * 100 + (int)$d->format('j');
    if ($md >= 1201 || $md <= 106) {
        return 'christmas';
    }
    if ($md >= 1018 && $md <= 1101) {
        return 'halloween';
    }
    return 'levelup';
}

function active_skin(?array $u = null): string
{
    $list = skin_list();
    $s = (string)($u['skin'] ?? 'auto');
    if (!isset($list[$s]) || $s === 'auto') {
        $s = (string)setting('default_skin', 'auto');
    }
    if (!isset($list[$s]) || $s === 'auto') {
        return season_skin();
    }
    return $s;
}
