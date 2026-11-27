<?php
declare(strict_types=1);

/* Connect & automate: plain-language recipes for using your data elsewhere, no coding needed. */

$u = me();
$uid = $u ? (int)$u['id'] : 0;
$api = site_url('api/v1');

if ($u && is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'hook_save') {
        $url = trim((string)($_POST['url'] ?? ''));
        $k = trim((string)($_POST['key'] ?? ''));
        if ($k !== '' || $url === '') {
            set_setting('webhook_key:' . $uid, mb_substr($k, 0, 120));
        }
        if ($url === '') {
            set_setting('webhook:' . $uid, '');
            flash('Webhook removed.');
        } elseif (!webhook_resolve($url)) {
            flash('The address must start with https:// and point to a public website.', 'err');
        } else {
            set_setting('webhook:' . $uid, $url);
            flash('Saved. You get a message each time you finish a workout.');
        }
    } elseif ($do === 'hook_resend') {
        if (!rate_ok('hookresend:' . $uid, 3, 600)) {
            flash('Wait a few minutes before sending again (3 successful sends per 10 minutes).', 'err');
        } else {
            [$n, $d, $x] = webhook_resend_snakediet($uid, 7);
            if ($n > 0) {
                rate_hit('hookresend:' . $uid);   // only successful sends count
            }
            flash($n + $d + $x === 0 ? 'Nothing to send. Save a SnakeDiet address and key first, and log a workout in the last 7 days.' : 'Sent ' . $n . ' workout' . ($n === 1 ? '' : 's') . ($d ? ', ' . $d . ' already there' : '') . ($x ? ', ' . $x . ' failed (' . trim((string)($GLOBALS['webhook_resend_err'] ?? '')) . ')' : '') . '.', $x ? 'err' : 'ok');
        }
    } elseif ($do === 'hook_test') {
        $url = (string)setting('webhook:' . $uid, '');
        if ($url !== '' && rate_ok('hooktest:' . $uid, 5, 600)) {
            rate_hit('hooktest:' . $uid);
            $ok = webhook_post($url, ['content' => '✅ MyFitLevel test message. It works!', 'text' => '✅ MyFitLevel test message. It works!', 'username' => 'MyFitLevel', 'source' => (string)parse_url(site_url(''), PHP_URL_HOST), 'event' => 'test'], (string)setting('webhook_key:' . $uid, ''), 3, $uid);
            flash($ok ? 'Sent! Check where the webhook posts.' : 'The other side did not accept it. Check the address.', $ok ? 'ok' : 'err');
        } else {
            flash('Save a webhook address first (max 5 tests per 10 minutes).', 'err');
        }
    }
    redirect('connect');
}
$hook = $u ? (string)setting('webhook:' . $uid, '') : '';
$K = 'YOUR_KEY';
layout_header('Connect & automate', '');
?>
<h1 class="pagetitle">Connect &amp; automate</h1>
<p class="muted">Your workouts are yours. Use them in a spreadsheet, a calendar, your phone, Discord, a smart home or a stream. Most of these need no coding, just copy and paste.</p>

<div class="card prose">
  <h3>Step 1: get your key</h3>
  <p>Open <a href="<?= e(url('settings')) ?>">Settings</a> → API access → Generate API key. Copy it once; it works like a password. Wherever you see <code>YOUR_KEY</code> below, put your key there. Links with the key in them only <b>read</b> your data. Writing always needs the key sent privately (the recipes below do that).</p>
</div>

<div class="card prose"><h3>📊 Spreadsheet that updates itself</h3>
  <p>In Google Sheets, click a cell and paste:</p>
  <pre>=IMPORTDATA("<?= e($api) ?>/export.csv?key=<?= $K ?>&amp;inline=1")</pre>
  <p>Every set you have logged appears as rows: date, exercise, weight, reps, points. Make your own charts, pivot tables or Looker Studio dashboard from it. Excel: Data → From web.</p></div>

<div class="card prose"><h3>📅 Workouts in your calendar</h3>
  <p>Subscribe to this address in Google Calendar (Other calendars → From URL), Apple Calendar (File → New Calendar Subscription) or Outlook:</p>
  <pre><?= e($api) ?>/calendar.ics?key=<?= $K ?></pre>
  <p>Your finished workouts show up as events. Calendars refresh it every few hours.</p></div>

<div class="card prose"><h3>🔔 Post to Discord, Slack, Zapier or Make</h3>
  <p>Get a message each time you finish a workout, for example in a private Discord channel with friends. In Discord: channel settings → Integrations → Webhooks → copy the webhook URL. In Zapier / Make / IFTTT use a "Webhooks / Catch hook" trigger, then send the data to Sheets, Notion, email, Telegram, a smart light, anything.</p>
  <?php if ($u): ?>
  <form method="post" class="chalform"><?= csrf_field() ?><input type="hidden" name="do" value="hook_save">
    <label>Webhook address <input type="url" name="url" value="<?= e($hook) ?>" placeholder="https://discord.com/api/webhooks/…" maxlength="400"></label>
    <label>Key for that site (only if it asks for one, e.g. SnakeDiet) <input type="password" name="key" autocomplete="off" placeholder="<?= setting('webhook_key:' . $uid, '') !== '' ? 'saved (type a new one to replace)' : 'optional' ?>" maxlength="120"></label>
    <div class="row" style="gap:.5rem"><button class="btn" type="submit">Save</button>
    <?php if ($hook): ?><button class="btn ghost" name="do" value="hook_test" type="submit">Send a test</button><?php endif; ?>
    <?php if (webhook_is_snakediet($hook)): ?><button class="btn ghost" name="do" value="hook_resend" type="submit">Resend last 7 days to SnakeDiet</button><?php endif; ?></div>
    <p class="muted small">Leave empty and save to remove it. Only https addresses on the public internet work.</p></form>
  <?php else: ?><p><a href="<?= e(url('login')) ?>">Log in</a> to set this up.</p><?php endif; ?></div>
<?php if ($u): webhook_log_ensure(); $wlog = rows('SELECT created_at, method, url, status, response FROM webhook_log WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$uid]); ?>
<?php if ($wlog): ?>
<div class="card prose"><h3>Last calls in and out</h3>
  <p class="muted small">The last 20 calls sent from here (POST) or received (IN). Your key is never shown.</p>
  <?php foreach ($wlog as $l): ?>
  <div class="small" style="margin:.5rem 0;word-break:break-all"><b class="<?= (int)$l['status'] >= 200 && (int)$l['status'] < 300 ? 'ok' : '' ?>"><?= (int)$l['status'] ?></b> <?= e($l['method']) ?> <code><?= e($l['url']) ?></code> <span class="muted"><?= e($l['created_at']) ?></span><br><span class="muted"><?= e($l['response'] !== '' ? $l['response'] : '(no response)') ?></span></div>
  <?php endforeach; ?></div>
<?php endif; endif; ?>


<div class="card prose"><h3>🐍 Link with SnakeDiet (and other sites)</h3>
  <p>Two-way, no coding. <b>From here to SnakeDiet:</b> on SnakeDiet create an API key (Settings), then paste here as webhook address <code>https://snakediet.org/api/v1/posts</code> and that key in "Key for that site". Each finished workout then shows up as a post there.</p>
  <p><b>From SnakeDiet to here:</b> in your SnakeDiet connect settings, paste <code><?= e($api) ?>/posts</code> and your MyFitLevel key (Step 1). Your fasts and milestones then show up in your feed here. Details for site owners: <a href="<?= e(url('api-docs')) ?>">API guide</a>.</p></div>

<div class="card prose"><h3>📱 iPhone: Shortcuts</h3>
  <p><b>"How am I doing?"</b> Make a shortcut: <i>Get contents of URL</i> → <code><?= e($api) ?>/summary?format=text&amp;key=<?= $K ?></code> → <i>Speak text</i>. Name it "How am I doing" and ask Siri. Put it on your Lock Screen or Action Button.</p>
  <p><b>Quick log.</b> <i>Get contents of URL</i> → same address ending in <code>/quicklog</code>, method POST, header <code>Authorization: Bearer YOUR_KEY</code>, request body JSON: <code>{"exercise":"db bench","sets":3,"reps":8,"weight":30}</code>. One tap and the workout is logged, with points and levels.</p></div>

<div class="card prose"><h3>🤖 Android: HTTP Shortcuts, Tasker, KWGT</h3>
  <p>The free app <b>HTTP Shortcuts</b> makes home-screen buttons for any web address: one button for <code>/summary</code> (shows your status), one for <code>/quicklog</code> (logs a usual workout). Tasker and KWGT can read <code>/widget</code> for live level and streak. Ready-made widgets: <a href="<?= e(url('widgets')) ?>">Widgets</a>.</p></div>

<div class="card prose"><h3>🖼 Show your level anywhere</h3>
  <p>A live stats card as an image, no key needed, for forum signatures, Discord, a website or a GitHub profile:</p>
  <pre><?= e($api) ?>/card/<?= e($u ? $u['username'] : 'yourname') ?>.svg</pre>
  <?php if ($u): ?><p><img src="<?= e($api) ?>/card/<?= e($u['username']) ?>.svg" alt="Your stats card" style="max-width:100%;border-radius:12px"></p><?php endif; ?>
  <p class="muted small">The card shows only level, points, streak, workouts and PRs.</p></div>

<div class="card prose"><h3>🏠 Smart home &amp; screens</h3>
  <p><b>Home Assistant:</b> add a REST sensor with <code><?= e($api) ?>/widget?key=<?= $K ?></code> and use <code>level</code>, <code>streak</code>, <code>week_workouts</code> in dashboards or automations (flash a light when you hit a new level, remind you if nobody trained by 18:00). <b>Desktop and phone widgets:</b> Rainmeter, Lively, Übersicht, Conky, Scriptable under <a href="<?= e(url('widgets')) ?>">Widgets</a>.</p></div>

<div class="card prose"><h3>💡 More ideas</h3>
  <ul>
    <li><b>Weekly report to yourself.</b> Zapier / Make: every Sunday, fetch <code>/week</code> and email or Telegram it.</li>
    <li><b>Streamer overlay.</b> OBS Browser Source pointing at a page that reads <code>/widget</code> every minute.</li>
    <li><b>Coach or friend view.</b> Share your stats card, or let a friend subscribe to your calendar address.</li>
    <li><b>Smartwatch button.</b> Tasker / Shortcuts runs <code>/quicklog</code> from your wrist.</li>
    <li><b>Lift tracker in Notion or Obsidian.</b> Pull <code>/prs</code> for your best lifts as a table.</li>
    <li><b>Group challenge.</b> Everyone posts the webhook into one Discord and you see the whole crew's workouts live.</li>
    <li><b>E-ink or desk display.</b> A Raspberry Pi shows level and streak from <code>/widget</code>.</li>
  </ul></div>

<div class="card"><p>Want the full technical list of calls? <a href="<?= e(url('api-docs')) ?>">API guide</a>. Missing something you'd like to connect to? <a href="<?= e(url('feedback')) ?>">Tell us</a>.</p></div>
<?php layout_footer(''); ?>
