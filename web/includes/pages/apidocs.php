<?php
declare(strict_types=1);

$base = site_url('api/v1');
/** Keep this list in step with includes/pages/api.php whenever an endpoint is added or changed. */
$endpoints = [
    ['GET', '/me', 'Who the key belongs to: username, display name, level, points, unit.', null, '{"ok":true,"user":{"username":"emil","level":12,"xp":14320,"unit":"kg"}}'],
    ['GET', '/exercises', 'Every active exercise with its slug, muscle, equipment and kind. Use the slug when logging.', null, '{"ok":true,"exercises":[{"slug":"back-squat","name":"Back Squat","muscle":"legs","equipment":"barbell","kind":"weight"}]}'],
    ['GET', '/workouts', 'Your workouts with all sets, oldest first. Query: limit (1-200, default 50), after_id (for paging), since (date/time).', null, '{"ok":true,"workouts":[{"id":42,"performed_at":"2026-11-21 16:11:00","points":310,"sets":[{"exercise":"Back Squat","weight_kg":100,"reps":5,"points":62,"is_pr":true}]}],"next_after_id":42,"has_more":false}'],
    ['GET', '/widget', 'One flat object made for widgets (name, level, xp, level_xp, level_need, level_pct, streak, workouts, prs, followers, week_workouts, week_points, today_routine, today_label). SnakeDiet\'s /widget uses the same names (plus fasting fields), so the same widgets work on both sites. Also accepts ?key=YOUR_KEY in the address for tools that cannot send headers (read-only calls only).', null, '{"ok":true,"name":"Emil","level":24,"xp":41230,"level_xp":3120,"level_need":6800,"level_pct":46,"streak":6,"week_workouts":4,"week_points":1840,"today_routine":"Push day"}'],
    ['GET', '/stats', 'Your totals: workouts, sets, volume (kg), PRs, streak, cardio minutes, and sets by muscle group.', null, '{"ok":true,"stats":{"workouts":187,"sets":3120,"volume_kg":412300,"prs":58,"streak":6,"sets_by_group":{"chest":410,"back":520,"legs":600}}}'],
    ['GET', '/quests', 'Your quests with progress: steps done, and the task you are on with current value and target.', null, '{"ok":true,"quests":[{"code":"iron_path","name":"The Iron Path","steps_total":4,"steps_done":1,"finished":false,"current_step":{"task":"Do 5 bench press sets","progress":3,"target":5}}]}'],
    ['GET', '/badges', 'Achievements you have earned and how many exist.', null, '{"ok":true,"badges":[{"code":"first_workout","name":"First Rep","earned_at":"2026-11-21 16:11:00"}],"total_available":28}'],
    ['GET', '/routines', 'Your routines with every exercise (sets, target reps, weight in kg, rest, note) and your weekly schedule (day 1 = Monday).', null, '{"ok":true,"routines":[{"id":2,"name":"Push","exercises":[{"exercise":"Barbell Bench Press","sets":4,"reps":"6-8","weight_kg":80}]}],"schedule":{"1":2}}'],
    ['GET', '/today', 'Today\'s scheduled routine, or rest_day: true.', null, '{"ok":true,"rest_day":false,"routine":{"id":2,"name":"Push","exercise_count":5}}'],
    ['GET', '/leaders', 'Top 20 members by total points.', null, '{"ok":true,"leaders":[{"rank":1,"username":"emil","name":"Emil","level":24,"xp":41230}]}'],
    ['GET', '/summary', 'One sentence about how you are doing, for Siri, Shortcuts and notifications. Add ?format=text for plain text. Accepts ?key=.', null, '{"ok":true,"text":"Level 24 (46% to the next), 6-day streak, 4 workouts and 1840 points this week. Today: Push day.","level":24,"streak":6}'],
    ['GET', '/week', 'The last 7 days, one entry per day (workouts, points, sets). Accepts ?key=.', null, '{"ok":true,"days":[{"date":"2026-11-21","workouts":1,"points":310,"sets":18}]}'],
    ['GET', '/prs', 'Your best lift for each exercise: heaviest weight and estimated one-rep max. Accepts ?key=.', null, '{"ok":true,"prs":[{"exercise":"Back Squat","slug":"back-squat","heaviest_kg":140,"est_1rm_kg":163.3}]}'],
    ['GET', '/export.csv', 'Every set you have logged as a CSV file (Excel, Google Sheets =IMPORTDATA). Add inline=1 to show it instead of downloading. Accepts ?key=.', null, 'date,workout_id,title,exercise,muscle,weight_kg,reps,duration_sec,distance_m,points,is_pr,is_warmup'],
    ['GET', '/calendar.ics', 'Your workouts as a calendar feed you can subscribe to in Google, Apple or Outlook calendar. Accepts ?key=.', null, 'BEGIN:VCALENDAR ... BEGIN:VEVENT SUMMARY:💪 Legs (310 pts) ...'],
    ['GET', '/card/USERNAME.svg', 'Public stats card image (level, points, streak, workouts, PRs). No key needed. Use it in signatures, Discord or a website.', null, '(an SVG image)'],
    ['POST', '/quicklog', 'The easy way to log: exercises by name (db, bb, kb abbreviations work), no slugs. Weight in kg, or add "unit":"lb". Send one exercise or an items list. Also accepts duration_min and distance_km.', '{"title":"Push","items":[{"exercise":"db bench","sets":3,"reps":8,"weight":30},{"exercise":"overhead press","sets":3,"reps":6,"weight":40}]}', '{"ok":true,"workout_id":44,"points":210,"level_up":null,"logged":["Dumbbell Bench Press","Barbell Overhead Press"]}'],
    ['POST', '/posts', 'Post into your feed from another site or script (this is how SnakeDiet can show your fasts here). Text up to 900 characters; optional source (e.g. snakediet.org) and url are added to the post. 20 per 10 minutes.', '{"text":"Finished a 36 h fast 🐍","source":"snakediet.org","url":"https://snakediet.org/u/emil"}', '{"ok":true,"post_id":12}'],
    ['POST', '/workouts', 'Log a workout. Weights are always in kg here. Up to 120 posts per hour.', '{"performed_at":"2026-11-21 16:11:00","title":"Legs","exercises":[{"slug":"back-squat","sets":[{"weight_kg":100,"reps":5},{"weight_kg":60,"reps":8,"is_warmup":true}]},{"slug":"running","sets":[{"duration_sec":1800,"distance_m":5000}]}]}', '{"ok":true,"workout_id":43,"points":310,"level_up":null}'],
];
layout_header('API guide', '');
?>
<h1 class="pagetitle">API guide</h1>
<p class="muted">Read and log your own workouts from scripts, other sites or apps. Free to use. <?= e(site_name()) ?> version <?= e(APP_VERSION) ?>.</p>

<div class="card prose">
  <h3>1. Get a key</h3>
  <p>Log in, open <a href="<?= e(url('settings')) ?>">Settings</a> → API access and press Generate API key. Copy it right away; it is shown once. A new key replaces the old one, and you can revoke it any time.</p>
  <h3>2. Send it with every request</h3>
  <pre>Authorization: Bearer YOUR_KEY</pre>
  <p>Base URL: <code><?= e($base) ?></code>. Answers are JSON with <code>"ok": true</code> or <code>"ok": false</code> and an <code>error</code>. After too many wrong keys from one address you get 429.</p>
  <h3>Quick test</h3>
  <pre>curl -H "Authorization: Bearer YOUR_KEY" <?= e($base) ?>/me</pre>
</div>

<?php foreach ($endpoints as [$m, $path, $desc, $req, $res]): ?>
<div class="card prose">
  <h4><span class="chip on"><?= e($m) ?></span> <code><?= e($path) ?></code></h4>
  <p><?= e($desc) ?></p>
  <?php if ($req): ?><p class="small muted">Example body</p><pre><?= e($req) ?></pre><?php endif; ?>
  <p class="small muted">Example answer</p><pre><?= e($res) ?></pre>
</div>
<?php endforeach; ?>

<div class="card prose">
  <h3>Good to know</h3>
  <ul>
    <li>Points, levels and quests are calculated by the site, so a logged workout counts exactly like one logged in the app.</li>
    <li>No coding? Recipes for Sheets, calendars, Discord, Shortcuts and more: <a href="<?= e(url('connect')) ?>">Connect &amp; automate</a>. We can also post to your own webhook when you finish a workout.</li>
    <li>Ready-made desktop and phone widgets that use these calls: <a href="<?= e(url('widgets')) ?>">Widgets</a>.</li>
    <li>Your whole history as one file: Settings → Your data → Download my data.</li>
    <li>Building something with it? Tell us through <a href="<?= e(url('feedback')) ?>">feedback</a> and we will keep the API stable for you.</li>
  </ul>
</div>
<?php layout_footer(''); ?>
