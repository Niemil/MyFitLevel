<?php
declare(strict_types=1);

$name = site_name();
layout_header('Privacy', '', ['index' => true, 'path' => 'privacy', 'desc' => 'What ' . $name . ' stores about you, how it is used, and how to export or delete your data.']);
?>
<div class="prose">
  <h1>Privacy</h1>
  <p><?= e($name) ?> is a small, community-run project. Here is the short, honest version.</p>
  <h2>What we store</h2>
  <ul>
    <li>Your account: username, email (optional), a hashed password, and the profile details you choose to add.</li>
    <li>Your workouts, sets, props, comments, group posts, achievements and quests.</li>
    <li>Technical data needed to keep the service safe, such as rate-limit counters.</li>
    <li>A site log of errors and security events (for example a failed login with the username typed, a blocked request, or a refused API key). It holds the page address without anything after the question mark, a shortened IP address and the first part of the browser name. It never holds passwords, API keys or what you search for, and it is deleted after 30 days.</li>
  </ul>
  <h2 id="choices">Cookies and visitor statistics</h2>
  <ul>
    <li><b>Always:</b> one session cookie (and, if you tick it, a "stay signed in" cookie) keeps you logged in. No advertising, no third-party analytics, no data sold.</li>
    <li><b>Anonymous counting (no consent needed):</b> for each page view we note the page, where you came from (e.g. Google or Facebook), your country, device and browser type, and whether you look like a person or a bot. Your IP address is <b>not</b> stored. To count unique visitors we use a one-way hash of your IP and browser that changes every day, so it cannot follow you from one day to the next.</li>
    <li><b>If you press OK:</b> we also set a random visitor cookie so we can see returning visitors, and we keep your IP address for <?= STATS_IP_DAYS ?> days. Statistics are deleted after <?= STATS_KEEP_DAYS ?> days.</li>
    <li><b>If you press No thanks</b>, or your browser sends Do Not Track or Global Privacy Control, we only do the anonymous counting above, and we never show you the question again (browser signals: not at all).</li>
    <li>We also count how many people press OK or No thanks, without storing who.</li>
    <li>Your light/dark choice and unfinished-workout backup are kept in your browser's local storage on your device.</li>
  </ul>
  <p><button type="button" class="btn" data-stats-prefs>Change my choice</button></p>
  <p class="muted small">Country lookup uses IP geolocation data by <a href="https://db-ip.com" rel="noopener">DB-IP.com</a> (CC BY 4.0).</p>
  <h2>Who sees your workouts</h2>
  <p>Workouts appear in the feed for members who follow you and, depending on your privacy settings, on your profile. We do not sell your data.</p>
  <h2>Your control</h2>
  <ul>
    <li>Export all your workouts any time from Settings.</li>
    <li>Delete your account and everything attached to it from Settings.</li>
  </ul>
  <p class="muted small">Questions? Use the contact details shown by the site administrator.</p>
</div>
<?php layout_footer(); ?>
