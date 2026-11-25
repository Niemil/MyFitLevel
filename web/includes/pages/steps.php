<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
game_ensure();
define('GAME_CSS', true);
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
// The app is recognised by its user agent, by the cookie it sets, or by being an Android WebView.
$inApp = str_contains($ua, 'MyFitLevelApp') || (($_COOKIE['mflapp'] ?? '') === '1') || (bool)preg_match('/; wv\)/', $ua);

// The Android app links itself: it opens this page with ?connect=1, gets its own key and hands it to the app.
if ($inApp && isset($_GET['connect'])) {
    api_keys_ensure();
    q("DELETE FROM api_keys WHERE user_id = ? AND name = 'Android app'", [$uid]);
    $tok = api_key_create($uid, 'Android app');
    header('Location: mfl://key/' . $tok);
    exit;
}

if (is_post() && ($_POST['do'] ?? '') === 'iphone_key') {
    csrf_check();
    api_keys_ensure();
    if (!(int)val("SELECT COUNT(*) FROM api_keys WHERE user_id = ? AND name = 'iPhone' AND token_enc IS NOT NULL", [$uid])) {
        api_key_create($uid, 'iPhone');
    }
    redirect('steps#iphone');
}

if (is_post() && ($_POST['do'] ?? '') === 'prefs') {
    csrf_check();
    $home = ($_POST['home'] ?? '') === '1' ? 1 : 0;
    $goal = max(1000, min(50000, (int)preg_replace('/[^0-9]/', '', (string)($_POST['goal'] ?? '10000'))));
    q('UPDATE game_wallet SET steps_home = ?, steps_goal = ? WHERE user_id = ?', [$home, $goal, $uid]);
    flash('Saved.');
    redirect('steps');
}

if (is_post()) {
    csrf_check();
    $day = (string)($_POST['day'] ?? date('Y-m-d'));
    $steps = (int)preg_replace('/[^0-9]/', '', (string)($_POST['steps'] ?? '0'));
    $km = (float)str_replace(',', '.', (string)($_POST['km'] ?? '0'));
    if (!rate_ok('steps:' . $uid, 60, 3600)) {
        flash('Slow down a little.', 'err');
    } else {
        $res = game_steps_set($uid, $day, $steps, (int)round(max(0, $km) * 1000), 'manual');
        flash($res['ok'] ? 'Saved ' . number_format($steps) . ' steps for ' . $day . '. ' . ($res['game'] ?? '') : (string)$res['error'], $res['ok'] ? 'ok' : 'err');
    }
    redirect('steps');
}

$days = rows('SELECT * FROM daily_steps WHERE user_id = ? ORDER BY day DESC LIMIT 14', [$uid]);
$today = (int)val('SELECT COALESCE(steps,0) FROM daily_steps WHERE user_id = ? AND day = ?', [$uid, date('Y-m-d')]);
$week = (int)val('SELECT COALESCE(SUM(steps),0) FROM daily_steps WHERE user_id = ? AND day >= ?', [$uid, game_week_key()]);
$max = max(1, ...array_map(fn($d) => (int)$d['steps'], $days ?: [['steps' => 1]]));
$hasKey = !empty($u['api_token_hash']);
$site = rtrim((string)(defined('SITE_URL') ? SITE_URL : 'https://' . ($_SERVER['HTTP_HOST'] ?? 'myfitlevel.com')), '/');
$per = max(1, (int)game_cfg('steps_per_point'));
$cap = (int)game_cfg('steps_cap_day');
$stepsRule = $cap > 0 ? '1 point per ' . number_format($per) . ' steps, up to ' . number_format($cap) . ' steps a day, so at most ' . intdiv($cap, $per) . ' points' : 'currently off';
layout_header('Steps', 'me');
?>
<h1 class="pagetitle">👟 Steps</h1>
<p class="muted">Log your daily steps and distance. They count towards team quests like "Step up". Steps earn a few points (<?= e($stepsRule) ?>) and count for team quests. Training still pays far more, so lifting stays the main way up.</p>

<div class="gb-facts">
  <div><b><?= number_format($today) ?></b><span>steps today</span></div>
  <div><b><?= number_format($week) ?></b><span>this week</span></div>
</div>

<?php $wal = game_wallet($uid); ?>
<?php if ($inApp): $hasKeyApp = (bool)val("SELECT COUNT(*) FROM api_keys WHERE user_id = ? AND name = 'Android app'", [$uid]); ?>
<div class="card"><h3>📱 <?= $hasKeyApp ? 'Step sync on this phone' : 'Connect this phone' ?></h3>
  <p class="small">One tap links this app to your account (no key to copy) and then reads your steps from Health Connect. Steps give points. It is optional: you can also add steps by hand below, or skip them.</p>
  <div class="btnrow"><a class="btn" href="<?= e(url('steps') . '?connect=1') ?>"><?= $hasKeyApp ? 'Reconnect this phone' : 'Connect this phone' ?></a>
  <a class="btn ghost" href="mfl://sync">Sync now / Health Connect settings</a></div>
  <p class="small muted" style="margin-top:8px">New phone, reinstalled the app or reset keys? Tap Reconnect and allow Health Connect again.</p></div>
<?php endif; ?>

<div class="card">
  <h3>Log a day</h3>
  <form method="post"><?= csrf_field() ?>
    <label>Day <input type="date" name="day" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d', strtotime('-14 days'))) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
    <label>Steps <input name="steps" inputmode="numeric" placeholder="e.g. 8500" required></label>
    <label>Distance in km (optional) <input name="km" inputmode="decimal" placeholder="blank = estimated from steps"></label>
    <button class="btn" type="submit">Save</button>
    <p class="muted small">You can fill in the last 14 days. As the day goes on, just save your new total: saving again replaces the day. Phone sync only ever raises a day, never lowers it.</p>
  </form>
</div>

<div class="card">
  <h3>Home page</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="prefs">
    <label><input type="checkbox" name="home" value="1" <?= ($wal['steps_home'] === null ? $today > 0 || $days : (int)$wal['steps_home'] === 1) ? 'checked' : '' ?>> Show my steps on the home page</label>
    <label>Daily goal (steps) <input name="goal" inputmode="numeric" value="<?= (int)$wal['steps_goal'] ?>"></label>
    <button class="btn ghost" type="submit">Save</button></form>
</div>

<?php if ($days): ?>
<div class="card">
  <h3>Last days</h3>
  <?php foreach ($days as $d): ?>
    <div class="gb-row"><span class="small" style="width:5.5em"><?= e(date('D M j', strtotime($d['day']))) ?></span>
      <span class="grow"><span style="display:block;height:8px;border-radius:4px;background:#36a85c;width:<?= max(2, (int)round((int)$d['steps'] / $max * 100)) ?>%"></span></span>
      <b><?= number_format((int)$d['steps']) ?></b> <small class="muted"><?= number_format((int)$d['distance_m'] / 1000, 1) ?> km<?= $d['source'] === 'api' ? ' · auto' : '' ?></small></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
api_keys_ensure();
$ik = row("SELECT * FROM api_keys WHERE user_id = ? AND name = 'iPhone' AND token_enc IS NOT NULL ORDER BY id DESC LIMIT 1", [$uid]);
$ikey = $ik ? api_key_reveal($ik) : '';
$g = fn(string $n) => e(asset('guide/' . $n));
?>
<div class="card" id="iphone">
  <h3>🍎 iPhone: send steps with a Shortcut</h3>
  <p class="small">No app needed, takes about 3 minutes. Everything you need to type is below with a Copy button: on the iPhone, open this page, tap Copy, switch to Shortcuts and paste.</p>
  <video class="gvid" controls playsinline preload="metadata" poster="<?= $g('iphone-guide-poster.jpg') ?>" src="<?= $g('iphone-guide.mp4') ?>"></video>
  <p class="muted small">Video guide, about 1 minute. No sound: read the big text and watch where the finger taps.</p>
<?php if (!$ikey): ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="iphone_key"><button class="btn" type="submit">Create my iPhone key and show the guide</button></form>
<?php else: ?>
  <div class="gstep"><b>1. New shortcut</b><p class="small">Open <b>Shortcuts</b>, tap <b>+</b>. Tap <b>Add Action</b> and search <b>Find Health Samples</b>.</p></div>
  <div class="gstep"><b>2. Find Health Samples</b>
    <p class="small">Set it up exactly like this: <i>Type is Steps</i>, <i>Start Date is today</i>, <i>Group by Day</i>, <i>Limit off</i>. Then add <b>Calculate Statistics</b> (Sum of Health Samples).</p>
    <img class="gimg" src="<?= $g('shortcut-find.jpg') ?>" alt="Find Health Samples and Calculate Statistics" loading="lazy"></div>
  <div class="gstep"><b>3. Get Contents of URL</b>
    <p class="small">Add the action <b>Get Contents of URL</b>, open its arrow and fill in:</p>
    <div class="cf"><span>URL</span><input readonly value="<?= e($site) ?>/api/v1/steps"><button type="button" class="btn small" data-copy="<?= e($site) ?>/api/v1/steps">Copy</button></div>
    <div class="cf"><span>Method</span><input readonly value="POST"></div>
    <div class="cf"><span>Header name</span><input readonly value="Authorization"><button type="button" class="btn small" data-copy="Authorization">Copy</button></div>
    <div class="cf"><span>Header value</span><input readonly value="Bearer <?= e($ikey) ?>"><button type="button" class="btn small" data-copy="Bearer <?= e($ikey) ?>">Copy</button></div>
    <div class="cf"><span>Request Body</span><input readonly value="JSON"></div>
    <div class="cf"><span>Field</span><input readonly value="Number · steps = the Sum variable"></div>
    <img class="gimg" src="<?= $g('shortcut-url.jpg') ?>" alt="Get Contents of URL filled in" loading="lazy">
    <p class="muted small">For the field: tap <b>Add new field</b> → <b>Number</b>, key <code>steps</code>, then tap the value and choose the <b>Sum</b> variable from the Calculate action.</p></div>
  <div class="gstep"><b>4. Test it</b><p class="small">Tap the ▶ play button and allow Health access. You should see something like this answer (your steps number, <code>"ok": true</code>), and today's steps show up here.</p>
    <img class="gimg" src="<?= $g('shortcut-done.jpg') ?>" alt="Result with ok true" loading="lazy"></div>
  <div class="gstep"><b>5. Make it automatic</b><p class="small">Shortcuts → <b>Automation</b> → <b>+</b> → <b>Time of Day</b> (for example 22:00, Daily) → <b>Run Immediately</b> → pick your shortcut. Add a second one at 12:00 if you like. Sending again for a day only raises the number, so extra runs are safe.</p></div>
  <p class="muted small">Your key also lives under <a href="<?= e(url('settings')) ?>#api">Settings</a>. Missed a day? Type it in above.</p>
<?php endif; ?>
</div>

<div class="card">
  <h3>🤖 Android app</h3>
  <p class="small">The MyFitLevel Android app reads your steps from Health Connect and syncs them for you, and you do not need to copy any key: open this page inside the app and tap "Connect this phone". <a href="https://github.com/Niemil/MyFitLevel/releases/download/latest/MyFitLevel.apk">Download the APK</a> (you may need to allow installing from your browser).</p>
</div>

<div class="card">
  <h3>Other apps</h3>
  <p class="small">Any app that can send a web request works: for example Tasker or MacroDroid can POST the same JSON. Prefer something ready-made? Use the Android app above.</p>
  <pre class="small" style="white-space:pre-wrap">POST <?= e($site) ?>/api/v1/steps
Authorization: Bearer YOUR_KEY
Content-Type: application/json

{"steps": 8123, "distance_m": 6100, "date": "<?= e(date('Y-m-d')) ?>"}</pre>
  <p class="muted small">date and distance_m are optional. Sending again for the same day replaces the value.</p>
</div>
<?php layout_footer('', ['copy.js']); ?>
