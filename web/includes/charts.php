<?php
declare(strict_types=1);

/* Small server-side SVG charts (no JavaScript needed). Colours come from CSS classes. */

function chart_calendar(array $byDay, int $days = 365): string
{
    $end = strtotime('today');
    $start = strtotime('-' . ($days - 1) . ' days', $end);
    $start -= ((int)date('N', $start) - 1) * 86400;           // back to Monday
    $max = max(1, $byDay ? max($byDay) : 1);
    $cell = 12; $gap = 3; $x0 = 22; $y0 = 16;
    $weeks = (int)ceil(($end - $start) / 86400 / 7) + 1;
    $w = $x0 + $weeks * ($cell + $gap); $h = $y0 + 7 * ($cell + $gap);
    $svg = '<svg class="cal" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Training calendar">';
    foreach ([0 => 'Mon', 2 => 'Wed', 4 => 'Fri'] as $r => $lbl) {
        $svg .= '<text x="0" y="' . ($y0 + $r * ($cell + $gap) + 10) . '" class="cl">' . $lbl . '</text>';
    }
    $lastMonth = '';
    for ($t = $start, $i = 0; $t <= $end; $t += 86400, $i++) {
        $col = intdiv($i, 7); $row = $i % 7;
        if ($t < strtotime('-' . ($days - 1) . ' days', $end)) { continue; }
        $d = date('Y-m-d', $t);
        $v = $byDay[$d] ?? 0;
        $lvl = $v <= 0 ? 0 : min(4, 1 + (int)floor(($v / $max) * 3.999));
        $x = $x0 + $col * ($cell + $gap); $y = $y0 + $row * ($cell + $gap);
        if ($row === 0 && date('M', $t) !== $lastMonth) { $lastMonth = date('M', $t); $svg .= '<text x="' . $x . '" y="10" class="cl">' . $lastMonth . '</text>'; }
        $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $cell . '" height="' . $cell . '" rx="3" class="c' . $lvl . '"><title>' . $d . ($v ? ': ' . number_format($v) . ' pts' : '') . '</title></rect>';
    }
    return $svg . '</svg>';
}

/** $pts: list of [label, value]. */
function chart_line(array $pts, string $unit = ''): string
{
    $n = count($pts);
    if ($n < 2) { return '<p class="muted small">Needs at least two sessions to draw a line.</p>'; }
    $W = 600; $H = 200; $l = 40; $r = 10; $t = 14; $b = 26;
    $vals = array_column($pts, 1);
    $min = min($vals); $max = max($vals);
    if ($max - $min < 1e-9) { $max = $min + 1; }
    $pad = ($max - $min) * 0.1; $lo = max(0, $min - $pad); $hi = $max + $pad;
    $X = fn($i) => $l + ($W - $l - $r) * ($n === 1 ? 0 : $i / ($n - 1));
    $Y = fn($v) => $t + ($H - $t - $b) * (1 - ($v - $lo) / ($hi - $lo));
    $svg = '<svg class="lchart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img">';
    for ($g = 0; $g <= 3; $g++) {
        $v = $lo + ($hi - $lo) * $g / 3; $y = $Y($v);
        $svg .= '<line x1="' . $l . '" x2="' . ($W - $r) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" class="gl"/><text x="' . ($l - 6) . '" y="' . round($y + 4, 1) . '" class="cl" text-anchor="end">' . round($v) . '</text>';
    }
    $d = ''; $area = '';
    foreach ($pts as $i => $p) { $d .= ($i ? 'L' : 'M') . round($X($i), 1) . ' ' . round($Y($p[1]), 1) . ' '; }
    $area = $d . 'L' . round($X($n - 1), 1) . ' ' . ($H - $b) . ' L' . $l . ' ' . ($H - $b) . ' Z';
    $svg .= '<path d="' . $area . '" class="la"/><path d="' . trim($d) . '" class="ll"/>';
    foreach ($pts as $i => $p) {
        $svg .= '<circle cx="' . round($X($i), 1) . '" cy="' . round($Y($p[1]), 1) . '" r="3.5" class="ld"><title>' . e((string)$p[0]) . ': ' . round($p[1], 1) . $unit . '</title></circle>';
    }
    $svg .= '<text x="' . $l . '" y="' . ($H - 6) . '" class="cl">' . e((string)$pts[0][0]) . '</text><text x="' . ($W - $r) . '" y="' . ($H - 6) . '" class="cl" text-anchor="end">' . e((string)$pts[$n - 1][0]) . '</text>';
    return $svg . '</svg>';
}

/** $bars: list of [label, value]. */
function chart_bars(array $bars): string
{
    $n = count($bars);
    if (!$n) { return ''; }
    $W = 600; $H = 170; $l = 8; $b = 22; $t = 10;
    $max = max(1, max(array_column($bars, 1)));
    $bw = ($W - $l * 2) / $n;
    $svg = '<svg class="lchart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img">';
    foreach ($bars as $i => $p) {
        $h = ($H - $t - $b) * ($p[1] / $max);
        $svg .= '<rect x="' . round($l + $i * $bw + 1, 1) . '" y="' . round($H - $b - $h, 1) . '" width="' . round($bw - 2, 1) . '" height="' . round(max(0, $h), 1) . '" rx="2" class="bar"><title>' . e((string)$p[0]) . ': ' . number_format((int)$p[1]) . ' pts</title></rect>';
    }
    $svg .= '<text x="' . $l . '" y="' . ($H - 6) . '" class="cl">' . e((string)$bars[0][0]) . '</text><text x="' . ($W - $l) . '" y="' . ($H - 6) . '" class="cl" text-anchor="end">' . e((string)$bars[$n - 1][0]) . '</text>';
    return $svg . '</svg>';
}

/** Level curve: list of [level, total points] on a log scale. */
function chart_levels(array $table, int $mark = 0): string
{
    $W = 640; $H = 260; $l = 54; $r = 12; $t = 12; $b = 28;
    $lvls = array_keys($table); $maxL = max($lvls);
    $maxV = log10(max(10, $table[$maxL]));
    $X = fn($lv) => $l + ($W - $l - $r) * (($lv - 1) / max(1, $maxL - 1));
    $Y = fn($v) => $t + ($H - $t - $b) * (1 - log10(max(1, $v)) / $maxV);
    $svg = '<svg class="lchart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Points needed per level">';
    for ($e = 1; $e <= (int)floor($maxV); $e++) {
        $y = $Y(10 ** $e);
        $svg .= '<line x1="' . $l . '" x2="' . ($W - $r) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" class="gl"/><text x="' . ($l - 6) . '" y="' . round($y + 4, 1) . '" class="cl" text-anchor="end">' . fmt_big(10 ** $e) . '</text>';
    }
    $d = '';
    foreach ($table as $lv => $v) { $d .= ($d === '' ? 'M' : 'L') . round($X($lv), 1) . ' ' . round($Y(max(1, $v)), 1) . ' '; }
    $svg .= '<path d="' . trim($d) . '" class="ll"/>';
    foreach ([1, 10, 25, 50, 75, 100] as $lv) {
        if (isset($table[$lv])) { $svg .= '<text x="' . round($X($lv), 1) . '" y="' . ($H - 8) . '" class="cl" text-anchor="middle">' . $lv . '</text>'; }
    }
    if ($mark && isset($table[$mark])) {
        $svg .= '<circle cx="' . round($X($mark), 1) . '" cy="' . round($Y(max(1, $table[$mark])), 1) . '" r="6" class="ld"><title>You are here: level ' . $mark . '</title></circle>';
    }
    return $svg . '<text x="' . ($W - $r) . '" y="' . ($t + 10) . '" class="cl" text-anchor="end">points (log scale) by level</text></svg>';
}
