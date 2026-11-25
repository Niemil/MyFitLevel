<?php
declare(strict_types=1);

/* Gyms: shared gyms, home gyms and their equipment. */

const GYM_CATS = [
    'dumbbell' => 'Dumbbells',
    'barbell'  => 'Barbells & bars',
    'plates'   => 'Plates',
    'machine'  => 'Machines',
    'cable'    => 'Cable stations',
    'rack'     => 'Racks & benches',
    'cardio'   => 'Cardio',
    'other'    => 'Other',
];
/** Categories where a list of weights makes sense. */
const GYM_WEIGHT_CATS = ['dumbbell', 'barbell', 'plates', 'machine', 'cable'];

function gym_can_view(array $g, ?array $viewer): bool
{
    if ((int)$g['is_public'] === 1) {
        return true;
    }
    return $viewer && ((int)$viewer['id'] === (int)$g['owner_id'] || !empty($viewer['is_admin']));
}

function gym_can_edit(array $g, ?array $viewer): bool
{
    return $viewer && ((int)$viewer['id'] === (int)$g['owner_id'] || !empty($viewer['is_admin']));
}

/**
 * Parses "5, 7.5, 10-30/2.5, 32" into a sorted list of kg values.
 * Numbers are in $unit (what the member types); stored as kg.
 */
function gym_parse_weights(string $txt, string $unit): array
{
    $out = [];
    $f = $unit === 'lb' ? 1 / LB_PER_KG : 1.0;
    // "2,5" is a decimal comma; separate items with spaces or semicolons ("5 7,5 10-30/2,5")
    $txt = preg_replace('/(\d),(\d)/', '$1.$2', $txt);
    foreach (preg_split('/[;,\s]+/', trim($txt)) ?: [] as $tok) {
        $tok = trim($tok);
        if ($tok === '') {
            continue;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)(?:\/(\d+(?:\.\d+)?))?$/', $tok, $m)) {
            $a = (float)$m[1]; $b = (float)$m[2]; $st = isset($m[3]) ? (float)$m[3] : 1.0;
            if ($st <= 0 || $b < $a || ($b - $a) / $st > 200) {
                continue;
            }
            for ($x = $a; $x <= $b + 0.0001; $x += $st) {
                $out[] = round($x * $f, 3);
            }
        } elseif (preg_match('/^\d+(?:\.\d+)?$/', $tok)) {
            $out[] = round((float)$tok * $f, 3);
        }
    }
    $out = array_values(array_unique(array_filter($out, fn($v) => $v > 0 && $v <= 1000)));
    sort($out);
    return array_slice($out, 0, 200);
}

/** Weights (stored in kg) as a display list in the viewer's unit. */
function gym_weights_display(?string $json, string $unit): array
{
    $a = $json ? (json_decode($json, true) ?: []) : [];
    return array_map(fn($kg) => round($unit === 'lb' ? (float)$kg * LB_PER_KG : (float)$kg, 1), $a);
}

/** Gyms a member can pick when logging: own + saved + the one used last. Returns rows with 'db' (dumbbell weights in member's unit). */
function gyms_for_logger(array $u): array
{
    $uid = (int)$u['id'];
    $gs = rows('SELECT g.id, g.name, g.kind FROM gyms g
                WHERE g.owner_id = ? OR g.id IN (SELECT gym_id FROM gym_saved WHERE user_id = ?)
                   OR g.id = ?
                ORDER BY g.kind = \'home\' DESC, g.name', [$uid, $uid, (int)($u['default_gym_id'] ?? 0)]);
    if (!$gs) {
        return [];
    }
    $ids = array_map(fn($g) => (int)$g['id'], $gs);
    $by = [];
    foreach (rows("SELECT gym_id, weights FROM gym_items WHERE cat = 'dumbbell' AND weights IS NOT NULL AND gym_id IN (" . in_clause($ids) . ')', $ids) as $r) {
        foreach (gym_weights_display($r['weights'], $u['unit']) as $w) {
            $by[$r['gym_id']][] = $w;
        }
    }
    foreach ($gs as &$g) {
        $d = array_values(array_unique($by[$g['id']] ?? []));
        sort($d);
        $g = ['id' => (int)$g['id'], 'name' => $g['name'], 'home' => $g['kind'] === 'home', 'db' => $d];
    }
    return $gs;
}

/** Small "📍 Gym name" link for workout cards. $g needs gym_id, gym_name, gym_public, gym_owner. */
function gym_chip(array $w, ?array $viewer): string
{
    if (empty($w['gym_id']) || empty($w['gym_name'])) {
        return '';
    }
    $ok = (int)($w['gym_public'] ?? 0) === 1
        || ($viewer && ((int)$viewer['id'] === (int)($w['gym_owner'] ?? 0) || !empty($viewer['is_admin'])));
    $label = '📍 ' . e($w['gym_name']);
    return $ok
        ? '<a class="gymchip" href="' . e(url('gym/' . (int)$w['gym_id'])) . '">' . $label . '</a>'
        : '<span class="gymchip">' . $label . '</span>';
}
