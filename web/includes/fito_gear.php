<?php
declare(strict_types=1);

/**
 * Fito's character: species, eye colour and the gear the member has equipped, drawn on top of the mascot.
 * Gear looks follow the item's strength: wood and rags -> leather -> steel -> gold -> mythic ice-blue.
 */

const FITO_SPECIES = ['wolf' => 'Wolf', 'bear' => 'Bear', 'fox' => 'Fox', 'panther' => 'Panther'];
const FITO_EYES = ['ice' => 'Ice', 'emerald' => 'Emerald', 'amber' => 'Amber', 'crimson' => 'Crimson', 'violet' => 'Violet', 'gold' => 'Gold'];
const FITO_SPECIES_COST = 250;   // the first pick is free, later changes cost this many coins

function fito_look(int $uid): array
{
    staff_ensure();
    $r = row('SELECT species, eye, picks FROM char_look WHERE user_id = ?', [$uid]) ?: ['species' => 'wolf', 'eye' => 'ice', 'picks' => 0];
    if (!isset(FITO_SPECIES[$r['species']])) { $r['species'] = 'wolf'; }
    if (!isset(FITO_EYES[$r['eye']])) { $r['eye'] = 'ice'; }
    $g = game_gear($uid);
    $cat = game_items();
    $gear = [];
    foreach ($g['by_slot'] as $slot => $key) {
        $i = $cat[$key];
        $tier = game_item_tier($key);
        $gear[$slot] = ['key' => $key, 'name' => $i[0], 'tier' => $tier, 'set' => (string)($i[8] ?? '')];
    }
    // look-only items cover the stat gear in the same slot
    foreach (array_keys($gear) as $slot) {
        if (strpos($slot, 'look_') === 0) {
            $gear[substr($slot, 5)] = $gear[$slot];
            unset($gear[$slot]);
        }
    }
    return ['species' => $r['species'], 'eye' => $r['eye'], 'picks' => (int)$r['picks'], 'gear' => $gear];
}

/** Draws Fito (idle pose) with a member's look. */
function fito_dressed(array $look, int $size = 220, string $class = ''): string
{
    $GLOBALS['fito_dress'] = $look;
    try {
        return fito('idle', $size, 'dressed ' . $class);
    } finally {
        $GLOBALS['fito_dress'] = null;
    }
}

/** Head-only preview of a species (for the picker). */
function fito_species_face(string $species, string $eye, int $size = 64): string
{
    $GLOBALS['fito_dress'] = ['species' => $species, 'eye' => $eye, 'gear' => []];
    try {
        return fito('idle', $size, '', true);
    } finally {
        $GLOBALS['fito_dress'] = null;
    }
}

function fito_ramp(int $t): array
{
    return [['#9b7b52', '#5f4a2e'], ['#b9825a', '#7a4b2a'], ['#d9deea', '#8590a3'], ['#f6dc7a', '#c4932a'], ['#a8ecff', '#2b7fe0']][max(0, min(4, $t))];
}

function fito_dressup(string $svg, string $pose, bool $face, array $d): string
{
    // ---- species and eye colour ----
    $sp = $d['species'] ?? 'wolf';
    $eye = $d['eye'] ?? 'ice';
    $eyes = ['ice' => ['#eaf8ff', '#62ccff', '#1b86e0'], 'emerald' => ['#eafff1', '#4be28a', '#16a05a'], 'amber' => ['#fff6e2', '#ffb54a', '#d9761a'],
             'crimson' => ['#ffecec', '#ff6b6b', '#c92a2a'], 'violet' => ['#f3ecff', '#b58cff', '#6c3ad6'], 'gold' => ['#fffbe0', '#ffe066', '#d9a800']];
    $fur = ['wolf' => ['#a9b1c1', '#727a8c', '#cdd3df', '#9aa2b3', '#f4f6fa', '#c3c9d6'],
            'bear' => ['#a8744a', '#6b4528', '#c79668', '#8a5c38', '#ead2b0', '#c9a67c'],
            'fox' => ['#f4964a', '#c4571a', '#ffb06b', '#d96a22', '#fff4e4', '#ecd6bc'],
            'panther' => ['#4d5468', '#1e2230', '#6a7288', '#3c4254', '#7a8298', '#4a5166']][$sp] ?? null;
    $e = $eyes[$eye] ?? $eyes['ice'];
    $id = $sp . '-' . $eye;
    $defs = '<defs>'
        . '<linearGradient id="ftFur-' . $id . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $fur[0] . '"/><stop offset="1" stop-color="' . $fur[1] . '"/></linearGradient>'
        . '<linearGradient id="ftFurL-' . $id . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $fur[2] . '"/><stop offset="1" stop-color="' . $fur[3] . '"/></linearGradient>'
        . '<linearGradient id="ftLight-' . $id . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $fur[4] . '"/><stop offset="1" stop-color="' . $fur[5] . '"/></linearGradient>'
        . '<radialGradient id="ftEye-' . $id . '"><stop offset="0" stop-color="' . $e[0] . '"/><stop offset=".4" stop-color="' . $e[1] . '"/><stop offset="1" stop-color="' . $e[2] . '"/></radialGradient>';
    for ($t = 0; $t < 5; $t++) {
        [$a, $b] = fito_ramp($t);
        $defs .= '<linearGradient id="ga' . $t . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $a . '"/><stop offset="1" stop-color="' . $b . '"/></linearGradient>';
    }
    $defs .= '</defs>';
    $svg = str_replace(['url(#ftFur)', 'url(#ftFurL)', 'url(#ftLight)', 'url(#ftEye)'], ['url(#ftFur-' . $id . ')', 'url(#ftFurL-' . $id . ')', 'url(#ftLight-' . $id . ')', 'url(#ftEye-' . $id . ')'], $svg);
    $svg = preg_replace('/(<svg[^>]*>)/', '$1' . $defs, $svg, 1);

    if ($sp === 'bear') {
        $svg = preg_replace('#<g class="f-earL">.*?</g>#s', '<g class="f-earL"><circle cx="74" cy="48" r="24" fill="url(#ftFur-' . $id . ')"/><circle cx="74" cy="48" r="12" fill="#3a2414"/></g>', $svg, 1);
        $svg = preg_replace('#<g class="f-earR">.*?</g>#s', '<g class="f-earR"><circle cx="166" cy="48" r="24" fill="url(#ftFur-' . $id . ')"/><circle cx="166" cy="48" r="12" fill="#3a2414"/></g>', $svg, 1);
        $svg = preg_replace('#<g class="f-tail">.*?</g>#s', '<g class="f-tail"><circle cx="186" cy="226" r="17" fill="url(#ftFur-' . $id . ')"/></g>', $svg, 1);
    }
    if ($face || $pose !== 'idle') {
        return $svg;
    }

    $gear = $d['gear'] ?? [];
    $behind = $boots = $armor = $hands = $helm = $charm = '';

    // aura and wings (behind the body)
    if (isset($gear['aura'])) {
        $t = $gear['aura']['tier'];
        $behind .= '<circle class="f-aura" cx="120" cy="170" r="128" fill="url(#ga' . $t . ')" opacity=".28"/>';
    }
    if (isset($gear['wings'])) {
        $t = $gear['wings']['tier'];
        $w = '<path d="M62 160Q-18 96 -4 30Q30 66 44 58Q40 98 78 128Z" fill="url(#ga' . $t . ')"/><path d="M60 170Q-6 140 -14 96Q24 118 40 112Q40 140 76 150Z" fill="url(#ga' . $t . ')" opacity=".85"/>';
        $behind .= $w . '<g transform="translate(240 0) scale(-1 1)">' . $w . '</g>';
    }
    // boots
    if (isset($gear['boots'])) {
        $a = 'url(#ga' . $gear['boots']['tier'] . ')';
        $boots = '<path d="M66 262Q94 250 118 262L120 285Q92 292 64 285Z" fill="' . $a . '"/><path d="M174 262Q146 250 122 262L120 285Q148 292 176 285Z" fill="' . $a . '"/>'
            . '<path d="M64 282Q92 289 120 283M176 282Q148 289 120 283" stroke="#1d2233" stroke-opacity=".6" stroke-width="3" fill="none"/>';
    }
    // armor
    $ar = $gear['armor']['tier'] ?? null;
    if ($ar !== null) {
        $a = 'url(#ga' . $ar . ')';
        $armor = '<path d="M62 150Q120 132 178 150L172 214Q120 232 68 214Z" fill="' . $a . '"/>';
        if ($ar === 0) {
            $armor .= '<path d="M70 176l10 6M150 190l12-4M100 204l10 4" stroke="#3b2c18" stroke-width="3" stroke-linecap="round"/><path d="M66 208Q120 224 174 208" stroke="#d8c7a0" stroke-width="4" fill="none" stroke-dasharray="6 5"/>';
        } elseif ($ar === 1) {
            $armor .= '<path d="M120 142V224" stroke="#3b2414" stroke-width="3" opacity=".6"/><path d="M80 172Q120 182 160 172M82 196Q120 206 158 196" stroke="#3b2414" stroke-opacity=".5" stroke-width="2.4" fill="none" stroke-dasharray="4 4"/>';
        } elseif ($ar === 2) {
            $armor .= '<path d="M70 164H170M72 180H168M74 196H166M76 212H164" stroke="#5b6475" stroke-opacity=".55" stroke-width="3" stroke-dasharray="3 4"/>';
        } else {
            $armor .= '<path d="M120 142V224M84 168Q120 180 156 168" stroke="#fff" stroke-opacity=".5" stroke-width="3" fill="none"/>'
                . '<circle cx="86" cy="190" r="3.2" fill="#fff" opacity=".7"/><circle cx="154" cy="190" r="3.2" fill="#fff" opacity=".7"/>'
                . '<ellipse cx="58" cy="156" rx="19" ry="13" fill="' . $a . '"/><ellipse cx="182" cy="156" rx="19" ry="13" fill="' . $a . '"/>';
            if ($ar === 4) {
                $armor .= '<path d="M44 150L52 130L62 148ZM196 150L188 130L178 148Z" fill="' . $a . '"/><circle cx="120" cy="176" r="8" fill="#62ccff" filter="url(#ftGlow)"/>';
            }
        }
    }
    // pendant
    if (isset($gear['charm']) || isset($gear['relic'])) {
        $t = ($gear['charm']['tier'] ?? $gear['relic']['tier']);
        $charm = '<path d="M96 144Q120 178 144 144" stroke="#d9deea" stroke-opacity=".7" stroke-width="2" fill="none"/><circle cx="120" cy="168" r="7" fill="url(#ga' . $t . ')" stroke="#fff" stroke-opacity=".5"/>';
    }
    // gloves and weapon (right fist at 182,228)
    if (isset($gear['gloves'])) {
        $a = 'url(#ga' . $gear['gloves']['tier'] . ')';
        $hands .= '<circle cx="58" cy="228" r="19" fill="' . $a . '"/><circle cx="182" cy="228" r="19" fill="' . $a . '"/>';
    }
    if (isset($gear['weapon'])) {
        $t = $gear['weapon']['tier'];
        $k = strtolower($gear['weapon']['name']);
        $a = 'url(#ga' . $t . ')';
        $wood = 'url(#ga0)';
        if ($t === 0) {
            $wp = '<path d="M-3 12L-4 -60Q-3 -84 4 -90L6 -84Q2 -60 3 12Z" fill="' . $wood . '"/><path d="M-2 -30L-12 -40" stroke="#5f4a2e" stroke-width="3" stroke-linecap="round"/>';
        } elseif (preg_match('/axe/', $k)) {
            $wp = '<rect x="-3" y="-84" width="6" height="96" rx="3" fill="' . $wood . '"/><path d="M3 -86Q34 -92 34 -54Q14 -60 3 -52Z" fill="' . $a . '"/>';
        } elseif (preg_match('/hammer|maul/', $k)) {
            $wp = '<rect x="-3" y="-80" width="6" height="92" rx="3" fill="' . $wood . '"/><rect x="-20" y="-100" width="40" height="28" rx="5" fill="' . $a . '"/>';
        } elseif (preg_match('/spear/', $k)) {
            $wp = '<rect x="-2.5" y="-110" width="5" height="124" rx="2" fill="' . $wood . '"/><path d="M-10 -104L0 -134L10 -104Z" fill="' . $a . '"/>';
        } else {
            $wp = '<path d="M-5 -16L-5 -88L0 -100L5 -88L5 -16Z" fill="' . $a . '"/><rect x="-15" y="-18" width="30" height="7" rx="3" fill="#1d2233"/><rect x="-3" y="-12" width="6" height="22" rx="3" fill="#1d2233"/>';
        }
        if ($t === 4) {
            $wp = '<g filter="url(#ftGlow)">' . $wp . '</g>';
        }
        $hands .= '<g transform="translate(182 226) rotate(14)">' . $wp . '</g>';
    }
    // helmet (drawn in head coordinates)
    if (isset($gear['helmet'])) {
        $t = $gear['helmet']['tier'];
        $a = 'url(#ga' . $t . ')';
        if ($t === 0) {
            $h = '<path d="M62 82Q70 56 120 52Q170 56 178 82Q150 70 120 70Q90 70 62 82Z" fill="' . $a . '"/><path d="M170 78L196 90L172 90Z" fill="' . $a . '"/>';
        } else {
            $h = '<path d="M62 86Q60 46 120 40Q180 46 178 86Q150 68 120 68Q90 68 62 86Z" fill="' . $a . '"/>';
            if ($t === 2 || $t === 3) { $h .= '<path d="M115 62V106H125V62Z" fill="' . $a . '"/>'; }
            if ($t === 3) { $h .= '<path d="M120 42Q154 8 196 22Q164 30 152 50Z" fill="#c94a4a"/>'; }
            if ($t === 4) { $h .= '<path d="M68 70L76 30L98 54L120 24L142 54L164 30L172 70Z" fill="' . $a . '"/><circle cx="120" cy="50" r="6" fill="#62ccff" filter="url(#ftGlow)"/>'; }
        }
        $helm = $h;
    }

    $svg = preg_replace('/<ellipse class="f-shadow"/', $behind . '<ellipse class="f-shadow"', $svg, 1);
    $svg = preg_replace('/<path d="M70 218L170 218/', $boots . '<path d="M70 218L170 218', $svg, 1);
    $svg = preg_replace('/<g class="f-armL">/', $armor . $charm . '<g class="f-armL">', $svg, 1);
    $svg = preg_replace('/<g class="f-head">/', $hands . '<g class="f-head">', $svg, 1);
    $svg = preg_replace('/<path d="M92 68L100 88"/', $helm . '<path d="M92 68L100 88"', $svg, 1);
    if (isset($gear['wings']) || isset($gear['aura'])) {
        $svg = preg_replace('/viewBox="0 0 240 300"/', 'viewBox="-40 -10 320 310"', $svg, 1);
    }
    return $svg;
}

/** Look tier of an item: sets say so themselves, other gear is judged by its strength. */
function game_item_tier(string $key): int
{
    $i = game_items()[$key] ?? null;
    if (!$i) {
        return 0;
    }
    if (isset($i[7])) {
        return (int)$i[7];
    }
    $s = $i[3] + $i[4];
    return $s <= 2 ? 0 : ($s <= 5 ? 1 : ($s <= 9 ? 2 : ($s <= 14 ? 3 : 4)));
}

/** Small drawn icon of a piece of gear, in the same colours Fito wears it in. */
function gear_icon(string $key, int $size = 56): string
{
    $i = game_items()[$key] ?? null;
    if (!$i) {
        return '';
    }
    $slot = preg_replace('/^look_/', '', (string)$i[2]);
    [$a, $b] = fito_ramp(game_item_tier($key));
    $f = 'fill="' . $a . '" stroke="' . $b . '" stroke-width="2.5" stroke-linejoin="round"';
    $dk = 'fill="#1d2233"';
    $n = strtolower($i[0]);
    switch ($slot) {
        case 'weapon':
            if (game_item_tier($key) === 0) {
                $g = '<path d="M28 58L30 14Q31 6 36 4L38 8Q35 16 35 58Z" ' . $f . '/>';
            } elseif (strpos($n, 'axe') !== false) {
                $g = '<rect x="29" y="8" width="6" height="52" rx="3" fill="#8a6a45"/><path d="M35 8Q58 4 58 28Q44 24 35 30Z" ' . $f . '/>';
            } elseif (preg_match('/maul|hammer/', $n)) {
                $g = '<rect x="29" y="14" width="6" height="46" rx="3" fill="#8a6a45"/><rect x="14" y="4" width="36" height="20" rx="4" ' . $f . '/>';
            } elseif (strpos($n, 'spear') !== false) {
                $g = '<rect x="30" y="14" width="4" height="48" rx="2" fill="#8a6a45"/><path d="M22 18L32 2L42 18Z" ' . $f . '/>';
            } else {
                $g = '<path d="M28 44V12L32 4L36 12V44Z" ' . $f . '/><rect x="21" y="43" width="22" height="6" rx="3" ' . $dk . '/><rect x="29" y="48" width="6" height="12" rx="3" ' . $dk . '/>';
            }
            break;
        case 'armor':
            $g = '<path d="M12 16Q32 8 52 16L48 54Q32 60 16 54Z" ' . $f . '/><path d="M32 14V56" stroke="' . $b . '" stroke-width="2" opacity=".6"/><ellipse cx="12" cy="18" rx="7" ry="5" ' . $f . '/><ellipse cx="52" cy="18" rx="7" ry="5" ' . $f . '/>';
            break;
        case 'helmet':
            $g = '<path d="M10 40Q8 10 32 8Q56 10 54 40Q44 30 32 30Q20 30 10 40Z" ' . $f . '/><path d="M29 28V48H35V28Z" ' . $f . '/>';
            break;
        case 'gloves':
            $g = '<rect x="16" y="14" width="32" height="16" rx="6" ' . $f . '/><rect x="14" y="30" width="36" height="22" rx="10" ' . $f . '/><path d="M24 32V44M32 32V46M40 32V44" stroke="' . $b . '" stroke-width="2" opacity=".6"/>';
            break;
        case 'boots':
            $g = '<path d="M16 10H36V34Q52 36 56 46V54H12V10Z" ' . $f . '/><path d="M12 50H56" stroke="#1d2233" stroke-width="4"/>';
            break;
        case 'wings':
            $g = '<path d="M30 52Q2 36 6 8Q22 22 30 20Z" ' . $f . '/><path d="M34 52Q62 36 58 8Q42 22 34 20Z" ' . $f . '/>';
            break;
        case 'aura':
            $g = '<circle cx="32" cy="32" r="24" ' . $f . ' opacity=".55"/><circle cx="32" cy="32" r="12" fill="' . $a . '" opacity=".85"/>';
            break;
        default: // charm and everything else
            $g = '<path d="M16 8Q32 40 48 8" stroke="#d9deea" stroke-width="2.5" fill="none"/><circle cx="32" cy="42" r="12" ' . $f . '/>';
    }
    return '<svg class="gearico" width="' . $size . '" height="' . $size . '" viewBox="0 0 64 64" aria-hidden="true">' . $g . '</svg>';
}

/** The look with some items tried on (fitting room). Look-only and stat items both just change how Fito appears. */
function fito_try_on(array $look, array $keys): array
{
    $cat = game_items();
    foreach ($keys as $k) {
        if (!isset($cat[$k])) {
            continue;
        }
        $i = $cat[$k];
        $slot = preg_replace('/^look_/', '', (string)$i[2]);
        if (in_array($slot, ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'charm', 'wings', 'aura'], true)) {
            $look['gear'][$slot] = ['key' => $k, 'name' => $i[0], 'tier' => game_item_tier($k), 'set' => (string)($i[8] ?? '')];
        }
    }
    return $look;
}
