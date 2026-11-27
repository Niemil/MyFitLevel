# MyFitLevel workout logger: specification for the SnakeDiet AI

Source code is public (AGPL-3.0): https://github.com/Niemil/MyFitLevel (folder `web/`). Paths below are inside `web/`.
Stack: PHP 8, MySQL via PDO, plain JS (no framework), CSP `script-src 'self'` (NO inline scripts, put JS in assets files).

## a) Exercise library
Files: `myfitlevel-exercises.json` / `.csv` (339 active exercises: 170 weight, 66 bodyweight, 91 cardio, 12 duration).
Columns: slug, name, muscle, equipment, kind, coef, bw_pct, dist_coef, per_side, subs.
- muscle: chest, back, shoulders, biceps, triceps, legs, core, fullbody, cardio. subs = comma list of finer regions for a body map.
- equipment: barbell, dumbbell, kettlebell, cable, machine, bodyweight, band, plate, rope, sled, any, outdoor, pool, ... (see file)
- kind decides what a set contains:
  - weight: weight_kg + reps
  - bodyweight: reps (+ optional added weight_kg). bw_pct = share of body weight that is lifted (push-up 0.65, pull-up 1.00).
  - cardio: duration_sec (+ optional distance_m). dist_coef > 0 means distance counts.
  - duration: duration_sec only (plank, wall sit).
- per_side = 1 (or equipment dumbbell): weight is entered PER HAND, work counts both hands (x2), reps count once.
- Seeded by `exercise_seed()` in `includes/seed.php`, INSERT IGNORE by slug so every update adds new ones and never overwrites admin edits (column `locked`).
- Search (logger.js picker): client-side filter over the full list by name + equipment, plus muscle chips, body map, "My lists" and routines. Users cannot create their own exercises today (admin only: Admin > Exercises). Abbreviations work in the API quicklog (db, bb, kb, ohp, bp, rdl).

## b) UI flow (screen by screen)
Page `log` (`includes/pages/log.php` + `public/assets/logger.js`, ~800 lines). One page, no steps:
1. Empty state: three-line hint, a big "+ Add exercise (N)" button, a "Start timer" control (workout timer starts when pressed or when the first set is ticked; shows 00:00 counting up; or choose a past date/time to log afterwards). Fields: title (optional), notes, gym/place picker.
2. "+ Add exercise" opens a bottom sheet (picker): search box, muscle chips (All, chest, back, shoulders, biceps, triceps, legs, core, fullbody, cardio) plus an optional body map for finer regions, a "My lists" source and a "Routines" source. Tap = add and close (or keep open to add several).
3. Each exercise is a card: name, "Last time: 80 kg x 8 (3 sets)" hint, then a row per set: weight field, reps field (or minutes / km for cardio, seconds for holds), tick box (k) to mark the set done, warm-up toggle (wu). Buttons + and - next to weight and reps step by the exercise's own step (user can choose step chips 1, 2.5, 5, custom; saved per exercise in `user_exercise_prefs`). "Add set" copies the previous set. A new exercise is prefilled from last session (setting: first set only / all sets / none).
4. Autosave: every change is pushed (debounced 400 ms, max 6 s) as JSON to POST `draft` (table `workout_drafts`, one row per user) and also kept in localStorage, so a closed tab or dead battery loses nothing. The draft is restored next time. Clearing = finishing or discarding.
5. A running estimate of points ("est. pts") is shown from the client-side copy of the formula.
6. Finish: "Save workout" posts a normal form: `ex[i][id]`, `ex[i][note]`, `ex[i][sets][j][w|r|t|d|wu|k]`, `title`, `notes`, `performed_at`, `duration` (seconds, from the timer), `gym_id`, plus CSRF and a nonce (double-submit guard). Unticked sets (k=0) are ignored. Then a short completion animation, then a results screen (points, level up, quests, achievements, PR list) from `$_SESSION['complete']`.
7. Edit: `log?edit=ID` loads the saved workout into the same logger; saving replaces all sets (same workout id, points recomputed, PR flags re-derived for later workouts). Delete: button on the workout page; points, PRs and quests are recalculated.
8. Routines (templates): "Save as routine" from the logger stores the exercise list as JSON in `routines.payload`; a weekly schedule (`routine_schedule`, dow 1-7) suggests today's routine; routines can be started from the picker or the home screen. No rest timer exists in the logger today (routine rows keep a rest value, unused by the UI).
9. PR marking: on save, the best set per exercise is compared with the previous best score. A set that beats it is `is_pr = 1`, earns +40 points and shows a PR badge. Warm-up sets never count as PR. Absurd weights are refused (`staff_check_set`), suspicious ones are flagged privately.

## c) Data model
```
exercises(id, slug UNIQUE, name, muscle, equipment, kind ENUM('weight','bodyweight','cardio','duration'),
          coef DECIMAL(6,3), bw_pct DECIMAL(3,2), dist_coef DECIMAL(6,2), active, subs, per_side, locked)
workouts(id, user_id, performed_at DATETIME (member wall-clock), title, notes, points, volume_kg, set_count,
         unit ENUM('kg','lb'), gym_id, duration_sec, created_at)
workout_sets(id, workout_id FK cascade, user_id, exercise_id FK, weight_kg DECIMAL(7,2) NULL, reps SMALLINT NULL,
             duration_sec INT NULL, distance_m INT NULL, score DECIMAL(9,2), points INT, is_pr TINYINT, is_warmup TINYINT)
workout_ex_notes(workout_id, exercise_id, note)
workout_drafts(user_id PK, payload MEDIUMTEXT JSON, updated_at)
user_exercise_prefs(user_id, exercise_id, step, unit)   -- weight step per exercise
routines(id, user_id, name, payload TEXT JSON, created_at); routine_schedule(user_id, dow, routine_id)
inbound_workouts(user_id, ext_id UNIQUE per user, workout_id, source, minutes, kcal)  -- workouts received from other sites
```
Weights are ALWAYS stored in kg; `workouts.unit` only remembers what the user typed. PR is not a table: `workout_sets.is_pr` + `score` (estimated 1RM) and `MAX(score)` per user and exercise.

## d) Points and levels
```php
// set_points(): returns [points, score]
weight:     load = kg * (per_side ? 2 : 1); score = load * (1 + min(reps,12)/30)      // Epley e1RM
bodyweight: load = bodyweight_kg * bw_pct + added_kg; score = load * (1 + min(reps,12)/30)
points (weight/bodyweight) = coef * score ** 1.3 * 0.2085          // LOAD_EXP 1.3, POINTS_SCALE 0.2085; 0 if reps = 0
cardio:   with distance and dist_coef: ratio = clamp((km/h)/ref_kmh, 0.4, 2.5)
          points = dist_coef * 6.6 * km**1.2 * ratio**1.3      // CARDIO_SCALE 6.6
          time only: points = coef * 6.6 * minutes
duration: points = coef * 3.0 * minutes                           // DURATION_SCALE 3.0
every set: max(1, round(points)); PR set: + 40; warm-up sets pay the same but never PR
```
`coef` for weight exercises comes from `derive_coef(name, equipment)` in `includes/seed.php`: class factor (isolation 0.60, default 0.80, big compound 1.00, barbell squat/deadlift 1.15) times equipment factor (barbell/dumbbell/kettlebell/bodyweight 1.00, cable 0.90, machine 0.80, smith 0.90; leg press/hack squat x0.60, calf machine x0.75).
Reference speeds for cardio (km/h) in `cardio_ref_kmh()`: run 9.7, jog 8.5, walk 5.0, cycling 20, rowing 12, swim 2.0, elliptical 8, default 6.
Levels: total points -> level by log-interpolation between anchors:
`L1 0, L2 150, L10 3500, L20 10000, L30 18500, L40 30000, L50 45000, L60 66000, L70 94000, L80 130000, L90 175000, L100 230000` (`level_base()` / `level_from_xp()` in `includes/xp.php`). Tuned for 3.5 workouts a week plus 6000 steps a day. Steps give 1 point per 80 steps (setting).
Imported workouts from other sites become one timed cardio-type row (coef 0.9 for generic) unless sets can be read.

## e) Code to read (all public)
- `includes/pages/log.php`: server side of the logger (form handling, draft restore, edit mode).
- `public/assets/logger.js`: whole client logger (picker, set rows, steppers, hints, autosave, submit).
- `includes/pages/draft.php`: autosave endpoint (CSRF via header or `_csrf`).
- `includes/xp.php`: `save_workout()` (validation, points, PR, transaction, recalculation), `set_points()`, levels.
- `includes/seed.php`: exercise list + `derive_coef()`.
- `includes/webhook.php`: what we send to SnakeDiet and how we receive from it.
When SnakeDiet's logger sends a finished workout, keep using the format already agreed: id `sd-<user>-<workout>`, sets[{exercise,reps|seconds,kg}], minutes, start, source, event. For kcal: SnakeDiet calculates it, we never send it.
