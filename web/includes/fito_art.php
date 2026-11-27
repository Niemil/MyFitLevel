<?php
declare(strict_types=1);

/*
 * Drawn gear for Fito. Every design is a small SVG shape in the character's own coordinates (240 x 300):
 * head around y 30-170, torso x 62-178 / y 140-232, fists at (58,228) and (182,228), feet y 258-285.
 * A palette colours a design, so one design can be worn in many colours. Armor returns three layers:
 * 'k' behind the body (cape), 'b' on the torso, 'o' over the arms (shoulders).
 */

/** Palettes: m main, s shade (outline), l light, a accent, g glow. */
function fa_palettes(): array
{
    return [
        'wood'     => ['#a8845a', '#5f4a2e', '#d2b07c', '#d8c7a0', '#ffd88a'],
        'leather'  => ['#b9825a', '#6e3f1f', '#dcab82', '#4a2c16', '#ffcf8a'],
        'iron'     => ['#aab2c0', '#4f586a', '#e2e7f0', '#2a3040', '#cfe3ff'],
        'steel'    => ['#d9deea', '#7a869c', '#ffffff', '#2e4a7a', '#cfe8ff'],
        'gold'     => ['#f6dc7a', '#a8741a', '#fff3b8', '#a82828', '#fff0a0'],
        'mythril'  => ['#a8ecff', '#2b7fe0', '#eaffff', '#1b3b80', '#62ccff'],
        'storm'    => ['#6b5bd6', '#241a62', '#b9a8ff', '#ffe14a', '#8fd0ff'],
        'crimson'  => ['#c94a4a', '#6a1818', '#ff9a8a', '#f4d27a', '#ff8a6a'],
        'emerald'  => ['#3fb67a', '#14604a', '#9af0c4', '#e8d27a', '#7affb8'],
        'obsidian' => ['#4a4268', '#14101f', '#8a7cc0', '#b46cff', '#c58cff'],
        'sakura'   => ['#f2a6c4', '#a8487a', '#ffd8e8', '#ffffff', '#ffc0de'],
        'ocean'    => ['#3fb7c9', '#14677a', '#9aeaf5', '#f4e6b0', '#8afff0'],
        'bone'     => ['#e9e0c9', '#8f8466', '#fff8e6', '#7a2e2e', '#fff0c0'],
        'royal'    => ['#3f5fd9', '#1a2a80', '#9ab0ff', '#f6dc7a', '#b0c4ff'],
        'sun'      => ['#ffb54a', '#b8501a', '#ffe0a0', '#fff6e0', '#ffe08a'],
        'shadow'   => ['#323850', '#0e101a', '#6a7498', '#d24a4a', '#ff7a7a'],
        'forest'   => ['#5f8f4a', '#2a4a1e', '#a8d48a', '#8a5a2a', '#d4ff9a'],
        'frost'    => ['#cfe6ff', '#5a7aa8', '#ffffff', '#3a5a9a', '#bfe8ff'],
        'lava'     => ['#5a2a2a', '#1e0a0a', '#a85a3a', '#ff7a1a', '#ffb04a'],
    ];
}

/** Gradient + helper defs for a palette code (ids are fixed per code, so repeating them is harmless). */
function fa_defs(string $code): string
{
    [$m, $s] = fa_palettes()[$code] ?? fa_palettes()['iron'];
    return '<defs><linearGradient id="faM-' . $code . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $m . '"/><stop offset="1" stop-color="' . $s . '"/></linearGradient>'
        . '<pattern id="faC-' . $code . '" width="8" height="7" patternUnits="userSpaceOnUse"><path d="M0 3.5Q2 0.5 4 3.5Q6 6.5 8 3.5" stroke="' . $s . '" stroke-width="1.1" fill="none" opacity=".75"/></pattern>'
        . '<pattern id="faS-' . $code . '" width="12" height="9" patternUnits="userSpaceOnUse"><path d="M0 9Q6 -1 12 9" stroke="' . $s . '" stroke-width="1.2" fill="none" opacity=".8"/></pattern></defs>';
}

/** Colour set handed to every design. */
function fa_colors(string $code): array
{
    [$m, $s, $l, $a, $g] = fa_palettes()[$code] ?? fa_palettes()['iron'];
    return ['M' => 'url(#faM-' . $code . ')', 'm' => $m, 's' => $s, 'l' => $l, 'a' => $a, 'g' => $g, 'chain' => 'url(#faC-' . $code . ')', 'scale' => 'url(#faS-' . $code . ')'];
}

/** The same shape on the left and the right side of the body. */
function fa_pair(string $left): string
{
    return $left . '<g transform="translate(240 0) scale(-1 1)">' . $left . '</g>';
}

/* ---------------------------------- ARMOR ---------------------------------- */

function fa_armor(string $d, array $c): array
{
    $M = $c['M']; $m = $c['m']; $s = $c['s']; $l = $c['l']; $a = $c['a']; $g = $c['g'];
    $st = "stroke='$s' stroke-width='2' stroke-linejoin='round'";
    $T = 'M62 150Q120 132 178 150L172 214Q120 232 68 214Z';
    $r = ['k' => '', 'b' => '', 'o' => ''];
    switch ($d) {
        case 'rags':
            $r['b'] = "<path d='M64 152Q120 136 176 152L172 210L162 203L154 216L144 205L134 218L124 206L114 219L104 206L94 218L84 205L74 216L68 207Z' fill='$M' $st/>"
                . "<rect x='78' y='166' width='20' height='17' rx='2' fill='$l' stroke='$s' stroke-width='1.6' stroke-dasharray='3 2'/><rect x='142' y='184' width='18' height='15' rx='2' fill='$a' stroke='$s' stroke-width='1.6' stroke-dasharray='3 2'/>"
                . "<path d='M66 196Q120 208 174 196' stroke='$l' stroke-width='4' fill='none' stroke-dasharray='7 4'/>";
            break;
        case 'jerkin':
            $r['b'] = "<path d='M64 152Q92 144 106 150L120 200L134 150Q148 144 176 152L171 214Q120 230 69 214Z' fill='$M' $st/>"
                . "<path d='M108 158L130 162M111 170L128 174M114 182L126 186' stroke='$a' stroke-width='2.4' stroke-linecap='round'/>"
                . "<path d='M66 152L86 147V163L68 166ZM174 152L154 147V163L172 166Z' fill='$l' stroke='$s' stroke-width='1.6'/>"
                . "<path d='M68 203Q120 217 172 203V215Q120 229 68 215Z' fill='$a' $st/><rect x='111' y='206' width='18' height='13' rx='2' fill='#e8c35a' $st/>";
            break;
        case 'chain':
            $r['b'] = "<path d='$T' fill='$M' $st/><path d='$T' fill='{$c['chain']}'/>"
                . "<path d='M70 202Q120 216 170 202V214Q120 228 70 214Z' fill='$a' $st/><rect x='110' y='204' width='20' height='14' rx='3' fill='$l' $st/>"
                . "<path d='M92 148Q120 164 148 148' stroke='$s' stroke-width='5' fill='none'/><path d='M94 149Q120 163 146 149' stroke='$l' stroke-width='2' fill='none' stroke-dasharray='2 5' stroke-linecap='round'/>";
            $r['o'] = "<ellipse cx='56' cy='158' rx='19' ry='14' fill='$M' $st/><ellipse cx='56' cy='158' rx='19' ry='14' fill='{$c['chain']}'/><ellipse cx='184' cy='158' rx='19' ry='14' fill='$M' $st/><ellipse cx='184' cy='158' rx='19' ry='14' fill='{$c['chain']}'/>";
            break;
        case 'coat':
            $r['b'] = "<path d='M62 150Q120 134 178 150L177 226Q120 240 63 226Z' fill='$M' $st/>"
                . "<path d='M100 143L121 196L106 202L86 154Z M140 143L119 196L134 202L154 154Z' fill='$l' $st/>"
                . "<path d='M64 168L176 214V226L64 182Z' fill='$a' $st opacity='.95'/>"
                . "<circle cx='110' cy='204' r='3.4' fill='#f6dc7a'/><circle cx='130' cy='204' r='3.4' fill='#f6dc7a'/><circle cx='110' cy='216' r='3.4' fill='#f6dc7a'/><circle cx='130' cy='216' r='3.4' fill='#f6dc7a'/>"
                . "<path d='M64 224Q120 238 176 224' stroke='#f6dc7a' stroke-width='3' fill='none'/>";
            break;
        case 'plate':
            $r['b'] = "<path d='$T' fill='$M' $st/><path d='M120 142V222' stroke='$s' stroke-width='2.4'/><path d='M72 172Q120 186 168 172M70 190Q120 204 170 190' stroke='$l' stroke-width='2.2' fill='none' opacity='.75'/>"
                . "<path d='M68 200Q120 214 172 200V208Q120 222 68 208Z M70 210Q120 224 170 210V216Q120 230 70 216Z' fill='$m' $st/>"
                . "<path d='M103 214H137L142 262Q120 252 98 262Z' fill='$a' $st/><path d='M120 226l4 8h9l-7 5 3 9-9-6-9 6 3-9-7-5h9Z' fill='#f6dc7a'/>"
                . "<path d='M90 146Q120 162 150 146L148 158Q120 174 92 158Z' fill='$l' $st/>";
            $r['o'] = fa_pair("<ellipse cx='54' cy='156' rx='23' ry='15' fill='$M' $st/><path d='M36 154Q54 142 72 154' stroke='$l' stroke-width='2.4' fill='none'/><circle cx='42' cy='162' r='2.4' fill='$l'/><circle cx='66' cy='162' r='2.4' fill='$l'/>");
            break;
        case 'elven':
            $r['k'] = "<path d='M70 150Q44 220 62 292L120 274L178 292Q196 220 170 150Z' fill='$a' opacity='.92' stroke='$s' stroke-width='1.6'/><path d='M120 160V280' stroke='$s' stroke-width='1.4' opacity='.5'/>";
            $r['b'] = "<path d='M64 150Q120 130 176 150L168 212Q120 236 72 212Z' fill='$M' $st/>"
                . "<path d='M120 146V228M88 158Q104 192 120 228M152 158Q136 192 120 228M76 176Q98 200 120 228M164 176Q142 200 120 228' stroke='$l' stroke-width='2' fill='none' opacity='.8'/>"
                . "<path d='M120 164l9 12-9 14-9-14Z' fill='$g' stroke='$l' stroke-width='1.6' filter='url(#ftGlow)'/>";
            $r['o'] = fa_pair("<path d='M32 156Q42 126 80 142Q74 170 42 172Z' fill='$M' $st/><path d='M44 150Q60 142 74 148M46 160Q62 154 72 158' stroke='$l' stroke-width='2' fill='none'/>");
            break;
        case 'storm':
            $r['b'] = "<path d='$T' fill='$M' $st/><path d='M70 168H170M72 186H168' stroke='$s' stroke-width='2.4' opacity='.6'/>"
                . "<path d='M128 154L106 190L121 190L110 220L138 180L123 180Z' fill='{$c['a']}' stroke='$s' stroke-width='1.6' filter='url(#ftGlow)'/>"
                . "<path d='M70 202Q120 216 170 202V214Q120 228 70 214Z' fill='$s' $st/>"
                . "<circle cx='80' cy='160' r='2.6' fill='$l'/><circle cx='160' cy='160' r='2.6' fill='$l'/><circle cx='84' cy='196' r='2.6' fill='$l'/><circle cx='156' cy='196' r='2.6' fill='$l'/>";
            $r['o'] = fa_pair("<ellipse cx='54' cy='158' rx='23' ry='15' fill='$M' $st/><path d='M34 152L40 124L54 146ZM52 144L62 116L72 142ZM66 148L82 124L84 150Z' fill='$l' $st/>");
            break;
        case 'robe':
            $r['b'] = "<path d='M64 150Q120 134 176 150L186 246Q120 260 54 246Z' fill='$M' $st/>"
                . "<path d='M98 146L120 214L142 146' stroke='{$c['a']}' stroke-width='6' fill='none' stroke-linejoin='round'/><path d='M60 242Q120 256 180 242' stroke='{$c['a']}' stroke-width='5' fill='none'/>"
                . "<path d='M70 204Q120 218 170 204' stroke='$l' stroke-width='5' fill='none'/>"
                . "<path d='M84 176l3 6 6 1-5 4 1 6-5-3-5 3 1-6-5-4 6-1ZM156 230l3 6 6 1-5 4 1 6-5-3-5 3 1-6-5-4 6-1Z' fill='{$c['a']}'/>";
            break;
        case 'gi':
            $r['b'] = "<path d='M64 152Q120 136 176 152L172 214Q120 228 68 214Z' fill='$M' $st/>"
                . "<path d='M88 148L136 206L124 212L84 156Z M152 148L104 206L116 212L156 156Z' fill='$m' $st/>"
                . "<path d='M66 196Q120 208 174 196V212Q120 224 66 212Z' fill='$a' $st/><path d='M112 202l-14 22M128 202l14 22' stroke='$a' stroke-width='5' stroke-linecap='round'/>"
                . "<path d='M70 224Q120 236 170 224' stroke='{$c['chain']}' stroke-width='0'/>";
            break;
        case 'scale':
            $r['b'] = "<path d='$T' fill='$M' $st/><path d='$T' fill='{$c['scale']}'/><path d='M120 142V222' stroke='$s' stroke-width='2' opacity='.7'/>"
                . "<path d='M70 200Q120 214 170 200V214Q120 228 70 214Z' fill='$a' $st/>";
            $r['o'] = fa_pair("<ellipse cx='54' cy='158' rx='22' ry='15' fill='$M' $st/><ellipse cx='54' cy='158' rx='22' ry='15' fill='{$c['scale']}'/><path d='M38 150L30 122L52 144Z' fill='$l' $st/>");
            break;
        case 'samurai':
            $y = [150, 166, 182, 198];
            foreach ($y as $i => $yy) {
                $f = $i % 2 ? $m : $M;
                $r['b'] .= "<path d='M64 " . ($yy + 2) . "Q120 " . ($yy - 14) . " 176 " . ($yy + 2) . "L174 " . ($yy + 18) . "Q120 " . ($yy + 2) . " 66 " . ($yy + 18) . "Z' fill='$f' $st/>";
            }
            $r['b'] .= "<path d='M92 158V214M120 150V220M148 158V214' stroke='{$c['a']}' stroke-width='2.4' stroke-dasharray='4 3'/><path d='M66 214Q120 232 174 214V222Q120 238 66 222Z' fill='$a' $st/>";
            $r['o'] = fa_pair("<path d='M26 146L72 142L70 190L24 186Z' fill='$M' $st/><path d='M26 160L71 156M25 174L70 170' stroke='$l' stroke-width='2.4' opacity='.8'/><path d='M34 146V188M50 144V189' stroke='{$c['a']}' stroke-width='2' stroke-dasharray='3 3'/>");
            break;
        case 'viking':
            $r['b'] = "<path d='M64 152Q120 136 176 152L172 214Q120 230 68 214Z' fill='$M' $st/><path d='M78 150L162 214M162 150L78 214' stroke='{$c['a']}' stroke-width='6' opacity='.9'/>"
                . "<path d='M68 202Q120 216 172 202V214Q120 228 68 214Z' fill='{$c['a']}' $st/><circle cx='120' cy='208' r='6' fill='$l' $st/>";
            $r['o'] = "<path d='M34 160Q36 138 62 142Q92 132 120 142Q148 132 178 142Q204 138 206 160Q192 172 176 160Q150 172 120 164Q90 172 64 160Q50 172 34 160Z' fill='$l' stroke='$s' stroke-width='2'/>"
                . "<path d='M52 152l6 8M76 150l5 9M100 154l4 9M140 154l-4 9M164 150l-5 9M188 152l-6 8' stroke='$s' stroke-width='1.6' stroke-linecap='round'/>";
            break;
        case 'royal':
            $r['b'] = "<path d='M62 150Q120 132 178 150L184 240Q120 254 56 240Z' fill='$M' $st/><path d='M96 146L120 230L144 146' stroke='$l' stroke-width='9' fill='none' stroke-linejoin='round'/>"
                . "<path d='M60 236Q120 250 180 236' stroke='$l' stroke-width='7' fill='none'/><circle cx='112' cy='176' r='1.8' fill='$s'/><circle cx='128' cy='196' r='1.8' fill='$s'/><circle cx='64' cy='238' r='1.8' fill='$s'/><circle cx='176' cy='238' r='1.8' fill='$s'/>"
                . "<circle cx='120' cy='170' r='7' fill='{$c['a']}' stroke='$s' stroke-width='1.6'/>";
            $r['o'] = "<path d='M36 158Q38 142 62 144L90 146L88 158Q60 168 36 158Z M204 158Q202 142 178 144L150 146L152 158Q180 168 204 158Z' fill='$l' stroke='$s' stroke-width='1.6'/>";
            break;
        case 'tabard':
            $r['b'] = "<path d='$T' fill='$M' $st/><path d='$T' fill='{$c['chain']}'/><path d='M82 148H158L164 258Q120 268 76 258Z' fill='$a' $st/>"
                . "<path d='M120 164V222M102 186H138' stroke='$l' stroke-width='7'/><path d='M68 204Q120 218 172 204V212Q120 226 68 212Z' fill='$s' $st/>";
            $r['o'] = fa_pair("<ellipse cx='56' cy='158' rx='19' ry='14' fill='$M' $st/><ellipse cx='56' cy='158' rx='19' ry='14' fill='{$c['chain']}'/>");
            break;
        default:
            $r['b'] = "<path d='$T' fill='$M' $st/>";
    }
    return $r;
}

/* --------------------------------- WEAPONS --------------------------------- */
/* Local coordinates: the fist is at 0,0 and the weapon points up (negative y). */

function fa_weapon(string $d, array $c): string
{
    $M = $c['M']; $s = $c['s']; $l = $c['l']; $a = $c['a']; $g = $c['g']; $m = $c['m'];
    $st = "stroke='$s' stroke-width='2' stroke-linejoin='round'";
    $hilt = "<rect x='-3.5' y='-10' width='7' height='24' rx='3' fill='$a' $st/>";
    switch ($d) {
        case 'stick':
            return "<path d='M-3 14L-5 -62Q-4 -88 3 -94L7 -88Q3 -62 4 14Z' fill='$M' $st/><path d='M-3 -30L-14 -42M2 -50L12 -60' stroke='$s' stroke-width='3' stroke-linecap='round'/>";
        case 'shortsword':
            return "<path d='M-5 -14L-5 -72L0 -84L5 -72L5 -14Z' fill='$M' $st/><path d='M0 -78V-16' stroke='$l' stroke-width='1.6'/><rect x='-14' y='-17' width='28' height='7' rx='3' fill='$a' $st/>$hilt";
        case 'cutlass':
            return "<path d='M-5 -14Q-9 -50 6 -86Q16 -60 6 -14Z' fill='$M' $st/><path d='M-1 -22Q-3 -50 6 -76' stroke='$l' stroke-width='1.6' fill='none'/><path d='M-14 -14Q0 -22 12 -14Q0 -8 -14 -14Z' fill='$a' $st/>$hilt";
        case 'longsword':
        case 'glowsword':
            $o = "<path d='M-6 -16L-6 -92L0 -108L6 -92L6 -16Z' fill='$M' $st/><path d='M0 -100V-18' stroke='$l' stroke-width='2'/><path d='M-20 -19Q0 -28 20 -19L16 -12Q0 -19 -16 -12Z' fill='$a' $st/>$hilt<circle cx='0' cy='16' r='4.5' fill='$l' $st/>";
            return $d === 'glowsword' ? "<g filter='url(#ftGlow)'>$o</g><path d='M-2 -80l2-8 2 8-2 8Z M-2 -52l2-8 2 8-2 8Z' fill='$g'/>" : $o;
        case 'greatsword':
            return "<path d='M-9 -18L-9 -96L0 -118L9 -96L9 -18Z' fill='$M' $st/><path d='M0 -108V-20M-5 -92V-24M5 -92V-24' stroke='$l' stroke-width='1.6' opacity='.8'/><path d='M-26 -20Q0 -32 26 -20L22 -11Q0 -20 -22 -11Z' fill='$a' $st/><rect x='-4.5' y='-12' width='9' height='30' rx='3' fill='$s'/><circle cx='0' cy='20' r='6' fill='$a' $st/>";
        case 'katana':
            return "<path d='M-4 -14Q-6 -60 5 -112Q8 -60 4 -14Z' fill='$M' $st/><path d='M0 -18Q-1 -60 5 -100' stroke='$l' stroke-width='1.4' fill='none'/><ellipse cx='0' cy='-14' rx='11' ry='4' fill='$a' $st/><rect x='-3.5' y='-10' width='7' height='26' rx='3' fill='$s'/><path d='M-3 -6L3 0M-3 2L3 8M-3 10L3 16' stroke='$l' stroke-width='1.4'/>";
        case 'axe':
            return "<rect x='-3.5' y='-88' width='7' height='104' rx='3' fill='#a8845a' $st/><path d='M3 -90Q44 -100 42 -50Q22 -58 3 -46Z' fill='$M' $st/><path d='M12 -84Q34 -86 34 -62' stroke='$l' stroke-width='2' fill='none'/><path d='M-3 -92L0 -104L3 -92Z' fill='$M' $st/>";
        case 'waraxe':
            $h = "<path d='M-3 -86Q-44 -98 -42 -48Q-22 -56 -3 -44Z' fill='$M' $st/>";
            return "<rect x='-3.5' y='-90' width='7' height='106' rx='3' fill='#a8845a' $st/><path d='M3 -86Q44 -98 42 -48Q22 -56 3 -44Z' fill='$M' $st/>$h<path d='M-3 -96L0 -112L3 -96Z' fill='$l' $st/>";
        case 'maul':
            return "<rect x='-3.5' y='-84' width='7' height='100' rx='3' fill='#a8845a' $st/><rect x='-24' y='-108' width='48' height='32' rx='6' fill='$M' $st/><path d='M-24 -98H24M-24 -86H24' stroke='$l' stroke-width='1.6' opacity='.6'/><path d='M4 -106L-6 -92L2 -92L-4 -78L8 -94L0 -94Z' fill='$a' filter='url(#ftGlow)'/><path d='M-24 -92H-30M24 -92H30' stroke='$s' stroke-width='4' stroke-linecap='round'/>";
        case 'mace':
            return "<rect x='-3.5' y='-70' width='7' height='86' rx='3' fill='#a8845a' $st/><circle cx='0' cy='-84' r='15' fill='$M' $st/><path d='M0 -102V-66M-18 -84H18M-13 -97L13 -71M13 -97L-13 -71' stroke='$s' stroke-width='3' stroke-linecap='round'/><circle cx='-6' cy='-90' r='3' fill='$l'/>";
        case 'spear':
            return "<rect x='-2.5' y='-112' width='5' height='130' rx='2' fill='#a8845a' $st/><path d='M-10 -104L0 -140L10 -104L0 -110Z' fill='$M' $st/><path d='M-3 -96h6M-3 -88h6' stroke='$a' stroke-width='3'/>";
        case 'trident':
            return "<rect x='-2.5' y='-100' width='5' height='118' rx='2' fill='$s'/><path d='M-16 -126V-104Q0 -92 16 -104V-126M0 -134V-100' stroke='$m' stroke-width='5' fill='none' stroke-linecap='round' stroke-linejoin='round'/><path d='M-16 -126l-3-6M0 -134l0-6M16 -126l3-6' stroke='$l' stroke-width='3' stroke-linecap='round'/>";
        case 'staff':
            return "<path d='M-3 16L-4 -90Q-3 -96 3 -96L4 16Z' fill='#a8845a' $st/><circle cx='0' cy='-104' r='13' fill='$g' $st filter='url(#ftGlow)'/><path d='M-14 -92Q0 -78 14 -92' stroke='$s' stroke-width='4' fill='none'/><circle cx='-4' cy='-108' r='3.5' fill='#fff' opacity='.8'/>";
        case 'wand':
            return "<path d='M-2.5 12L-3 -54L3 -54L2.5 12Z' fill='#6e3f1f' $st/><path d='M0 -78l5 15 15 1-12 9 4 15-12-9-12 9 4-15-12-9 15-1Z' transform='translate(0 8) scale(.9)' fill='$g' $st filter='url(#ftGlow)'/>";
        case 'scythe':
            return "<rect x='-3' y='-110' width='6' height='128' rx='3' fill='#5f4a2e' $st/><path d='M0 -112Q50 -128 62 -76Q38 -100 4 -96Z' fill='$M' $st/><path d='M8 -108Q40 -112 52 -86' stroke='$l' stroke-width='1.8' fill='none'/>";
        case 'dagger':
            return "<path d='M-4 -12L-4 -48L0 -58L4 -48L4 -12Z' fill='$M' $st/><rect x='-11' y='-14' width='22' height='6' rx='3' fill='$a' $st/><rect x='-3' y='-9' width='6' height='20' rx='3' fill='$s'/>";
        case 'bat':
            return "<path d='M-4 16L-4 -50Q-9 -64 -6 -92Q0 -98 6 -92Q9 -64 4 -50L4 16Z' fill='$M' $st/><path d='M-3 -80H3M-3 -66H3' stroke='$a' stroke-width='3'/>";
        default:
            return fa_weapon('shortsword', $c);
    }
}

/* --------------------------------- HELMETS --------------------------------- */
/* Head coordinates: skull top y 65-80, ears around x 75 and 165, brows y 95. Hats may reach above the head. */

function fa_helmet(string $d, array $c): string
{
    $M = $c['M']; $s = $c['s']; $l = $c['l']; $a = $c['a']; $g = $c['g']; $m = $c['m'];
    $st = "stroke='$s' stroke-width='2' stroke-linejoin='round'";
    switch ($d) {
        case 'cap':
            return "<path d='M62 84Q66 52 120 48Q174 52 178 84Q150 72 120 72Q90 72 62 84Z' fill='$M' $st/><path d='M168 80L202 92L170 92Z' fill='$M' $st/><path d='M92 56Q120 50 148 56' stroke='$l' stroke-width='2' fill='none'/>";
        case 'band':
            return "<path d='M62 88Q120 66 178 88L178 78Q120 56 62 78Z' fill='$a' $st/><path d='M176 82L200 98M176 86L196 106' stroke='$a' stroke-width='5' stroke-linecap='round'/>";
        case 'hood':
            return "<path d='M54 112Q40 36 120 30Q200 36 186 112Q176 82 150 76Q120 70 90 76Q64 82 54 112Z' fill='$M' $st/><path d='M66 100Q72 78 92 72M174 100Q168 78 148 72' stroke='$l' stroke-width='2' fill='none' opacity='.7'/><path d='M104 36Q120 22 136 36' stroke='$s' stroke-width='2' fill='none'/>";
        case 'barbute':
            return "<path d='M60 92Q56 44 120 38Q184 44 180 92L172 150Q164 128 150 118L144 100H96L90 118Q76 128 68 150Z' fill='$M' $st/><path d='M115 70V122H125V70Z' fill='$M' $st/><path d='M66 90Q120 74 174 90' stroke='$l' stroke-width='2.4' fill='none' opacity='.7'/>";
        case 'tricorn':
            return "<path d='M34 84Q70 62 120 60Q170 62 206 84Q170 76 120 76Q70 76 34 84Z' fill='$M' $st/><path d='M62 76Q70 40 120 36Q170 40 178 76Q150 68 120 68Q90 68 62 76Z' fill='$M' $st/><circle cx='120' cy='60' r='5' fill='$a'/><path d='M62 76Q120 66 178 76' stroke='$a' stroke-width='4' fill='none'/>";
        case 'crest':
            return "<path d='M60 94Q56 44 120 38Q184 44 180 94Q150 72 120 72Q90 72 60 94Z' fill='$M' $st/><path d='M115 70V112H125V70Z' fill='$M' $st/><path d='M118 38Q132 -6 196 14Q168 24 160 46Q142 56 118 54Z' fill='$a' $st/><path d='M66 90Q120 70 174 90' stroke='$l' stroke-width='2.4' fill='none' opacity='.8'/>";
        case 'winged':
            $w = "<path d='M62 78Q30 76 6 50Q30 48 40 36Q36 62 66 66Z' fill='$l' $st/><path d='M60 88Q26 92 4 76Q28 70 40 64Q40 80 62 80Z' fill='$M' $st/>";
            return $w . "<g transform='translate(240 0) scale(-1 1)'>$w</g><path d='M60 94Q56 44 120 38Q184 44 180 94Q150 72 120 72Q90 72 60 94Z' fill='$M' $st/><path d='M114 70V110H126V70Z' fill='$M' $st/><circle cx='120' cy='52' r='6' fill='$g' stroke='$s' stroke-width='1.6' filter='url(#ftGlow)'/>";
        case 'horned':
            $h = "<path d='M66 76Q34 70 24 30Q54 44 74 60Z' fill='#f4ecd6' $st/>";
            return $h . "<g transform='translate(240 0) scale(-1 1)'>$h</g><path d='M60 94Q56 46 120 40Q184 46 180 94Q150 74 120 74Q90 74 60 94Z' fill='$M' $st/><path d='M62 86H178' stroke='$a' stroke-width='5'/><path d='M115 72V104H125V72Z' fill='$M' $st/><circle cx='84' cy='86' r='2.6' fill='$l'/><circle cx='156' cy='86' r='2.6' fill='$l'/>";
        case 'kabuto':
            return "<path d='M58 96Q54 44 120 40Q186 44 182 96Q150 74 120 74Q90 74 58 96Z' fill='$M' $st/><path d='M50 98Q54 118 66 126L72 98ZM190 98Q186 118 174 126L168 98Z' fill='$M' $st/><path d='M120 40Q96 4 60 -2Q80 22 100 40ZM120 40Q144 4 180 -2Q160 22 140 40Z' fill='$a' $st/><path d='M62 90H178' stroke='$l' stroke-width='3' opacity='.7'/>";
        case 'crown':
            return "<path d='M70 86L68 52L94 70L120 40L146 70L172 52L170 86Q120 72 70 86Z' fill='$M' $st/><circle cx='120' cy='60' r='5' fill='$a' $st/><circle cx='88' cy='72' r='3.4' fill='$a'/><circle cx='152' cy='72' r='3.4' fill='$a'/>";
        case 'circlet':
            return "<path d='M66 88Q120 68 174 88' stroke='$M' stroke-width='6' fill='none' $st/><path d='M120 70l7 10-7 10-7-10Z' fill='$g' $st filter='url(#ftGlow)'/>";
        case 'wizard':
            return "<path d='M40 90Q120 64 200 90Q170 74 148 70L132 -8Q124 -26 108 -8L96 38L92 70Q70 74 40 90Z' fill='$M' $st/><path d='M92 76Q120 68 148 76L146 66Q120 60 94 66Z' fill='$a' $st/><path d='M118 22l3 7 7 1-5 5 2 7-7-4-7 4 2-7-5-5 7-1Z' fill='$a'/>";
        case 'bandana':
            return "<path d='M62 90Q120 62 178 90L176 74Q120 46 64 74Z' fill='$M' $st/><path d='M176 80L206 96L190 100L204 120L172 98Z' fill='$M' $st/><circle cx='100' cy='72' r='2.4' fill='$l'/><circle cx='122' cy='66' r='2.4' fill='$l'/><circle cx='144' cy='72' r='2.4' fill='$l'/>";
        case 'mask':
            return "<path d='M60 92Q56 46 120 40Q184 46 180 92Q150 74 120 74Q90 74 60 92Z' fill='$M' $st/><path d='M68 100Q90 92 108 106Q90 114 70 110ZM172 100Q150 92 132 106Q150 114 170 110Z' fill='$s' opacity='.0'/><path d='M62 108Q120 96 178 108L172 118Q120 108 68 118Z' fill='$M' $st/><path d='M120 40L120 24L130 40Z' fill='$a' $st/>";
        case 'feather':
            return "<path d='M62 86Q66 54 120 50Q174 54 178 86Q150 74 120 74Q90 74 62 86Z' fill='$M' $st/><path d='M150 58Q158 18 196 -4Q196 30 170 62Z' fill='$a' $st/><path d='M154 56Q168 30 190 6' stroke='$l' stroke-width='1.6' fill='none'/>";
        case 'leaf':
            return "<path d='M62 88Q70 56 120 52Q170 56 178 88Q150 76 120 76Q90 76 62 88Z' fill='$M' $st/><path d='M96 56Q86 30 62 22Q74 48 90 60ZM144 56Q154 30 178 22Q166 48 150 60Z' fill='$l' $st/><path d='M116 54Q120 26 124 54Z' fill='$a' $st/>";
        default:
            return fa_helmet('cap', $c);
    }
}

/* ---------------------------------- GLOVES --------------------------------- */
/* Drawn on the fist at (58,228) mirrored to (182,228). r 19. */

function fa_gloves(string $d, array $c): string
{
    $M = $c['M']; $s = $c['s']; $l = $c['l']; $a = $c['a']; $m = $c['m'];
    $st = "stroke='$s' stroke-width='2' stroke-linejoin='round'";
    $one = '';
    switch ($d) {
        case 'wraps':
            $one = "<circle cx='58' cy='228' r='19' fill='$M' $st/><path d='M40 222L76 216M40 230L76 224M41 238L75 232' stroke='$l' stroke-width='3' stroke-linecap='round'/><rect x='42' y='200' width='32' height='10' rx='3' fill='$M' $st/>";
            break;
        case 'glove':
            $one = "<circle cx='58' cy='228' r='19' fill='$M' $st/><path d='M48 232V240M58 232V242M68 232V240' stroke='$s' stroke-width='2' stroke-linecap='round'/><rect x='40' y='198' width='36' height='12' rx='4' fill='$a' $st/>";
            break;
        case 'gauntlet':
            $one = "<circle cx='58' cy='228' r='19' fill='$M' $st/><path d='M42 226H74M44 234H72' stroke='$l' stroke-width='2' opacity='.8'/><path d='M38 196H78L74 214H42Z' fill='$M' $st/><path d='M42 205H74' stroke='$l' stroke-width='2'/><circle cx='47' cy='228' r='2' fill='$l'/><circle cx='69' cy='228' r='2' fill='$l'/>";
            break;
        case 'cuffs':
            $one = "<circle cx='58' cy='228' r='19' fill='$a' $st/><path d='M36 198H80L76 212H40Z' fill='#f4ecd6' $st/><path d='M42 202v8M48 202v8M54 202v8M60 202v8M66 202v8M72 202v8' stroke='$s' stroke-width='1.2'/>";
            break;
        case 'plated':
            $one = "<circle cx='58' cy='228' r='19' fill='$M' $st/><path d='M40 220Q58 212 76 220M40 230Q58 222 76 230' stroke='$l' stroke-width='2.4' fill='none'/><path d='M34 192H82L78 214H38Z' fill='$M' $st/><path d='M36 198H80M37 206H79' stroke='$l' stroke-width='2' opacity='.8'/>";
            break;
        case 'spiked':
            $one = "<circle cx='58' cy='228' r='19' fill='$M' $st/><path d='M44 214L40 200L50 210ZM58 210L58 196L64 208ZM72 214L78 202L74 216Z' fill='$l' $st/><path d='M34 196H82L78 214H38Z' fill='$M' $st/><circle cx='46' cy='205' r='3' fill='$a'/><circle cx='70' cy='205' r='3' fill='$a'/>";
            break;
        case 'claws':
            $one = "<circle cx='58' cy='228' r='19' fill='$M' $st/><path d='M44 242L38 262M54 246L50 268M64 246L66 268M73 240L80 260' stroke='$l' stroke-width='4' stroke-linecap='round'/><rect x='40' y='198' width='36' height='12' rx='4' fill='$M' $st/>";
            break;
        case 'bracer':
            $one = "<circle cx='58' cy='228' r='19' fill='$m' stroke='$s' stroke-width='2'/><rect x='38' y='196' width='40' height='18' rx='4' fill='$M' $st/><path d='M44 200V210M58 200V210M72 200V210' stroke='$l' stroke-width='2'/>";
            break;
        default:
            return fa_gloves('glove', $c);
    }
    return $one . '<g transform="translate(240 0) scale(-1 1)">' . $one . '</g>';
}

/* ----------------------------------- BOOTS --------------------------------- */
/* Left foot, drawn once and mirrored. Shoes span y 258-285. */

function fa_boots(string $d, array $c): string
{
    $M = $c['M']; $s = $c['s']; $l = $c['l']; $a = $c['a']; $m = $c['m'];
    $st = "stroke='$s' stroke-width='2' stroke-linejoin='round'";
    $sole = "<path d='M64 284Q92 291 120 284' stroke='#1d2233' stroke-width='4' fill='none' stroke-linecap='round'/>";
    switch ($d) {
        case 'sandals':
            $one = "<path d='M64 282Q92 290 120 282L118 276Q92 282 66 276Z' fill='$M' $st/><path d='M74 262L82 278M96 262L98 278' stroke='$a' stroke-width='3' stroke-linecap='round'/><path d='M70 270Q94 262 116 270' stroke='$a' stroke-width='3' fill='none'/>";
            break;
        case 'boots':
            $one = "<path d='M66 252H112L118 262L120 285Q92 292 64 285Z' fill='$M' $st/><path d='M66 258H112' stroke='$a' stroke-width='6'/>$sole<path d='M72 270Q92 266 112 272' stroke='$l' stroke-width='2' fill='none' opacity='.7'/>";
            break;
        case 'greaves':
            $one = "<path d='M66 246H114L118 262L120 285Q92 292 64 285Z' fill='$M' $st/><path d='M68 254H114M70 264H116' stroke='$l' stroke-width='2.4' opacity='.8'/><ellipse cx='90' cy='250' rx='18' ry='7' fill='$m' $st/>$sole";
            break;
        case 'riding':
            $one = "<path d='M64 236H114L118 262L120 285Q92 292 64 285Z' fill='$M' $st/><path d='M62 238H116L114 248H64Z' fill='$a' $st/><path d='M70 266Q92 262 114 268' stroke='$l' stroke-width='2' fill='none' opacity='.7'/><circle cx='118' cy='276' r='3' fill='$l'/>$sole";
            break;
        case 'sabaton':
            $one = "<path d='M64 240H114L120 262L124 284Q94 294 62 286Z' fill='$M' $st/><path d='M66 250H114M66 262H118M64 273H122' stroke='$l' stroke-width='2.2' opacity='.85'/><path d='M96 262L124 270L124 284L104 284Z' fill='$m' $st/>$sole";
            break;
        case 'winged':
            $w = "<path d='M64 262Q40 262 28 238Q48 242 62 250ZM64 270Q38 276 22 262Q44 258 62 262Z' fill='$l' $st/>";
            $one = $w . "<path d='M64 248H114L118 262L120 285Q92 292 64 285Z' fill='$M' $st/><path d='M66 256H114' stroke='$a' stroke-width='4'/>$sole";
            break;
        case 'spiked':
            $one = "<path d='M64 246H114L118 262L120 285Q92 292 64 285Z' fill='$M' $st/><path d='M70 248L74 234L80 248ZM86 248L90 232L96 248ZM102 248L106 234L110 248Z' fill='$l' $st/><path d='M66 262H116' stroke='$a' stroke-width='5'/>$sole";
            break;
        case 'tabi':
            $one = "<path d='M66 258H112L116 270L120 282L92 292L70 286Z' fill='$M' $st/><path d='M110 276L118 276' stroke='$s' stroke-width='2'/><path d='M66 262H112' stroke='$a' stroke-width='5'/><path d='M62 288Q90 296 124 288' stroke='$s' stroke-width='4' fill='none' stroke-linecap='round'/>";
            break;
        case 'fur':
            $one = "<path d='M64 250H116L120 285Q92 292 62 285Z' fill='$M' $st/><path d='M62 250Q70 244 76 252Q84 242 92 252Q100 242 108 252Q114 244 120 250V258H62Z' fill='#f4ecd6' $st/>$sole";
            break;
        default:
            return fa_boots('boots', $c);
    }
    return fa_pair($one);
}

/* -------------------------------- WINGS / AURA / CHARM -------------------- */

function fa_wings(string $d, array $c): string
{
    $M = $c['M']; $s = $c['s']; $l = $c['l']; $g = $c['g'];
    $st = "stroke='$s' stroke-width='2' stroke-linejoin='round'";
    switch ($d) {
        case 'bat':
            $w = "<path d='M66 160Q20 120 -10 130Q10 110 -4 84Q20 94 28 70Q38 92 54 84Q56 110 80 128Z' fill='$M' $st/><path d='M-4 84L58 140M28 70L60 140' stroke='$s' stroke-width='1.6' opacity='.7'/>";
            break;
        case 'fairy':
            $w = "<path d='M66 150Q10 110 10 40Q60 62 80 120Z' fill='$l' opacity='.85' $st/><path d='M66 172Q20 190 16 232Q56 224 78 190Z' fill='$M' opacity='.8' $st/>";
            break;
        case 'angel':
            $w = "<path d='M64 154Q8 128 -6 60Q20 78 34 66Q30 88 52 96Q50 76 70 86Q60 120 80 134Z' fill='$l' $st/><path d='M62 168Q16 160 -8 126Q22 134 40 126Q46 148 78 152Z' fill='$M' $st/>";
            break;
        case 'dragon':
            $w = "<path d='M64 156Q10 140 -14 70Q4 66 12 40Q26 62 40 52Q44 80 60 80Q58 110 80 128Z' fill='$M' $st/><path d='M12 40L62 126M40 52L66 128M-14 70L56 134' stroke='$l' stroke-width='1.8' opacity='.8'/>";
            break;
        default:
            return fa_wings('angel', $c);
    }
    return $w . '<g transform="translate(240 0) scale(-1 1)">' . $w . '</g>';
}

function fa_aura(string $d, array $c): string
{
    $g = $c['g']; $l = $c['l'];
    switch ($d) {
        case 'ring':
            return "<circle cx='120' cy='170' r='124' fill='none' stroke='$g' stroke-width='5' opacity='.55'/><circle cx='120' cy='170' r='108' fill='none' stroke='$l' stroke-width='2' opacity='.5' stroke-dasharray='6 8'/>";
        case 'flame':
            return "<path d='M120 20Q150 70 130 100Q180 80 170 150Q196 130 190 200Q170 250 120 262Q70 250 50 200Q44 130 70 150Q60 80 110 100Q92 70 120 20Z' fill='$g' opacity='.4'/>";
        case 'sparks':
            return "<circle cx='120' cy='170' r='122' fill='$g' opacity='.16'/><path d='M40 90l4 10 10 4-10 4-4 10-4-10-10-4 10-4ZM200 70l4 10 10 4-10 4-4 10-4-10-10-4 10-4ZM30 220l3 8 8 3-8 3-3 8-3-8-8-3 8-3ZM210 210l3 8 8 3-8 3-3 8-3-8-8-3 8-3Z' fill='$l' opacity='.9'/>";
        default:
            return "<circle cx='120' cy='170' r='128' fill='$g' opacity='.28'/>";
    }
}

function fa_charm(string $d, array $c): string
{
    $M = $c['M']; $s = $c['s']; $l = $c['l']; $g = $c['g'];
    $ch = "<path d='M96 144Q120 180 144 144' stroke='$l' stroke-width='2' fill='none' opacity='.8'/>";
    switch ($d) {
        case 'gem':
            return $ch . "<path d='M120 160l10 10-10 18-10-18Z' fill='$g' stroke='$s' stroke-width='2' filter='url(#ftGlow)'/>";
        case 'medal':
            return $ch . "<circle cx='120' cy='172' r='9' fill='$M' stroke='$s' stroke-width='2'/><path d='M116 170l4-4 4 4-4 8Z' fill='$l'/>";
        case 'tooth':
            return $ch . "<path d='M114 160Q120 192 126 160Z' fill='#f4ecd6' stroke='$s' stroke-width='2'/>";
        case 'scarf':
            return "<path d='M80 146Q120 166 160 146L158 158Q120 178 82 158Z' fill='$M' stroke='$s' stroke-width='2'/><path d='M140 156L152 196L138 194L132 164Z' fill='$M' stroke='$s' stroke-width='2'/>";
        case 'shell':
            return $ch . "<path d='M108 176Q108 158 120 158Q132 158 132 176Q120 184 108 176Z' fill='$M' stroke='$s' stroke-width='2'/><path d='M120 160V180M113 162L116 180M127 162L124 180' stroke='$s' stroke-width='1.4'/>";
        default:
            return $ch . "<circle cx='120' cy='170' r='8' fill='$M' stroke='$s' stroke-width='2'/>";
    }
}

/* ------------------------------ ITEM -> DESIGN MAP ------------------------- */

/** Which design and colours an item uses; null means "use the old tier art". */
function fa_spec(string $key): ?array
{
    static $map = null;
    if ($map === null) {
        $map = fa_spec_map();
    }
    return $map[$key] ?? null;
}

/** Draws one slot of an item. For armor returns ['k'=>,'b'=>,'o'=>]; others a string. */
function fa_draw(string $slot, array $spec): array|string
{
    $c = fa_colors($spec[1]);
    $d = $spec[0];
    return match ($slot) {
        'armor' => fa_armor($d, $c),
        'weapon' => fa_weapon($d, $c),
        'helmet' => fa_helmet($d, $c),
        'gloves' => fa_gloves($d, $c),
        'boots' => fa_boots($d, $c),
        'wings' => fa_wings($d, $c),
        'aura' => fa_aura($d, $c),
        'charm' => fa_charm($d, $c),
        default => '',
    };
}

/* ------------------------------ CATALOG OF LOOKS --------------------------- */

/** Look-only outfit sets: id => [name, pieces slot => [name, design, palette, price]]. */
function fa_look_sets(): array
{
    return [
        'sam' => ['Samurai', ['weapon' => ['Katana', 'katana', 'crimson', 420], 'helmet' => ['Kabuto', 'kabuto', 'crimson', 320], 'armor' => ['Samurai Armor', 'samurai', 'crimson', 520], 'gloves' => ['Samurai Bracers', 'bracer', 'crimson', 220], 'boots' => ['Tabi Boots', 'tabi', 'crimson', 240]]],
        'vik' => ['Viking', ['weapon' => ['Viking Axe', 'waraxe', 'iron', 400], 'helmet' => ['Horned Helm', 'horned', 'iron', 300], 'armor' => ['Furred Mail', 'viking', 'leather', 480], 'gloves' => ['Viking Wraps', 'wraps', 'leather', 200], 'boots' => ['Fur Boots', 'fur', 'leather', 220]]],
        'nin' => ['Shadow', ['weapon' => ['Shadow Dagger', 'dagger', 'shadow', 380], 'helmet' => ['Shadow Mask', 'mask', 'shadow', 300], 'armor' => ['Shadow Gi', 'gi', 'shadow', 460], 'gloves' => ['Shadow Wraps', 'wraps', 'shadow', 200], 'boots' => ['Shadow Tabi', 'tabi', 'shadow', 220]]],
        'mag' => ['Archmage', ['weapon' => ['Star Staff', 'staff', 'royal', 440], 'helmet' => ['Wizard Hat', 'wizard', 'royal', 320], 'armor' => ['Archmage Robe', 'robe', 'royal', 520], 'gloves' => ['Silk Gloves', 'glove', 'royal', 200], 'boots' => ['Mage Boots', 'riding', 'royal', 240]]],
        'pir' => ['Pirate', ['weapon' => ['Sea Cutlass', 'cutlass', 'iron', 380], 'helmet' => ['Captain\'s Tricorn', 'tricorn', 'shadow', 320], 'armor' => ['Captain\'s Coat', 'coat', 'ocean', 480], 'gloves' => ['Lace Cuffs', 'cuffs', 'ocean', 200], 'boots' => ['Sea Boots', 'riding', 'leather', 240]]],
        'roy' => ['Royal', ['weapon' => ['Royal Sword', 'longsword', 'gold', 520], 'helmet' => ['Golden Crown', 'crown', 'gold', 420], 'armor' => ['Royal Robes', 'royal', 'royal', 560], 'gloves' => ['Royal Gloves', 'glove', 'gold', 240], 'boots' => ['Royal Boots', 'riding', 'royal', 260]]],
        'ran' => ['Ranger', ['weapon' => ['Ranger Spear', 'spear', 'forest', 360], 'helmet' => ['Leaf Crown', 'leaf', 'forest', 280], 'armor' => ['Ranger Jerkin', 'jerkin', 'forest', 420], 'gloves' => ['Ranger Gloves', 'glove', 'forest', 180], 'boots' => ['Ranger Boots', 'boots', 'forest', 200]]],
        'fro' => ['Frostguard', ['weapon' => ['Frostbrand', 'glowsword', 'frost', 560], 'helmet' => ['Ice Circlet', 'circlet', 'frost', 400], 'armor' => ['Frostmail Cloak', 'elven', 'frost', 600], 'gloves' => ['Frost Gauntlets', 'plated', 'frost', 260], 'boots' => ['Frost Greaves', 'greaves', 'frost', 280]]],
        'dra' => ['Dragonscale', ['weapon' => ['Dragon Blade', 'greatsword', 'lava', 600], 'helmet' => ['Dragon Helm', 'horned', 'lava', 440], 'armor' => ['Dragonscale Mail', 'scale', 'lava', 640], 'gloves' => ['Dragon Claws', 'claws', 'lava', 280], 'boots' => ['Dragon Boots', 'spiked', 'lava', 300]]],
        'gla' => ['Gladiator', ['weapon' => ['Arena Mace', 'mace', 'sun', 380], 'helmet' => ['Arena Helm', 'crest', 'sun', 320], 'armor' => ['Arena Scale', 'scale', 'sun', 460], 'gloves' => ['Arena Gauntlets', 'plated', 'sun', 220], 'boots' => ['Arena Sandals', 'sandals', 'sun', 200]]],
        'sak' => ['Sakura', ['weapon' => ['Blossom Katana', 'katana', 'sakura', 440], 'helmet' => ['Blossom Feather', 'feather', 'sakura', 300], 'armor' => ['Blossom Kimono', 'robe', 'sakura', 500], 'gloves' => ['Blossom Gloves', 'glove', 'sakura', 200], 'boots' => ['Blossom Tabi', 'tabi', 'sakura', 220]]],
        'rea' => ['Reaper', ['weapon' => ['Reaper Scythe', 'scythe', 'obsidian', 560], 'helmet' => ['Reaper Hood', 'hood', 'obsidian', 380], 'armor' => ['Reaper Robe', 'robe', 'obsidian', 580], 'gloves' => ['Reaper Claws', 'claws', 'obsidian', 260], 'boots' => ['Reaper Boots', 'boots', 'obsidian', 260]]],
        'sea' => ['Tidecaller', ['weapon' => ['Tide Trident', 'trident', 'ocean', 480], 'helmet' => ['Tide Circlet', 'circlet', 'ocean', 360], 'armor' => ['Tide Scale', 'scale', 'ocean', 540], 'gloves' => ['Tide Cuffs', 'cuffs', 'ocean', 220], 'boots' => ['Tide Sandals', 'sandals', 'ocean', 200]]],
        'tri' => ['Tribal', ['weapon' => ['Bone Spear', 'spear', 'bone', 300], 'helmet' => ['Feather Headdress', 'feather', 'bone', 260], 'armor' => ['Hide Wrap', 'rags', 'bone', 340], 'gloves' => ['Bone Wraps', 'wraps', 'bone', 160], 'boots' => ['Hide Sandals', 'sandals', 'bone', 160]]],
        'knt' => ['Obsidian Knight', ['weapon' => ['Night Greatsword', 'greatsword', 'obsidian', 640], 'helmet' => ['Night Helm', 'crest', 'obsidian', 480], 'armor' => ['Night Plate', 'tabard', 'obsidian', 680], 'gloves' => ['Night Gauntlets', 'spiked', 'obsidian', 300], 'boots' => ['Night Sabatons', 'sabaton', 'obsidian', 320]]],
        'gol' => ['Golden Guard', ['weapon' => ['Golden Mace', 'mace', 'gold', 600], 'helmet' => ['Golden Helm', 'winged', 'gold', 480], 'armor' => ['Golden Plate', 'plate', 'gold', 700], 'gloves' => ['Golden Gauntlets', 'plated', 'gold', 300], 'boots' => ['Golden Sabatons', 'sabaton', 'gold', 320]]],
        'cru' => ['Crusader', ['weapon' => ['Crusader Sword', 'longsword', 'steel', 440], 'helmet' => ['Crusader Helm', 'barbute', 'steel', 340], 'armor' => ['Crusader Tabard', 'tabard', 'crimson', 520], 'gloves' => ['Crusader Gauntlets', 'gauntlet', 'steel', 220], 'boots' => ['Crusader Greaves', 'greaves', 'steel', 240]]],
    ];
}

/** Single look-only extras: key => [name, slot, design, palette, price]. */
function fa_look_extras(): array
{
    $o = [];
    $wings = ['angel' => 'Angel', 'bat' => 'Bat', 'fairy' => 'Fairy', 'dragon' => 'Dragon'];
    $cols = ['frost' => 'Frost', 'crimson' => 'Crimson', 'gold' => 'Golden', 'obsidian' => 'Midnight', 'emerald' => 'Emerald', 'sakura' => 'Blossom'];
    $pick = ['angel' => ['frost', 'gold', 'sakura'], 'bat' => ['obsidian', 'crimson'], 'fairy' => ['emerald', 'sakura', 'frost'], 'dragon' => ['crimson', 'emerald', 'obsidian']];
    foreach ($pick as $d => $list) {
        foreach ($list as $p) {
            $o['look_wings_' . $d . '_' . $p] = [$cols[$p] . ' ' . $wings[$d] . ' Wings', 'wings', $d, $p, 600];
        }
    }
    $auras = ['ring' => 'Halo Ring', 'flame' => 'Flame', 'sparks' => 'Sparkle', 'glow' => 'Glow'];
    $apick = ['ring' => ['gold', 'frost', 'storm'], 'flame' => ['lava', 'mythril', 'emerald'], 'sparks' => ['sakura', 'gold', 'storm'], 'glow' => ['mythril', 'crimson', 'emerald']];
    $acol = ['gold' => 'Golden', 'frost' => 'Frost', 'storm' => 'Storm', 'lava' => 'Ember', 'mythril' => 'Mythril', 'emerald' => 'Emerald', 'sakura' => 'Blossom', 'crimson' => 'Crimson'];
    foreach ($apick as $d => $list) {
        foreach ($list as $p) {
            $o['look_aura_' . $d . '_' . $p] = [$acol[$p] . ' ' . $auras[$d], 'aura', $d, $p, 400];
        }
    }
    $charms = ['gem' => 'Gem Pendant', 'medal' => 'Medal', 'tooth' => 'Fang Necklace', 'scarf' => 'Scarf', 'shell' => 'Shell Necklace'];
    $cpick = ['gem' => ['mythril', 'crimson', 'emerald', 'storm'], 'medal' => ['gold', 'steel'], 'tooth' => ['bone'], 'scarf' => ['crimson', 'royal', 'forest', 'sun'], 'shell' => ['ocean', 'sakura']];
    $ccol = ['mythril' => 'Ice', 'crimson' => 'Ruby', 'emerald' => 'Emerald', 'storm' => 'Violet', 'gold' => 'Gold', 'steel' => 'Silver', 'bone' => 'Bone', 'royal' => 'Blue', 'forest' => 'Green', 'sun' => 'Orange', 'ocean' => 'Sea', 'sakura' => 'Pink'];
    foreach ($cpick as $d => $list) {
        foreach ($list as $p) {
            $o['look_charm_' . $d . '_' . $p] = [$ccol[$p] . ' ' . $charms[$d], 'charm', $d, $p, 200];
        }
    }
    return $o;
}

/** Map of item key => [design, palette]. */
function fa_spec_map(): array
{
    $suf = ['weapon' => 'w', 'helmet' => 'h', 'armor' => 'a', 'gloves' => 'g', 'boots' => 'b'];
    $stat = [
        'wan' => ['weapon' => ['stick', 'wood'], 'helmet' => ['cap', 'leather'], 'armor' => ['rags', 'wood'], 'gloves' => ['wraps', 'bone'], 'boots' => ['sandals', 'leather']],
        'sco' => ['weapon' => ['shortsword', 'steel'], 'helmet' => ['hood', 'forest'], 'armor' => ['jerkin', 'leather'], 'gloves' => ['glove', 'leather'], 'boots' => ['boots', 'leather']],
        'iro' => ['weapon' => ['axe', 'iron'], 'helmet' => ['barbute', 'iron'], 'armor' => ['chain', 'iron'], 'gloves' => ['gauntlet', 'iron'], 'boots' => ['greaves', 'iron']],
        'mer' => ['weapon' => ['cutlass', 'gold'], 'helmet' => ['tricorn', 'crimson'], 'armor' => ['coat', 'crimson'], 'gloves' => ['cuffs', 'crimson'], 'boots' => ['riding', 'leather']],
        'cha' => ['weapon' => ['greatsword', 'steel'], 'helmet' => ['crest', 'steel'], 'armor' => ['tabard', 'steel'], 'gloves' => ['plated', 'steel'], 'boots' => ['sabaton', 'steel']],
        'myt' => ['weapon' => ['glowsword', 'mythril'], 'helmet' => ['winged', 'mythril'], 'armor' => ['elven', 'mythril'], 'gloves' => ['plated', 'mythril'], 'boots' => ['winged', 'mythril']],
        'sto' => ['weapon' => ['maul', 'storm'], 'helmet' => ['horned', 'storm'], 'armor' => ['storm', 'storm'], 'gloves' => ['spiked', 'storm'], 'boots' => ['spiked', 'storm']],
    ];
    $m = [];
    foreach ($stat as $id => $pieces) {
        foreach ($pieces as $slot => $sp) {
            $m['set_' . $id . '_' . $suf[$slot]] = $sp;
            $m['look_' . $id . '_' . $suf[$slot]] = $sp;
        }
    }
    foreach (fa_look_sets() as $id => [$n, $pieces]) {
        foreach ($pieces as $slot => [$pn, $d, $p]) {
            $m['look_' . $id . '_' . $suf[$slot]] = [$d, $p];
        }
    }
    foreach (fa_look_extras() as $k => [$n, $slot, $d, $p]) {
        $m[$k] = [$d, $p];
    }
    return $m;
}

/** Shop items for the extra look-only sets and extras (same row shape as game_look_items). */
function fa_look_catalog(): array
{
    $suf = ['weapon' => 'w', 'helmet' => 'h', 'armor' => 'a', 'gloves' => 'g', 'boots' => 'b'];
    $c = [];
    foreach (fa_look_sets() as $id => [$sn, $pieces]) {
        foreach ($pieces as $slot => [$n, $d, $p, $price]) {
            $c['look_' . $id . '_' . $suf[$slot]] = [$n, '@', 'look_' . $slot, 0, 0, $price, 'Look only: ' . $sn . ' style, no bonus and no level needed', 2, $id];
        }
    }
    foreach (fa_look_extras() as $k => [$n, $slot, $d, $p, $price]) {
        $c[$k] = [$n, '@', 'look_' . $slot, 0, 0, $price, 'Look only: no bonus and no level needed', 2, 'x'];
    }
    return $c;
}

/** Icon crop (viewBox) per slot. */
function fa_icon(string $slot, array $spec, int $size): string
{
    $box = ['weapon' => '-46 -146 92 176', 'armor' => '10 120 220 160', 'helmet' => '8 -14 224 168', 'gloves' => '26 186 64 70', 'boots' => '56 232 128 64',
            'wings' => '-24 16 288 170', 'aura' => '-12 36 264 264', 'charm' => '84 138 72 56'][$slot] ?? '0 0 240 300';
    $art = fa_draw($slot, $spec);
    if (is_array($art)) {
        $art = $art['k'] . $art['b'] . $art['o'];
    }
    return '<svg class="gearico" width="' . $size . '" height="' . $size . '" viewBox="' . $box . '" aria-hidden="true"><defs><filter id="ftGlow" x="-60%" y="-60%" width="220%" height="220%"><feGaussianBlur stdDeviation="2.6" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs>'
        . fa_defs($spec[1]) . $art . '</svg>';
}
