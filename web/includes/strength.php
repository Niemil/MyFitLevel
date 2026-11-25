<?php
declare(strict_types=1);

/* Strength levels: estimated one-rep max relative to bodyweight, grouped into five tiers. */

const STRENGTH_TIERS = ['Beginner', 'Novice', 'Intermediate', 'Advanced', 'Elite'];

/** lift => [exercise name, thresholds (x bodyweight) for the five tiers] */
function strength_lifts(): array
{
    return [
        'Squat'          => ['Back Squat',           [0.75, 1.25, 1.75, 2.25, 2.75]],
        'Bench press'    => ['Barbell Bench Press',  [0.50, 0.75, 1.25, 1.75, 2.00]],
        'Deadlift'       => ['Deadlift',             [1.00, 1.50, 2.00, 2.75, 3.50]],
        'Overhead press' => ['Overhead Press',       [0.35, 0.55, 0.80, 1.10, 1.40]],
        'Barbell row'    => ['Barbell Row',          [0.50, 0.75, 1.00, 1.50, 1.75]],
    ];
}

function strength_levels(int $uid, ?float $bw): array
{
    $out = [];
    if (!$bw || $bw <= 0) {
        return $out;
    }
    foreach (strength_lifts() as $label => [$exName, $th]) {
        $best = (float)val(
            'SELECT COALESCE(MAX(s.weight_kg * (1 + LEAST(s.reps, 12) / 30)),0) FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id
             WHERE s.user_id = ? AND e.name = ? AND s.is_warmup = 0 AND s.weight_kg > 0 AND s.reps > 0', [$uid, $exName]);
        if ($best <= 0) {
            continue;
        }
        $ratio = $best / $bw;
        $tier = -1;
        foreach ($th as $i => $x) {
            if ($ratio >= $x) {
                $tier = $i;
            }
        }
        $next = $tier < 4 ? $th[$tier + 1] * $bw : null;
        $cur = $tier >= 0 ? $th[$tier] : 0.0;
        $pct = $tier >= 4 ? 100 : (int)round(max(0, min(1, ($ratio - $cur) / ($th[$tier + 1] - $cur))) * 100);
        $out[] = ['lift' => $label, 'kg' => $best, 'ratio' => $ratio, 'tier' => $tier, 'name' => $tier >= 0 ? STRENGTH_TIERS[$tier] : 'Getting started', 'next_kg' => $next, 'pct' => $pct];
    }
    return $out;
}
