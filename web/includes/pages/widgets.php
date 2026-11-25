<?php
declare(strict_types=1);

$base = rtrim(site_url(''), '/');
$dl = fn(string $f) => url('downloads/' . $f);
layout_header('Desktop & phone widgets', '', ['index' => true, 'path' => 'widgets', 'desc' => 'Show your level, points and streak on your desktop or phone with free widgets for Lively Wallpaper, Rainmeter, macOS, iPhone and Linux.']);
?>
<h1 class="pagetitle">Desktop &amp; phone widgets</h1>
<p class="lead">Put your level, points, weekly streak and today's workout on your desktop wallpaper or phone home screen. Free, and they only <b>read</b> your numbers with a personal key, so nothing is shared with anyone else.</p>

<div class="card prose">
  <img src="<?= e($dl('myfitlevel-lively-preview.jpg')) ?>" alt="MyFitLevel as an animated Windows wallpaper (Lively): level 28 ring, streak, weekly workouts and today's workout" style="width:100%;border-radius:14px" loading="lazy">
  <p class="muted small">Lively Wallpaper: your stats as a live wallpaper. Colours, size and position are adjustable.</p>
  <img src="<?= e($dl('myfitlevel-rainmeter-preview.jpg')) ?>" alt="MyFitLevel as a small Rainmeter widget on top of a normal wallpaper" style="width:100%;border-radius:14px;margin-top:10px" loading="lazy">
  <p class="muted small">Rainmeter: a small widget on top of any wallpaper you already like.</p>
  <h3>Before you start: get your key</h3>
  <ol>
    <li>Log in, open <a href="<?= e(url('settings')) ?>">Settings</a> → <b>API access</b> and press <b>Generate API key</b>. Copy it, it is shown once.</li>
    <li>The widgets read your stats through <code><?= e($base) ?>/api/v1/widget?key=YOUR_KEY</code>.</li>
    <li>Treat the key like a password. If it leaks, generate a new one in Settings and the old one stops working.</li>
  </ol>
  <p class="muted small">Which should you pick? On Windows 10/11, <b>Lively Wallpaper</b> is the easiest (a full animated wallpaper, no coding). <b>Rainmeter</b> gives a small widget on top of your normal wallpaper. On iPhone use <b>Scriptable</b>. These were made by the community and are not tested on every setup, so tell us what breaks via <a href="<?= e(url('feedback')) ?>">feedback</a>.</p>
</div>

<div class="card prose">
  <h3>Windows: Lively Wallpaper (free)</h3>
  <p><b>Get the free official version from GitHub.</b> Lively is free and open source. Some look-alike apps in the Microsoft Store are paid copies, so use the official one:</p>
  <ol>
    <li>Open <a href="https://github.com/rocksdanister/lively/releases" rel="noopener">github.com/rocksdanister/lively/releases</a> (the project by <i>rocksdanister</i>).</li>
    <li>Under the newest release, open <b>Assets</b> and download <code>lively_setup_x86_full_…exe</code>. Run it. If Windows SmartScreen warns, press <b>More info → Run anyway</b> (the developer explains this on the page).</li>
    <li>Come back here and <a href="<?= e($dl('myfitlevel-lively-wallpaper.zip')) ?>">download the MyFitLevel wallpaper (zip)</a>. Do not unzip it.</li>
    <li>In Lively press <b>+</b> (Add wallpaper) and choose the zip, or drag the zip onto the Lively window. Click the new wallpaper to set it.</li>
    <li>Click the <b>Customise</b> (sliders) icon on it, paste your key into <b>API key</b>, and pick colours, size and left / centre / right position.</li>
  </ol>
  <p class="muted small">Want to change the design? The wallpaper is a single <code>index.html</code> file; open it in any text editor. To test in a normal browser, open <code>index.html?key=YOUR_KEY</code>.</p>
</div>

<div class="card prose">
  <h3>Windows: Rainmeter</h3>
  <p>Free from <a href="https://www.rainmeter.net" rel="noopener">rainmeter.net</a>.</p>
  <ol>
    <li><a href="<?= e($dl('myfitlevel-rainmeter-skin.zip')) ?>">Download the skin (zip)</a> and unzip it into <code>Documents\Rainmeter\Skins\</code> so you get a <code>MyFitLevel</code> folder.</li>
    <li>Open <code>@Resources\Variables.inc</code> inside that folder in Notepad and put your key on the <code>ApiKey=</code> line.</li>
    <li>In Rainmeter's manager click <b>Refresh all</b>, find <b>MyFitLevel</b>, and <b>Load</b> the skin. Click the widget to open the site. Drag it where you like.</li>
  </ol>
</div>

<div class="card prose">
  <h3>macOS: Übersicht</h3>
  <ol>
    <li>Install the free <a href="https://tracesof.net/uebersicht/" rel="noopener">Übersicht</a>.</li>
    <li><a href="<?= e($dl('myfitlevel-ubersicht-widget.zip')) ?>">Download the widget (zip)</a>, unzip it, and move <code>myfitlevel.widget</code> to <code>~/Library/Application Support/Übersicht/widgets/</code>.</li>
    <li>Open <code>index.jsx</code> in a text editor, paste your key into the <code>KEY</code> line and save. The widget appears on your desktop.</li>
  </ol>
</div>

<div class="card prose">
  <h3>iPhone &amp; iPad: Scriptable</h3>
  <ol>
    <li>Install the free <b>Scriptable</b> app from the App Store.</li>
    <li><a href="<?= e($dl('myfitlevel-scriptable.js')) ?>">Open the script</a>, copy everything, and paste it into a new script in Scriptable (name it MyFitLevel).</li>
    <li>Add a Scriptable widget to your home screen, long-press it → <b>Edit widget</b> → Script: <b>MyFitLevel</b>, and paste your API key into the <b>Parameter</b> field.</li>
  </ol>
</div>

<div class="card prose">
  <h3>Android</h3>
  <p>There is no ready-made Android widget yet. Two options today: <b>(1)</b> add MyFitLevel to your home screen from the browser menu (it installs as an app), or <b>(2)</b> in the free-to-try <b>KWGT</b> widget app use its web function to read the numbers, for example <code>$wg("<?= e($base) ?>/api/v1/widget?key=YOUR_KEY", json, "$.level")$</code> for your level (other fields: <code>xp</code>, <code>streak</code>, <code>week_workouts</code>, <code>week_streak</code>, <code>today_label</code>). This is a starting point we have not tested in KWGT, so tell us how it goes.</p>
</div>

<div class="card prose">
  <h3>Linux: Conky</h3>
  <p><a href="<?= e($dl('myfitlevel-conky.conf')) ?>">Download the MyFitLevel Conky config</a>, replace <code>PASTE_YOUR_API_KEY_HERE</code> with your key (it appears in each line), install <code>curl</code>, <code>jq</code> and <code>conky</code>, and run <code>conky -c myfitlevel-conky.conf</code>.</p>
</div>

<div class="card prose">
  <h3>Also use SnakeDiet?</h3>
  <p>The sister site <a href="https://snakediet.org" rel="noopener">snakediet.org</a> has its own widgets for your fasting stats. They are separate from the ones above and need the key from snakediet.org (it starts with <code>sd_</code>): <a href="<?= e($dl('snakediet-lively-wallpaper.zip')) ?>">Lively wallpaper</a> · <a href="<?= e($dl('snakediet-rainmeter-skin.zip')) ?>">Rainmeter skin</a> · <a href="<?= e($dl('snakediet-conky.conf')) ?>">Conky</a>. Install them the same way as above.</p>
</div>

<div class="card prose">
  <h3>Build your own</h3>
  <p>The same <code>/api/v1/widget</code> answer is one flat JSON object, easy to read from any tool. There are also calls for stats, quests, badges, routines, today's workout and the leaderboard. See the <a href="<?= e(url('api-docs')) ?>">API guide</a>. Made something nice? Tell us through <a href="<?= e(url('feedback')) ?>">feedback</a> and we will add it here.</p>
</div>
<?php layout_footer(''); ?>
