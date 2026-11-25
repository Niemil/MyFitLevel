<?php
declare(strict_types=1);

/*
 * Built-in exercise list. Inserted by slug (INSERT IGNORE), so it is safe to run on every
 * update: new exercises appear, existing ones (and any admin edits) are left alone.
 * Row: [name, muscle, equipment, kind, coef, bw_pct, dist_coef]
 *  kind weight:     points = derive_coef() * e1RM^1.3 * scale (coef column in this seed is ignored)
 *  kind bodyweight: same, using bw_pct * your bodyweight + any added weight
 *  kind cardio:     coef per minute + dist_coef per km
 *  kind duration:   coef per minute
 */
function exercise_seed(): array
{
    return [
        // Chest
        ['Barbell Bench Press', 'chest', 'barbell', 'weight', 0.90, 0, 0],
        ['Incline Barbell Bench Press', 'chest', 'barbell', 'weight', 0.85, 0, 0],
        ['Decline Bench Press', 'chest', 'barbell', 'weight', 0.90, 0, 0],
        ['Dumbbell Bench Press', 'chest', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Incline Dumbbell Press', 'chest', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Dumbbell Fly', 'chest', 'dumbbell', 'weight', 1.20, 0, 0],
        ['Cable Crossover', 'chest', 'cable', 'weight', 0.75, 0, 0],
        ['Machine Chest Press', 'chest', 'machine', 'weight', 0.60, 0, 0],
        ['Pec Deck', 'chest', 'machine', 'weight', 0.60, 0, 0],
        ['Push-Up', 'chest', 'bodyweight', 'bodyweight', 1.00, 0.65, 0],
        ['Chest Dip', 'chest', 'bodyweight', 'bodyweight', 1.00, 1.00, 0],
        // Back
        ['Deadlift', 'back', 'barbell', 'weight', 1.10, 0, 0],
        ['Rack Pull', 'back', 'barbell', 'weight', 1.00, 0, 0],
        ['Barbell Row', 'back', 'barbell', 'weight', 0.85, 0, 0],
        ['Pendlay Row', 'back', 'barbell', 'weight', 0.85, 0, 0],
        ['Dumbbell Row', 'back', 'dumbbell', 'weight', 1.00, 0, 0],
        ['T-Bar Row', 'back', 'barbell', 'weight', 0.85, 0, 0],
        ['Seated Cable Row', 'back', 'cable', 'weight', 0.65, 0, 0],
        ['Lat Pulldown', 'back', 'cable', 'weight', 0.65, 0, 0],
        ['Close-Grip Lat Pulldown', 'back', 'cable', 'weight', 0.65, 0, 0],
        ['Pull-Up', 'back', 'bodyweight', 'bodyweight', 1.00, 1.00, 0],
        ['Chin-Up', 'back', 'bodyweight', 'bodyweight', 1.00, 1.00, 0],
        ['Assisted Pull-Up', 'back', 'machine', 'weight', 0.30, 0, 0],
        ['Face Pull', 'back', 'cable', 'weight', 0.70, 0, 0],
        ['Straight-Arm Pulldown', 'back', 'cable', 'weight', 0.70, 0, 0],
        ['Barbell Shrug', 'back', 'barbell', 'weight', 0.70, 0, 0],
        ['Dumbbell Shrug', 'back', 'dumbbell', 'weight', 0.80, 0, 0],
        ['Back Extension', 'back', 'bodyweight', 'bodyweight', 0.80, 0.50, 0],
        // Shoulders
        ['Overhead Press', 'shoulders', 'barbell', 'weight', 0.90, 0, 0],
        ['Push Press', 'shoulders', 'barbell', 'weight', 0.90, 0, 0],
        ['Seated Dumbbell Shoulder Press', 'shoulders', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Arnold Press', 'shoulders', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Machine Shoulder Press', 'shoulders', 'machine', 'weight', 0.60, 0, 0],
        ['Dumbbell Lateral Raise', 'shoulders', 'dumbbell', 'weight', 1.40, 0, 0],
        ['Cable Lateral Raise', 'shoulders', 'cable', 'weight', 1.20, 0, 0],
        ['Dumbbell Front Raise', 'shoulders', 'dumbbell', 'weight', 1.30, 0, 0],
        ['Rear Delt Fly', 'shoulders', 'dumbbell', 'weight', 1.30, 0, 0],
        ['Upright Row', 'shoulders', 'barbell', 'weight', 0.80, 0, 0],
        ['Handstand Push-Up', 'shoulders', 'bodyweight', 'bodyweight', 1.00, 0.90, 0],
        // Biceps
        ['Barbell Curl', 'biceps', 'barbell', 'weight', 0.90, 0, 0],
        ['EZ-Bar Curl', 'biceps', 'barbell', 'weight', 0.90, 0, 0],
        ['Dumbbell Curl', 'biceps', 'dumbbell', 'weight', 1.10, 0, 0],
        ['Hammer Curl', 'biceps', 'dumbbell', 'weight', 1.10, 0, 0],
        ['Incline Dumbbell Curl', 'biceps', 'dumbbell', 'weight', 1.10, 0, 0],
        ['Preacher Curl', 'biceps', 'barbell', 'weight', 0.90, 0, 0],
        ['Concentration Curl', 'biceps', 'dumbbell', 'weight', 1.10, 0, 0],
        ['Cable Curl', 'biceps', 'cable', 'weight', 0.75, 0, 0],
        // Triceps
        ['Close-Grip Bench Press', 'triceps', 'barbell', 'weight', 0.85, 0, 0],
        ['Triceps Pushdown', 'triceps', 'cable', 'weight', 0.75, 0, 0],
        ['Rope Triceps Pushdown', 'triceps', 'cable', 'weight', 0.75, 0, 0],
        ['Skull Crusher', 'triceps', 'barbell', 'weight', 0.90, 0, 0],
        ['Overhead Triceps Extension', 'triceps', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Dumbbell Kickback', 'triceps', 'dumbbell', 'weight', 1.30, 0, 0],
        ['Triceps Dip', 'triceps', 'bodyweight', 'bodyweight', 1.00, 1.00, 0],
        ['Bench Dip', 'triceps', 'bodyweight', 'bodyweight', 0.90, 0.60, 0],
        // Legs
        ['Back Squat', 'legs', 'barbell', 'weight', 1.00, 0, 0],
        ['Front Squat', 'legs', 'barbell', 'weight', 1.00, 0, 0],
        ['Goblet Squat', 'legs', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Smith Machine Squat', 'legs', 'machine', 'weight', 0.90, 0, 0],
        ['Hack Squat', 'legs', 'machine', 'weight', 0.80, 0, 0],
        ['Leg Press', 'legs', 'machine', 'weight', 0.50, 0, 0],
        ['Bulgarian Split Squat', 'legs', 'dumbbell', 'weight', 1.10, 0, 0],
        ['Dumbbell Lunge', 'legs', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Barbell Lunge', 'legs', 'barbell', 'weight', 1.00, 0, 0],
        ['Walking Lunge', 'legs', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Step-Up', 'legs', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Romanian Deadlift', 'legs', 'barbell', 'weight', 0.95, 0, 0],
        ['Stiff-Leg Deadlift', 'legs', 'barbell', 'weight', 0.95, 0, 0],
        ['Sumo Deadlift', 'legs', 'barbell', 'weight', 1.10, 0, 0],
        ['Leg Extension', 'legs', 'machine', 'weight', 0.55, 0, 0],
        ['Lying Leg Curl', 'legs', 'machine', 'weight', 0.55, 0, 0],
        ['Seated Leg Curl', 'legs', 'machine', 'weight', 0.55, 0, 0],
        ['Hip Thrust', 'legs', 'barbell', 'weight', 0.80, 0, 0],
        ['Glute Bridge', 'legs', 'bodyweight', 'bodyweight', 0.80, 0.40, 0],
        ['Standing Calf Raise', 'legs', 'machine', 'weight', 0.70, 0, 0],
        ['Seated Calf Raise', 'legs', 'machine', 'weight', 0.60, 0, 0],
        ['Bodyweight Squat', 'legs', 'bodyweight', 'bodyweight', 0.90, 0.50, 0],
        ['Box Jump', 'legs', 'bodyweight', 'bodyweight', 0.90, 0.50, 0],
        ['Wall Sit', 'legs', 'bodyweight', 'duration', 2.50, 0, 0],
        // Core
        ['Crunch', 'core', 'bodyweight', 'bodyweight', 0.80, 0.25, 0],
        ['Sit-Up', 'core', 'bodyweight', 'bodyweight', 0.80, 0.35, 0],
        ['Bicycle Crunch', 'core', 'bodyweight', 'bodyweight', 0.80, 0.25, 0],
        ['Hanging Leg Raise', 'core', 'bodyweight', 'bodyweight', 0.90, 0.50, 0],
        ['Cable Crunch', 'core', 'cable', 'weight', 0.65, 0, 0],
        ['Ab Wheel Rollout', 'core', 'bodyweight', 'bodyweight', 0.90, 0.55, 0],
        ['Russian Twist', 'core', 'bodyweight', 'weight', 1.00, 0, 0],
        ['Mountain Climber', 'core', 'bodyweight', 'bodyweight', 0.80, 0.30, 0],
        ['Plank', 'core', 'bodyweight', 'duration', 3.00, 0, 0],
        ['Side Plank', 'core', 'bodyweight', 'duration', 3.00, 0, 0],
        // Full body
        ['Power Clean', 'fullbody', 'barbell', 'weight', 0.95, 0, 0],
        ['Snatch', 'fullbody', 'barbell', 'weight', 0.90, 0, 0],
        ['Thruster', 'fullbody', 'barbell', 'weight', 0.90, 0, 0],
        ['Kettlebell Swing', 'fullbody', 'kettlebell', 'weight', 1.00, 0, 0],
        ['Turkish Get-Up', 'fullbody', 'kettlebell', 'weight', 1.20, 0, 0],
        ['Farmer\'s Carry', 'fullbody', 'dumbbell', 'weight', 0.80, 0, 0],
        ['Burpee', 'fullbody', 'bodyweight', 'bodyweight', 0.90, 0.70, 0],
        ['Battle Ropes', 'fullbody', 'rope', 'duration', 5.00, 0, 0],
        // Cardio (coef per minute, dist_coef per km)
        ['Running', 'cardio', 'outdoor', 'cardio', 1.50, 0, 7.00],
        ['Treadmill Run', 'cardio', 'machine', 'cardio', 1.50, 0, 7.00],
        ['Walking', 'cardio', 'outdoor', 'cardio', 0.80, 0, 3.00],
        ['Incline Treadmill Walk', 'cardio', 'machine', 'cardio', 1.10, 0, 3.00],
        ['Hiking', 'cardio', 'outdoor', 'cardio', 1.10, 0, 4.00],
        ['Cycling', 'cardio', 'outdoor', 'cardio', 1.00, 0, 2.50],
        ['Stationary Bike', 'cardio', 'machine', 'cardio', 1.10, 0, 2.00],
        ['Rowing Machine', 'cardio', 'machine', 'cardio', 1.60, 0, 6.00],
        ['Elliptical', 'cardio', 'machine', 'cardio', 1.20, 0, 2.00],
        ['Stair Climber', 'cardio', 'machine', 'cardio', 1.50, 0, 0],
        ['Swimming', 'cardio', 'pool', 'cardio', 2.00, 0, 25.00],
        ['Jump Rope', 'cardio', 'rope', 'cardio', 2.00, 0, 0],
        ['HIIT', 'cardio', 'any', 'cardio', 2.00, 0, 0],
        ['Cross-Country Skiing', 'cardio', 'outdoor', 'cardio', 1.80, 0, 5.00],
        ['Ice Skating', 'cardio', 'outdoor', 'cardio', 1.30, 0, 3.00],
        ['Team Sport / Ball Game', 'cardio', 'any', 'cardio', 1.50, 0, 0],
        ['Martial Arts', 'cardio', 'any', 'cardio', 1.60, 0, 0],
        ['Yoga', 'cardio', 'any', 'cardio', 0.80, 0, 0],
        ['Stretching / Mobility', 'cardio', 'any', 'cardio', 0.40, 0, 0],
        ['Other Cardio', 'cardio', 'any', 'cardio', 1.20, 0, 0],
        // Added in v1.1: more gym, outdoor and sport activities
        ['Incline Machine Press', 'chest', 'machine', 'weight', 0.60, 0, 0],
        ['Smith Machine Bench Press', 'chest', 'machine', 'weight', 0.80, 0, 0],
        ['Low Cable Fly', 'chest', 'cable', 'weight', 0.75, 0, 0],
        ['High Cable Fly', 'chest', 'cable', 'weight', 0.75, 0, 0],
        ['Svend Press', 'chest', 'plate', 'weight', 1.00, 0, 0],
        ['Floor Press', 'chest', 'barbell', 'weight', 0.90, 0, 0],
        ['Dumbbell Pullover', 'chest', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Dumbbell Squeeze Press', 'chest', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Close-Grip Push-Up', 'chest', 'bodyweight', 'bodyweight', 1.00, 0.65, 0],
        ['Decline Push-Up', 'chest', 'bodyweight', 'bodyweight', 1.00, 0.70, 0],
        ['Incline Push-Up', 'chest', 'bodyweight', 'bodyweight', 1.00, 0.50, 0],
        ['Diamond Push-Up', 'chest', 'bodyweight', 'bodyweight', 1.00, 0.65, 0],
        ['Wide Push-Up', 'chest', 'bodyweight', 'bodyweight', 1.00, 0.65, 0],
        ['Archer Push-Up', 'chest', 'bodyweight', 'bodyweight', 1.00, 0.70, 0],
        ['Clap Push-Up', 'chest', 'bodyweight', 'bodyweight', 1.00, 0.65, 0],
        ['Ring Push-Up', 'chest', 'bodyweight', 'bodyweight', 1.00, 0.70, 0],
        ['Knee Push-Up', 'chest', 'bodyweight', 'bodyweight', 1.00, 0.45, 0],
        ['Single-Arm Cable Row', 'back', 'cable', 'weight', 0.70, 0, 0],
        ['Chest-Supported Row', 'back', 'dumbbell', 'weight', 0.90, 0, 0],
        ['Machine Row', 'back', 'machine', 'weight', 0.60, 0, 0],
        ['Meadows Row', 'back', 'barbell', 'weight', 0.90, 0, 0],
        ['Seal Row', 'back', 'barbell', 'weight', 0.85, 0, 0],
        ['Wide-Grip Seated Row', 'back', 'cable', 'weight', 0.65, 0, 0],
        ['Inverted Row', 'back', 'bodyweight', 'bodyweight', 1.00, 0.60, 0],
        ['Neutral-Grip Pull-Up', 'back', 'bodyweight', 'bodyweight', 1.00, 1.00, 0],
        ['Wide-Grip Pull-Up', 'back', 'bodyweight', 'bodyweight', 1.00, 1.00, 0],
        ['Muscle-Up', 'back', 'bodyweight', 'bodyweight', 1.00, 1.00, 0],
        ['Good Morning', 'back', 'barbell', 'weight', 0.90, 0, 0],
        ['Reverse Hyperextension', 'back', 'machine', 'weight', 0.60, 0, 0],
        ['Trap Bar Deadlift', 'back', 'barbell', 'weight', 1.10, 0, 0],
        ['Deficit Deadlift', 'back', 'barbell', 'weight', 1.10, 0, 0],
        ['Dumbbell Deadlift', 'back', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Reverse Pec Deck', 'back', 'machine', 'weight', 0.60, 0, 0],
        ['Superman', 'back', 'bodyweight', 'bodyweight', 0.80, 0.30, 0],
        ['Dead Hang', 'back', 'bodyweight', 'duration', 2.50, 0, 0],
        ['Cable Shrug', 'back', 'cable', 'weight', 0.70, 0, 0],
        ['Landmine Press', 'shoulders', 'barbell', 'weight', 0.90, 0, 0],
        ['Seated Barbell Press', 'shoulders', 'barbell', 'weight', 0.90, 0, 0],
        ['Z Press', 'shoulders', 'barbell', 'weight', 0.90, 0, 0],
        ['Dumbbell Y-Raise', 'shoulders', 'dumbbell', 'weight', 1.30, 0, 0],
        ['Cuban Press', 'shoulders', 'dumbbell', 'weight', 1.30, 0, 0],
        ['Machine Lateral Raise', 'shoulders', 'machine', 'weight', 0.80, 0, 0],
        ['Pike Push-Up', 'shoulders', 'bodyweight', 'bodyweight', 1.00, 0.65, 0],
        ['Cable Rear Delt Fly', 'shoulders', 'cable', 'weight', 1.00, 0, 0],
        ['Barbell Front Raise', 'shoulders', 'barbell', 'weight', 0.90, 0, 0],
        ['Plate Front Raise', 'shoulders', 'plate', 'weight', 1.00, 0, 0],
        ['Standing Dumbbell Shoulder Press', 'shoulders', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Kettlebell Press', 'shoulders', 'kettlebell', 'weight', 1.00, 0, 0],
        ['Wall Walk', 'shoulders', 'bodyweight', 'bodyweight', 1.00, 0.80, 0],
        ['Reverse Curl', 'biceps', 'barbell', 'weight', 0.90, 0, 0],
        ['Zottman Curl', 'biceps', 'dumbbell', 'weight', 1.10, 0, 0],
        ['Spider Curl', 'biceps', 'dumbbell', 'weight', 1.10, 0, 0],
        ['Drag Curl', 'biceps', 'barbell', 'weight', 0.90, 0, 0],
        ['Machine Curl', 'biceps', 'machine', 'weight', 0.60, 0, 0],
        ['Bayesian Cable Curl', 'biceps', 'cable', 'weight', 0.75, 0, 0],
        ['Wrist Curl', 'biceps', 'dumbbell', 'weight', 1.20, 0, 0],
        ['Reverse Wrist Curl', 'biceps', 'dumbbell', 'weight', 1.20, 0, 0],
        ['Cable Hammer Curl', 'biceps', 'cable', 'weight', 0.75, 0, 0],
        ['Chin-Up Hold', 'biceps', 'bodyweight', 'duration', 2.50, 0, 0],
        ['JM Press', 'triceps', 'barbell', 'weight', 0.85, 0, 0],
        ['Cable Overhead Triceps Extension', 'triceps', 'cable', 'weight', 0.75, 0, 0],
        ['Single-Arm Triceps Pushdown', 'triceps', 'cable', 'weight', 0.80, 0, 0],
        ['Reverse-Grip Triceps Pushdown', 'triceps', 'cable', 'weight', 0.75, 0, 0],
        ['Dumbbell Skull Crusher', 'triceps', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Machine Dip', 'triceps', 'machine', 'weight', 0.60, 0, 0],
        ['Tate Press', 'triceps', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Triceps Push-Up', 'triceps', 'bodyweight', 'bodyweight', 1.00, 0.65, 0],
        ['Bodyweight Triceps Extension', 'triceps', 'bodyweight', 'bodyweight', 0.90, 0.50, 0],
        ['Ring Dip', 'triceps', 'bodyweight', 'bodyweight', 1.00, 1.00, 0],
        ['Sissy Squat', 'legs', 'bodyweight', 'bodyweight', 0.90, 0.55, 0],
        ['Pistol Squat', 'legs', 'bodyweight', 'bodyweight', 1.00, 0.90, 0],
        ['Jump Squat', 'legs', 'bodyweight', 'bodyweight', 0.90, 0.50, 0],
        ['Jump Lunge', 'legs', 'bodyweight', 'bodyweight', 0.90, 0.50, 0],
        ['Reverse Lunge', 'legs', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Lateral Lunge', 'legs', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Curtsy Lunge', 'legs', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Cossack Squat', 'legs', 'bodyweight', 'bodyweight', 0.90, 0.55, 0],
        ['Zercher Squat', 'legs', 'barbell', 'weight', 1.00, 0, 0],
        ['Overhead Squat', 'legs', 'barbell', 'weight', 0.90, 0, 0],
        ['Safety Bar Squat', 'legs', 'barbell', 'weight', 1.00, 0, 0],
        ['Belt Squat', 'legs', 'machine', 'weight', 0.80, 0, 0],
        ['Pendulum Squat', 'legs', 'machine', 'weight', 0.80, 0, 0],
        ['Smith Machine Split Squat', 'legs', 'machine', 'weight', 0.85, 0, 0],
        ['Single-Leg Press', 'legs', 'machine', 'weight', 0.55, 0, 0],
        ['Single-Leg Romanian Deadlift', 'legs', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Dumbbell Romanian Deadlift', 'legs', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Nordic Hamstring Curl', 'legs', 'bodyweight', 'bodyweight', 0.90, 0.60, 0],
        ['Glute-Ham Raise', 'legs', 'bodyweight', 'bodyweight', 0.90, 0.55, 0],
        ['Cable Pull-Through', 'legs', 'cable', 'weight', 0.75, 0, 0],
        ['Cable Glute Kickback', 'legs', 'cable', 'weight', 0.65, 0, 0],
        ['Hip Abduction Machine', 'legs', 'machine', 'weight', 0.50, 0, 0],
        ['Hip Adduction Machine', 'legs', 'machine', 'weight', 0.50, 0, 0],
        ['Donkey Kick', 'legs', 'bodyweight', 'bodyweight', 0.70, 0.30, 0],
        ['Fire Hydrant', 'legs', 'bodyweight', 'bodyweight', 0.60, 0.25, 0],
        ['Single-Leg Hip Thrust', 'legs', 'bodyweight', 'bodyweight', 0.80, 0.50, 0],
        ['Barbell Glute Bridge', 'legs', 'barbell', 'weight', 0.80, 0, 0],
        ['Leg Press Calf Raise', 'legs', 'machine', 'weight', 0.60, 0, 0],
        ['Donkey Calf Raise', 'legs', 'machine', 'weight', 0.70, 0, 0],
        ['Single-Leg Calf Raise', 'legs', 'bodyweight', 'bodyweight', 0.80, 0.50, 0],
        ['Tibialis Raise', 'legs', 'bodyweight', 'bodyweight', 0.60, 0.30, 0],
        ['High Box Step-Up', 'legs', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Kettlebell Goblet Squat', 'legs', 'kettlebell', 'weight', 1.00, 0, 0],
        ['Heel-Elevated Squat', 'legs', 'barbell', 'weight', 1.00, 0, 0],
        ['Frog Pump', 'legs', 'bodyweight', 'bodyweight', 0.70, 0.35, 0],
        ['Banded Lateral Walk', 'legs', 'band', 'duration', 2.00, 0, 0],
        ['Dead Bug', 'core', 'bodyweight', 'bodyweight', 0.70, 0.25, 0],
        ['Hollow Hold', 'core', 'bodyweight', 'duration', 3.00, 0, 0],
        ['V-Up', 'core', 'bodyweight', 'bodyweight', 0.90, 0.35, 0],
        ['Toes-to-Bar', 'core', 'bodyweight', 'bodyweight', 1.00, 0.55, 0],
        ['Hanging Knee Raise', 'core', 'bodyweight', 'bodyweight', 0.90, 0.45, 0],
        ['Captain\'s Chair Leg Raise', 'core', 'bodyweight', 'bodyweight', 0.90, 0.40, 0],
        ['Lying Leg Raise', 'core', 'bodyweight', 'bodyweight', 0.90, 0.40, 0],
        ['Flutter Kicks', 'core', 'bodyweight', 'bodyweight', 0.70, 0.20, 0],
        ['Decline Sit-Up', 'core', 'bodyweight', 'bodyweight', 0.90, 0.40, 0],
        ['Pallof Press', 'core', 'cable', 'weight', 0.70, 0, 0],
        ['Cable Woodchopper', 'core', 'cable', 'weight', 0.75, 0, 0],
        ['Dumbbell Side Bend', 'core', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Landmine Twist', 'core', 'barbell', 'weight', 1.00, 0, 0],
        ['Ab Machine Crunch', 'core', 'machine', 'weight', 0.60, 0, 0],
        ['Reverse Crunch', 'core', 'bodyweight', 'bodyweight', 0.80, 0.25, 0],
        ['Dragon Flag', 'core', 'bodyweight', 'bodyweight', 1.00, 0.70, 0],
        ['L-Sit', 'core', 'bodyweight', 'duration', 4.00, 0, 0],
        ['Bear Crawl', 'core', 'bodyweight', 'duration', 4.00, 0, 0],
        ['Copenhagen Plank', 'core', 'bodyweight', 'duration', 3.50, 0, 0],
        ['Reverse Plank', 'core', 'bodyweight', 'duration', 3.00, 0, 0],
        ['Suitcase Carry', 'core', 'dumbbell', 'weight', 0.80, 0, 0],
        ['Bird Dog', 'core', 'bodyweight', 'bodyweight', 0.60, 0.25, 0],
        ['Plank Shoulder Tap', 'core', 'bodyweight', 'bodyweight', 0.70, 0.30, 0],
        ['Weighted Sit-Up', 'core', 'plate', 'weight', 0.80, 0, 0],
        ['Clean and Jerk', 'fullbody', 'barbell', 'weight', 0.95, 0, 0],
        ['Hang Clean', 'fullbody', 'barbell', 'weight', 0.95, 0, 0],
        ['Hang Snatch', 'fullbody', 'barbell', 'weight', 0.90, 0, 0],
        ['Clean Pull', 'fullbody', 'barbell', 'weight', 1.00, 0, 0],
        ['Dumbbell Snatch', 'fullbody', 'dumbbell', 'weight', 1.10, 0, 0],
        ['Dumbbell Clean and Press', 'fullbody', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Dumbbell Thruster', 'fullbody', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Kettlebell Clean', 'fullbody', 'kettlebell', 'weight', 1.00, 0, 0],
        ['Kettlebell Snatch', 'fullbody', 'kettlebell', 'weight', 1.00, 0, 0],
        ['Medicine Ball Slam', 'fullbody', 'medicine ball', 'weight', 1.00, 0, 0],
        ['Wall Ball Shot', 'fullbody', 'medicine ball', 'weight', 0.80, 0, 0],
        ['Sandbag Carry', 'fullbody', 'sandbag', 'weight', 0.80, 0, 0],
        ['Sandbag Clean', 'fullbody', 'sandbag', 'weight', 0.90, 0, 0],
        ['Tire Flip', 'fullbody', 'tire', 'weight', 0.90, 0, 0],
        ['Sled Push', 'fullbody', 'sled', 'weight', 0.60, 0, 0],
        ['Sled Pull', 'fullbody', 'sled', 'weight', 0.60, 0, 0],
        ['Rope Climb', 'fullbody', 'rope', 'bodyweight', 1.00, 1.00, 0],
        ['Jumping Jack', 'fullbody', 'bodyweight', 'bodyweight', 0.60, 0.25, 0],
        ['Star Jump', 'fullbody', 'bodyweight', 'bodyweight', 0.70, 0.40, 0],
        ['Devil Press', 'fullbody', 'dumbbell', 'weight', 1.00, 0, 0],
        ['Burpee Box Jump', 'fullbody', 'bodyweight', 'bodyweight', 1.00, 0.75, 0],
        ['Yoke Carry', 'fullbody', 'yoke', 'weight', 0.80, 0, 0],
        ['Jogging', 'cardio', 'outdoor', 'cardio', 1.30, 0, 6.00],
        ['Trail Running', 'cardio', 'outdoor', 'cardio', 1.70, 0, 7.00],
        ['Sprint Intervals', 'cardio', 'outdoor', 'cardio', 2.20, 0, 0],
        ['Power Walking', 'cardio', 'outdoor', 'cardio', 1.00, 0, 3.50],
        ['Nordic Walking', 'cardio', 'outdoor', 'cardio', 1.00, 0, 3.50],
        ['Dog Walk', 'cardio', 'outdoor', 'cardio', 0.80, 0, 3.00],
        ['Stroller Walk', 'cardio', 'outdoor', 'cardio', 0.90, 0, 3.20],
        ['Treadmill Walk', 'cardio', 'machine', 'cardio', 0.80, 0, 3.00],
        ['Mountain Biking', 'cardio', 'outdoor', 'cardio', 1.30, 0, 3.00],
        ['Spin Class', 'cardio', 'machine', 'cardio', 1.50, 0, 0],
        ['E-Bike Ride', 'cardio', 'outdoor', 'cardio', 0.70, 0, 1.50],
        ['Assault Bike', 'cardio', 'machine', 'cardio', 2.00, 0, 0],
        ['Ski Erg', 'cardio', 'machine', 'cardio', 1.80, 0, 6.00],
        ['Outdoor Rowing', 'cardio', 'outdoor', 'cardio', 1.60, 0, 6.00],
        ['Open Water Swimming', 'cardio', 'outdoor', 'cardio', 2.00, 0, 25.00],
        ['Aqua Aerobics', 'cardio', 'pool', 'cardio', 1.20, 0, 0],
        ['Kayaking', 'cardio', 'outdoor', 'cardio', 1.20, 0, 4.00],
        ['Canoeing', 'cardio', 'outdoor', 'cardio', 1.10, 0, 4.00],
        ['Stand-Up Paddleboarding', 'cardio', 'outdoor', 'cardio', 1.00, 0, 3.00],
        ['Surfing', 'cardio', 'outdoor', 'cardio', 1.40, 0, 0],
        ['Downhill Skiing', 'cardio', 'outdoor', 'cardio', 1.20, 0, 0],
        ['Snowboarding', 'cardio', 'outdoor', 'cardio', 1.20, 0, 0],
        ['Snowshoeing', 'cardio', 'outdoor', 'cardio', 1.40, 0, 4.00],
        ['Ski Touring', 'cardio', 'outdoor', 'cardio', 1.60, 0, 5.00],
        ['Snow Shoveling', 'cardio', 'outdoor', 'cardio', 1.20, 0, 0],
        ['Roller Skating', 'cardio', 'outdoor', 'cardio', 1.20, 0, 3.00],
        ['Inline Skating', 'cardio', 'outdoor', 'cardio', 1.20, 0, 3.00],
        ['Skateboarding', 'cardio', 'outdoor', 'cardio', 1.00, 0, 0],
        ['Bouldering', 'cardio', 'any', 'cardio', 1.60, 0, 0],
        ['Rock Climbing', 'cardio', 'outdoor', 'cardio', 1.60, 0, 0],
        ['Stadium Stairs', 'cardio', 'outdoor', 'cardio', 1.80, 0, 0],
        ['Stair Walking', 'cardio', 'any', 'cardio', 1.30, 0, 0],
        ['Rucking', 'cardio', 'outdoor', 'cardio', 1.30, 0, 4.00],
        ['Orienteering', 'cardio', 'outdoor', 'cardio', 1.40, 0, 5.00],
        ['Horse Riding', 'cardio', 'outdoor', 'cardio', 0.90, 0, 0],
        ['Golf (Walking)', 'cardio', 'outdoor', 'cardio', 0.70, 0, 3.00],
        ['Ultimate Frisbee', 'cardio', 'outdoor', 'cardio', 1.30, 0, 0],
        ['Soccer / Football', 'cardio', 'any', 'cardio', 1.60, 0, 0],
        ['Basketball', 'cardio', 'any', 'cardio', 1.60, 0, 0],
        ['Volleyball', 'cardio', 'any', 'cardio', 1.10, 0, 0],
        ['Tennis', 'cardio', 'any', 'cardio', 1.50, 0, 0],
        ['Padel', 'cardio', 'any', 'cardio', 1.40, 0, 0],
        ['Pickleball', 'cardio', 'any', 'cardio', 1.20, 0, 0],
        ['Badminton', 'cardio', 'any', 'cardio', 1.30, 0, 0],
        ['Squash', 'cardio', 'any', 'cardio', 1.80, 0, 0],
        ['Table Tennis', 'cardio', 'any', 'cardio', 0.90, 0, 0],
        ['Handball', 'cardio', 'any', 'cardio', 1.60, 0, 0],
        ['Floorball / Bandy', 'cardio', 'any', 'cardio', 1.50, 0, 0],
        ['Ice Hockey', 'cardio', 'any', 'cardio', 1.70, 0, 0],
        ['Baseball / Softball', 'cardio', 'any', 'cardio', 0.80, 0, 0],
        ['Rugby', 'cardio', 'any', 'cardio', 1.80, 0, 0],
        ['American Football', 'cardio', 'any', 'cardio', 1.50, 0, 0],
        ['Boxing', 'cardio', 'any', 'cardio', 1.90, 0, 0],
        ['Kickboxing', 'cardio', 'any', 'cardio', 1.90, 0, 0],
        ['Muay Thai', 'cardio', 'any', 'cardio', 1.90, 0, 0],
        ['Brazilian Jiu-Jitsu', 'cardio', 'any', 'cardio', 1.70, 0, 0],
        ['Judo', 'cardio', 'any', 'cardio', 1.60, 0, 0],
        ['Wrestling', 'cardio', 'any', 'cardio', 1.80, 0, 0],
        ['Karate / Taekwondo', 'cardio', 'any', 'cardio', 1.40, 0, 0],
        ['Dance', 'cardio', 'any', 'cardio', 1.20, 0, 0],
        ['Zumba / Aerobics', 'cardio', 'any', 'cardio', 1.30, 0, 0],
        ['Pilates', 'cardio', 'any', 'cardio', 0.90, 0, 0],
        ['Tai Chi', 'cardio', 'any', 'cardio', 0.60, 0, 0],
        ['CrossFit WOD', 'cardio', 'any', 'cardio', 2.00, 0, 0],
        ['Circuit Training', 'cardio', 'any', 'cardio', 1.80, 0, 0],
        ['Bootcamp', 'cardio', 'any', 'cardio', 1.80, 0, 0],
        ['Calisthenics Session', 'cardio', 'bodyweight', 'cardio', 1.50, 0, 0],
        ['Foam Rolling', 'cardio', 'any', 'cardio', 0.30, 0, 0],
        ['Playing With Kids', 'cardio', 'any', 'cardio', 0.80, 0, 0],
        ['Yard Work / Gardening', 'cardio', 'outdoor', 'cardio', 0.80, 0, 0],
        ['Hauling Firewood', 'cardio', 'outdoor', 'cardio', 1.00, 0, 0],
    ];
}

/**
 * Strength-exercise coefficient = movement class x equipment factor (our own scheme, not a copied table).
 *  Movement class: how much muscle and how many joints do the work, and how much systemic demand there is.
 *    1.15 barbell squat and deadlift; 1.00 big compound (bench/overhead press, deadlift, bench/overhead press, barbell row, pull-up, dip, lunge, clean, carry)
 *    0.80 other compound (incline/dumbbell presses, push-ups, machine/cable rows, pulldowns, leg press, hip hinge machines)
 *    0.60 isolation / accessory (curls, extensions, raises, flyes, calves, crunches, shrugs)
 *  Equipment factor: free weights 1.00, cable 0.90, Smith 0.90, machine 0.80 (guided path = less stabilising work).
 * Hand-held dumbbells are logged per hand and counted twice in set_points().
 */
function derive_coef(string $name, string $equip): float
{
    $n = strtolower($name);
    $has = static function (array $w) use ($n): bool {
        foreach ($w as $x) {
            if (strpos($n, $x) !== false) {
                return true;
            }
        }
        return false;
    };
    if ($has(['curl', 'extension', 'raise', 'fly', 'flye', 'kickback', 'calf', 'crunch', 'shrug', 'wrist', 'abduction', 'adduction',
              'face pull', 'skull', 'pushdown', 'push-down', 'pressdown', 'twist', 'woodchop', 'rotation', 'rear delt', 'pec deck',
              'sit-up', 'situp', 'leg raise', 'hanging', 'plank', 'ab wheel', 'rollout', 'crossover', 'pull-over', 'drag curl'])) {
        $cls = 0.60;
    } elseif ($has(['squat', 'deadlift', 'clean', 'snatch', 'jerk', 'thruster', 'hip thrust', 'good morning', 'lunge', 'step-up',
                    'get-up', 'farmer', 'carry', 'swing', 'muscle-up', 'pull-up', 'pullup', 'chin-up', 'chinup', 'dip',
                    'bench press', 'chest press', 'overhead press', 'military press', 'shoulder press', 'push press', 'barbell row', 'bent-over',
                    'bent over', 'pendlay', 't-bar', 'yates', 'dumbbell row', 'one-arm row', 'rack pull', 'seated barbell press',
                    'z press', 'meadows', 'seal row'])
              && !$has(['cable', 'incline', 'decline', 'sissy', 'hack', 'bench dip', 'assisted'])) {
        $cls = 1.00;
    } else {
        $cls = 0.80;
    }
    if ($cls === 1.00 && $equip === 'barbell' && $has(['back squat', 'front squat', 'deadlift']) && !$has(['romanian', 'stiff', 'single-leg', 'dumbbell', 'jump'])) {
        $cls = 1.15;   // the heaviest, most demanding lifts
    }
    $eq = ['barbell' => 1.00, 'dumbbell' => 1.00, 'kettlebell' => 1.00, 'bodyweight' => 1.00, 'cable' => 0.90, 'machine' => 0.80][$equip] ?? 0.90;
    if (strpos($n, 'smith') !== false) {
        $eq = 0.90;
    }
    // Sled-style leg machines carry far more plate weight than a free squat for the same effort; calf machines too.
    if ($has(['leg press', 'hack squat', 'belt squat'])) {
        $eq *= 0.60;
    } elseif ($has(['calf'])) {
        $eq *= 0.75;
    }
    return round($cls * $eq, 2);
}

function seed_exercises(PDO $pdo): int
{
    $st = $pdo->prepare(
        'INSERT IGNORE INTO exercises (slug, name, muscle, equipment, kind, coef, bw_pct, dist_coef) VALUES (?,?,?,?,?,?,?,?)'
    );
    $upd = $pdo->prepare('UPDATE exercises SET muscle = ?, equipment = ?, kind = ?, coef = ?, bw_pct = ?, dist_coef = ? WHERE slug = ? AND locked = 0');
    $tag = $pdo->prepare('UPDATE exercises SET subs = ?, per_side = ? WHERE slug = ?');
    $n = 0;
    foreach (exercise_seed() as [$name, $muscle, $equip, $kind, $coef, $bw, $dist]) {
        if ($kind === 'weight' || $kind === 'bodyweight') {
            $coef = derive_coef($name, $equip);
        }
        $st->execute([slugify($name), $name, $muscle, $equip, $kind, $coef, $bw, $dist]);
        $n += $st->rowCount();
        $upd->execute([$muscle, $equip, $kind, $coef, $bw, $dist, slugify($name)]);   // keep built-ins in step with new releases
        $tag->execute([implode(',', exercise_subs($name, $muscle, $equip)), exercise_side_mode($name, $equip), slugify($name)]);
    }
    return $n;
}
