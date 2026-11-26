<?php
declare(strict_types=1);

/** Shared gradients and filters, emitted once per page. */
function fito_defs(bool $force = false): string
{
    static $done = false;
    if ($done && !$force) return '';
    $done = true;
    return <<<'SVG'
<defs>
<linearGradient id="ftFur" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#a9b1c1"/><stop offset="1" stop-color="#727a8c"/></linearGradient>
<linearGradient id="ftFurL" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#cdd3df"/><stop offset="1" stop-color="#9aa2b3"/></linearGradient>
<linearGradient id="ftLight" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f4f6fa"/><stop offset="1" stop-color="#c3c9d6"/></linearGradient>
<linearGradient id="ftTank" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b4254"/><stop offset="1" stop-color="#232838"/></linearGradient>
<radialGradient id="ftEye"><stop offset="0" stop-color="#eaf8ff"/><stop offset=".4" stop-color="#62ccff"/><stop offset="1" stop-color="#1b86e0"/></radialGradient>
<filter id="ftGlow" x="-60%" y="-60%" width="220%" height="220%"><feGaussianBlur stdDeviation="2.6" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
</defs>
SVG;
}

/**
 * Fito, the MyFitLevel wolf mascot (inline SVG, drawn in code, no image files).
 * Parts carry classes (f-head, f-eyes, f-tail, f-armL ...) so CSS can animate them separately.
 * Poses: idle | cheer | flex | lift | oops.  $face = head only (small logos).
 */
function fito(string $pose = 'idle', int $size = 120, string $class = '', bool $face = false): string
{
    $poses = ['idle', 'cheer', 'flex', 'lift', 'oops', 'squat', 'curl'];
    if (!in_array($pose, $poses, true)) $pose = 'idle';

    $g = fito_defs();

    $limb = 'stroke="url(#ftFur)" stroke-linecap="round" fill="none"';
    $fistTxt = 'stroke="#343a48" stroke-width="2.4" stroke-linecap="round" opacity=".6"';
    $wrap = fn(string $at): string => '<g ' . $at . '><rect width="28" height="11" rx="5" fill="#e9ecf3"/><rect y="7" width="28" height="2" fill="#b9bfcc"/></g>';

    /* ---- left arm per pose (the right arm is the mirror image) ---- */
    switch ($pose) {
        case 'flex':
            $arm = '<path d="M60 164L26 174" ' . $limb . ' stroke-width="34"/><path d="M26 174L32 122" ' . $limb . ' stroke-width="28"/>'
                . '<ellipse cx="46" cy="156" rx="19" ry="14" fill="#dfe3ec" opacity=".5" transform="rotate(-25 46 156)"/>'
                . $wrap('transform="translate(17 132)"')
                . '<circle cx="32" cy="108" r="19" fill="url(#ftFurL)"/><path d="M22 100V112M32 98V111M42 100V112" ' . $fistTxt . '/>';
            break;
        case 'cheer':
            $arm = '<path d="M58 160L32 132" ' . $limb . ' stroke-width="34"/><path d="M32 132L26 92" ' . $limb . ' stroke-width="28"/>'
                . '<ellipse cx="44" cy="146" rx="17" ry="12" fill="#dfe3ec" opacity=".5" transform="rotate(-48 44 146)"/>'
                . $wrap('transform="translate(12 106)"')
                . '<circle cx="25" cy="80" r="19" fill="url(#ftFurL)"/><path d="M15 72V84M25 70V83M35 72V84" ' . $fistTxt . '/>';
            break;
        case 'lift':
            $arm = '<path d="M58 160L34 128" ' . $limb . ' stroke-width="34"/><path d="M34 128L38 42" ' . $limb . ' stroke-width="28"/>'
                . '<ellipse cx="46" cy="140" rx="17" ry="12" fill="#dfe3ec" opacity=".5" transform="rotate(-52 46 140)"/>'
                . $wrap('transform="translate(24 78)"')
                . '<circle cx="38" cy="22" r="17" fill="url(#ftFurL)"/>';
            break;
        case 'squat':
            $arm = '<path d="M58 160L28 184" ' . $limb . ' stroke-width="34"/><path d="M28 184L32 148" ' . $limb . ' stroke-width="28"/>'
                . '<ellipse cx="42" cy="172" rx="16" ry="12" fill="#dfe3ec" opacity=".5" transform="rotate(-40 42 172)"/>'
                . '<circle cx="32" cy="142" r="17" fill="url(#ftFurL)"/><path d="M23 134V146M32 132V145M41 134V146" ' . $fistTxt . '/>';
            break;
        case 'curl':
            $arm = '<path d="M56 160L42 208" ' . $limb . ' stroke-width="34"/>'
                . '<ellipse class="f-bicep" cx="52" cy="186" rx="13" ry="20" fill="#dfe3ec" opacity=".55"/>'
                . '<g class="f-fore"><path d="M42 208L38 246" ' . $limb . ' stroke-width="28"/>'
                . '<rect x="30" y="240" width="40" height="7" rx="3" fill="#cfd5e2"/><rect x="18" y="226" width="12" height="36" rx="4" fill="#1d2233"/><rect x="70" y="226" width="12" height="36" rx="4" fill="#1d2233"/>'
                . '<circle cx="42" cy="246" r="15" fill="url(#ftFurL)"/></g>';
            break;
        case 'oops':
            $arm = '<path d="M58 160L30 178" ' . $limb . ' stroke-width="34"/><path d="M30 178L16 148" ' . $limb . ' stroke-width="28"/>'
                . '<ellipse cx="42" cy="166" rx="16" ry="12" fill="#dfe3ec" opacity=".5" transform="rotate(-30 42 166)"/>'
                . $wrap('transform="translate(2 156) rotate(-62 14 6)"')
                . '<circle cx="14" cy="132" r="18" fill="url(#ftFurL)"/><path d="M5 124V136M14 122V135M23 124V136" ' . $fistTxt . '/>';
            break;
        default: /* idle: fists on hips */
            $arm = '<path d="M56 160L26 190" ' . $limb . ' stroke-width="35"/><path d="M26 190L50 222" ' . $limb . ' stroke-width="28"/>'
                . '<ellipse cx="40" cy="170" rx="16" ry="12" fill="#dfe3ec" opacity=".5" transform="rotate(-45 40 170)"/>'
                . '<path d="M46 150Q30 162 22 184" stroke="#363c4b" stroke-opacity=".45" stroke-width="3" fill="none" stroke-linecap="round"/>'
                . $wrap('transform="translate(41 201) rotate(54) translate(-13 -5)"')
                . '<circle cx="58" cy="228" r="17" fill="url(#ftFurL)"/><path d="M50 222L54 233M59 220L61 233M68 223L67 234" ' . $fistTxt . '/>';
    }
    $armL = '<g class="f-armL">' . $arm . '</g>';
    $armR = '<g class="f-armR"><g transform="translate(240 0) scale(-1 1)">' . $arm . '</g></g>';

    $bar = '';
    if ($pose === 'lift') {
        $bar = '<g class="f-bar"><rect x="-8" y="15" width="256" height="6" rx="3" fill="#cfd5e2"/>'
            . '<rect x="-6" y="-12" width="13" height="60" rx="4" fill="#1d2233"/>'
            . '<rect x="9" y="-4" width="9" height="44" rx="3" fill="#2b3142"/>'
            . '<rect x="233" y="-12" width="13" height="60" rx="4" fill="#1d2233"/>'
            . '<rect x="222" y="-4" width="9" height="44" rx="3" fill="#2b3142"/></g>';
        $armL = '<g class="f-lift">' . $armL . $armR . $bar . '</g>';
        $armR = '';
    }

    /* ---- face per pose ---- */
    switch ($pose) {
        case 'cheer':
            $brows = '<path d="M76 84L110 90" stroke="#252a38" stroke-width="5.5" stroke-linecap="round"/><path d="M164 84L130 90" stroke="#252a38" stroke-width="5.5" stroke-linecap="round"/>';
            $mouth = '<path d="M98 140Q120 178 142 140Z" fill="#10131d"/><path d="M108 154Q120 168 132 154Q120 160 108 154Z" fill="#ff6b8a"/><path d="M104 142L108 150L112 143Z" fill="#fff"/><path d="M130 143L134 150L138 142Z" fill="#fff"/>';
            break;
        case 'oops':
            $brows = '<path d="M76 90L112 84" stroke="#252a38" stroke-width="5.5" stroke-linecap="round"/><path d="M164 90L128 84" stroke="#252a38" stroke-width="5.5" stroke-linecap="round"/>';
            $mouth = '<path d="M100 148Q108 142 116 148T132 148T142 146" stroke="#10131d" stroke-width="3.6" fill="none" stroke-linecap="round"/>';
            break;
        default:
            $brows = '<path d="M74 80L112 92" stroke="#252a38" stroke-width="6.5" stroke-linecap="round"/><path d="M166 80L128 92" stroke="#252a38" stroke-width="6.5" stroke-linecap="round"/>';
            $mouth = '<path d="M120 134V142M98 144Q118 156 142 140" stroke="#10131d" stroke-width="3.6" fill="none" stroke-linecap="round"/><path d="M105 148L108 155L112 150Z" fill="#fff"/><path d="M130 148L134 154L137 145Z" fill="#fff"/>';
    }

    $head = '<g class="f-head"><g transform="translate(120 112) scale(.88) translate(-120 -100)">'
        . '<path d="M78 138L66 156L90 150L84 170L108 156L120 178L132 156L156 170L150 150L174 156L162 138Q120 166 78 138Z" fill="url(#ftLight)"/>'
        . '<g class="f-earL"><path d="M60 90Q54 44 62 12Q66 8 72 12L114 52Z" fill="url(#ftFur)"/><path d="M70 72Q66 44 70 26L98 52Z" fill="#2f3441"/></g>'
        . '<g class="f-earR"><path d="M180 90Q186 44 178 12Q174 8 168 12L126 52Z" fill="url(#ftFur)"/><path d="M170 72Q174 44 170 26L142 52Z" fill="#2f3441"/></g>'
        . '<path d="M60 88Q58 56 120 46Q182 56 180 88L198 104L178 104L190 126L160 118Q152 144 120 152Q88 144 80 118L50 126L62 104L42 104Z" fill="url(#ftFur)"/>'
        . '<path d="M62 108L46 120L64 120L52 136L86 126Q74 116 62 108Z" fill="url(#ftLight)"/><path d="M178 108L194 120L176 120L188 136L154 126Q166 116 178 108Z" fill="url(#ftLight)"/>'
        . '<path d="M76 98L60 116L88 108Z" fill="#2f3441" opacity=".8"/><path d="M164 98L180 116L152 108Z" fill="#2f3441" opacity=".8"/>'
        . '<path d="M90 104Q120 88 150 104Q160 140 120 168Q80 140 90 104Z" fill="url(#ftLight)"/>'
        . '<path d="M120 50Q130 70 122 90Q118 100 118 100L112 90Q108 70 120 50Z" fill="#a9b0c0" opacity=".9"/>'
        . '<path d="M112 92H128L124 108H116Z" fill="#6b7384" opacity=".55"/>'
        . '<ellipse cx="88" cy="76" rx="7" ry="3.6" fill="url(#ftLight)"/><ellipse cx="152" cy="76" rx="7" ry="3.6" fill="url(#ftLight)"/>'
        . '<g class="f-eyes"><path d="M80 90L108 95Q104 103 90 101Q80 98 80 90Z" fill="url(#ftEye)" filter="url(#ftGlow)"/><path d="M160 90L132 95Q136 103 150 101Q160 98 160 90Z" fill="url(#ftEye)" filter="url(#ftGlow)"/>'
        . '<ellipse cx="95" cy="97" rx="2.4" ry="4.4" fill="#0a1220"/><ellipse cx="145" cy="97" rx="2.4" ry="4.4" fill="#0a1220"/>'
        . '<circle cx="99" cy="94" r="1.8" fill="#fff"/><circle cx="141" cy="94" r="1.8" fill="#fff"/></g>'
        . $brows
        . '<path d="M92 68L100 88" stroke="#e8ebf2" stroke-width="2.2" stroke-linecap="round" opacity=".75"/>'
        . '<path d="M105 116Q120 106 135 116Q133 132 120 134Q107 132 105 116Z" fill="#10131d"/><ellipse cx="115" cy="115" rx="5" ry="2" fill="#fff" opacity=".35"/>'
        . $mouth
        . '</g></g>';

    $sweat = $pose === 'oops' ? '<path class="f-sweat" d="M182 52Q191 68 182 76Q173 68 182 52Z" fill="#7fd6ff"/>' : '';

    $shadow = '<ellipse class="f-shadow" cx="120" cy="290" rx="70" ry="7" fill="#000" opacity=".3"/>';
    $tail = '<g class="f-tail"><path d="M168 238Q240 236 232 156Q226 214 160 214Z" fill="url(#ftFur)"/><path d="M232 156Q238 190 222 214Q216 188 232 156Z" fill="url(#ftLight)"/></g>';
    $legs = '<g class="f-legs"><path d="M80 240L116 240L114 272L78 272Z" fill="url(#ftFur)"/><path d="M124 240L160 240L162 272L126 272Z" fill="url(#ftFur)"/>'
        . '<path d="M66 268Q94 256 118 268L120 285Q92 292 64 285Z" fill="#1d2233"/><path d="M174 268Q146 256 122 268L120 285Q148 292 176 285Z" fill="#1d2233"/>'
        . '<path d="M64 280Q92 287 120 281L120 286Q92 293 64 286Z" fill="#e9ecf3"/><path d="M176 280Q148 287 120 281L120 286Q148 293 176 286Z" fill="#e9ecf3"/>'
        . '<path d="M82 262L112 262M128 262L158 262" stroke="#fff" stroke-opacity=".6" stroke-width="3" stroke-linecap="round"/>'
        . '</g>';
    $upper = '<path d="M70 218L170 218L174 252L124 256L120 242L116 256L66 252Z" fill="#232838"/>'
        . '<path d="M68 220L64 250M172 220L176 250" stroke="#e9ecf3" stroke-width="4" stroke-linecap="round"/>'
        . '<g class="f-torso"><path d="M44 148Q120 122 196 148Q186 196 172 226Q120 240 68 226Q54 196 44 148Z" fill="url(#ftFur)"/>'
        . '<path d="M96 138Q120 150 144 138L140 160Q120 170 100 160Z" fill="url(#ftLight)" opacity=".85"/>'
        . '<path d="M120 164V224M102 186Q120 192 138 186M104 206Q120 212 136 206" stroke="#59607a" stroke-opacity=".5" stroke-width="2.4" fill="none" stroke-linecap="round"/>'
        . '<path d="M58 154Q84 140 112 160Q100 190 62 188Q54 172 58 154ZM182 154Q156 140 128 160Q140 190 178 188Q186 172 182 154Z" fill="url(#ftTank)"/>'
        . '<path d="M62 188Q78 196 96 190M178 188Q162 196 144 190" stroke="#1b2030" stroke-opacity=".5" stroke-width="2.4" fill="none" stroke-linecap="round"/></g>';

    $wide = in_array($pose, ['lift', 'squat'], true);
    $vb = $face ? '40 8 160 168' : ($wide ? '-14 -18 268 318' : '0 0 240 300');
    [$w, $h] = $face ? [$size, $size] : ($wide ? [(int)round($size * 0.86), $size] : [(int)round($size * 0.8), $size]);
    $cls = trim('mascot fito p-' . $pose . ($face ? ' f-face' : '') . ' ' . $class);
    $open = '<svg class="' . htmlspecialchars($cls, ENT_QUOTES) . '" width="' . $w . '" height="' . $h . '" viewBox="' . $vb . '" role="img" aria-label="Fito, the MyFitLevel wolf">';

    if ($face) return $open . $g . $head . '</svg>';

    if ($pose === 'squat') {
        $sqbar = '<g class="f-bar"><rect x="-8" y="141" width="256" height="6" rx="3" fill="#cfd5e2"/>'
            . '<rect x="-6" y="112" width="13" height="64" rx="4" fill="#1d2233"/><rect x="9" y="120" width="9" height="48" rx="3" fill="#2b3142"/>'
            . '<rect x="233" y="112" width="13" height="64" rx="4" fill="#1d2233"/><rect x="222" y="120" width="9" height="48" rx="3" fill="#2b3142"/></g>';
        return $open . $g . $shadow . $legs . '<g class="f-sq">' . $tail . $upper . $sqbar . $armL . $armR . $head . '</g></svg>';
    }
    return $open . $g . $shadow . $tail . $legs . $upper . $armL . $armR . $head . $sweat . '</svg>';
}

/** Side view: Fito on a bench doing a barbell press. */
function fito_bench(int $size = 160, string $class = ''): string
{
    $cls = trim('mascot fito p-bench ' . $class);
    $limb = 'stroke="url(#ftFur)" stroke-linecap="round" fill="none"';
    return '<svg class="' . htmlspecialchars($cls, ENT_QUOTES) . '" width="' . round($size * 1.6) . '" height="' . $size . '" viewBox="0 0 320 200" role="img" aria-label="Fito doing a bench press">'
        . fito_defs()
        . '<ellipse cx="160" cy="192" rx="130" ry="6" fill="#000" opacity=".28"/>'
        . '<rect x="70" y="136" width="12" height="56" rx="3" fill="#1d2233"/><rect x="222" y="136" width="12" height="56" rx="3" fill="#1d2233"/>'
        . '<rect x="44" y="120" width="216" height="18" rx="8" fill="#2b3142"/><rect x="44" y="120" width="216" height="5" rx="2.5" fill="#46506a"/>'
        /* far leg, then near leg, feet on the floor */
        . '<path d="M212 112L252 94L262 168" ' . $limb . ' stroke-width="20" stroke-linejoin="round" opacity=".8"/>'
        . '<path d="M206 114L244 96L254 170" ' . $limb . ' stroke-width="22" stroke-linejoin="round"/>'
        . '<path d="M236 186Q256 176 276 184L276 190L236 190Z" fill="#1d2233"/><rect x="236" y="188" width="40" height="3" rx="1.5" fill="#e9ecf3"/>'
        . '<g class="f-torso"><path d="M88 100Q130 92 200 100L214 122L92 122Q84 112 88 100Z" fill="url(#ftFur)"/>'
        . '<path d="M96 104Q124 98 150 104L148 120L98 120Z" fill="#dfe3ec" opacity=".45"/>'
        . '<path d="M196 98L222 100L224 124L196 122Z" fill="#232838"/></g>'
        /* head, face up, snout toward the ceiling */
        . '<g class="f-head"><path d="M52 92L26 84L38 112Z" fill="url(#ftFur)"/><path d="M48 96L36 90L42 106Z" fill="#2f3441"/>'
        . '<circle cx="70" cy="108" r="27" fill="url(#ftFur)"/>'
        . '<path d="M58 92Q66 70 82 64Q94 64 94 76Q92 92 84 106L74 112Z" fill="url(#ftLight)"/>'
        . '<ellipse cx="90" cy="66" rx="7" ry="5" transform="rotate(-30 90 66)" fill="#10131d"/>'
        . '<g class="f-eyes"><path d="M62 82L78 88Q75 96 65 94Z" fill="url(#ftEye)" filter="url(#ftGlow)"/><circle cx="69" cy="90" r="2" fill="#0a1220"/></g>'
        . '<path d="M58 76L82 85" stroke="#252a38" stroke-width="4.5" stroke-linecap="round"/>'
        . '<path d="M84 98Q92 92 96 84" stroke="#10131d" stroke-width="2.6" fill="none" stroke-linecap="round"/>'
        . '<path d="M72 122L100 124L86 134Z" fill="url(#ftLight)"/></g>'
        /* pressing arm and the bar seen end-on */
        . '<g class="b-bar"><circle cx="118" cy="26" r="30" fill="#1d2233"/><circle cx="118" cy="26" r="30" fill="none" stroke="#cfd5e2" stroke-width="3"/>'
        . '<circle cx="118" cy="26" r="20" fill="#2b3142"/><circle cx="118" cy="26" r="7" fill="#cfd5e2"/></g>'
        . '<g class="b-arm"><rect x="108" y="30" width="20" height="74" rx="10" fill="url(#ftFur)"/><rect x="106" y="84" width="24" height="14" rx="6" fill="#e9ecf3"/></g>'
        . '<g class="b-hand"><circle cx="118" cy="26" r="13" fill="url(#ftFurL)"/></g>'
        . '</svg>';
}

/** One move by name: bench | squat | curl | press. */
function fito_move(string $move, int $size = 150, string $class = ''): string
{
    if ($move === 'bench') return fito_bench($size, $class);
    return fito($move === 'press' ? 'lift' : $move, $size, $class);
}
