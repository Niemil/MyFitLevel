<?php
declare(strict_types=1);

/* Exercise difficulty: Beginner, Intermediate, Advanced, Elite. Rule based, so new exercises get a sensible tier automatically. */

const TIER_LABELS = ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', 'elite' => 'Elite'];

function exercise_tier(array $e): string
{
    $n = strtolower((string)($e['name'] ?? ''));
    $eq = strtolower((string)($e['equipment'] ?? ''));
    $kind = (string)($e['kind'] ?? '');
    $has = function (array $words) use ($n): bool {
        foreach ($words as $w) {
            if (strpos($n, $w) !== false) {
                return true;
            }
        }
        return false;
    };
    if ($has(['muscle-up', 'hang snatch', 'clean and jerk', 'pistol squat', 'dragon flag', 'handstand', 'planche', 'front lever', 'one-arm', 'nordic hamstring', 'overhead squat', 'snatch', 'rope climb'])
        && !$has(['dumbbell snatch', 'kettlebell snatch'])) {
        return 'elite';
    }
    if ($has(['power clean', 'hang clean', 'front squat', 'bulgarian', 'deficit', 'pendlay', 'meadows', 'seal row', 'safety bar', 'good morning', 'ab wheel', 'toes-to-bar',
              'hanging leg raise', 'l-sit', 'archer', 'clap push', 'ring push', 'turkish', 'devil press', 'yoke', 'tire flip', 'thruster', 'dumbbell snatch', 'kettlebell snatch',
              'kettlebell clean', 'dumbbell clean', 'sandbag clean', 'glute-ham', 'single-leg hip thrust', 'cossack', 'copenhagen', 'dumbbell thruster', 'wall ball', 'box jump', 'burpee box'])) {
        return 'advanced';
    }
    if ($kind === 'cardio') {
        return $has(['sprint', 'hiit', 'crossfit', 'jiu-jitsu', 'wrestling', 'judo', 'rock climbing', 'bouldering', 'muay thai']) ? 'intermediate' : 'beginner';
    }
    if ($has(['push-up', 'bodyweight squat', 'plank', 'crunch', 'glute bridge', 'goblet', 'dead hang', 'superman', 'bird dog', 'dead bug', 'sit-up', 'jumping jack', 'curl', 'shrug', 'pushdown', 'lateral raise', 'fly', 'extension', 'raise'])
        && !$has(['hanging', 'archer', 'clap', 'ring', 'diamond'])) {
        return 'beginner';
    }
    if ($eq === 'dumbbell' && $has(['bench press', 'row', 'press', 'deadlift']) && !$has(['romanian'])) {
        return 'beginner';
    }
    if ($has(['pull-up', 'chin-up', 'dip', 'lunge', 'split squat', 'romanian', 'hip thrust', 'swing', 'row', 'deadlift', 'overhead press', 'bench press', 'back squat', 'rack pull', 'diamond', 'inverted', 'step-up', 'farmer', 'carry', 'jump', 'good', 'nordic'])) {
        return 'intermediate';
    }
    if (in_array($eq, ['machine', 'cable', 'band', 'bodyweight', 'dumbbell', 'plate', 'rope', 'medicine ball'], true)) {
        return 'beginner';
    }
    return 'intermediate';
}

function tier_chip(string $tier): string
{
    return '<span class="tier t-' . e($tier) . '">' . e(TIER_LABELS[$tier] ?? $tier) . '</span>';
}
