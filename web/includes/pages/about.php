<?php
declare(strict_types=1);

layout_header('About', '');
?>
<h1 class="pagetitle">About <?= e(site_name()) ?></h1>
<div class="card prose">
  <h3>Free, non-profit, community-run</h3>
  <p><?= e(site_name()) ?> is a hobby project made by a member of the community who missed the old gamified fitness sites. It was started in November 2026 and is still being built, so expect rough edges and please <a href="<?= e(url('feedback')) ?>">tell us what to improve</a>.</p>
  <ul>
    <li><b>Free.</b> Everything on the site is free for everyone: routines, schedules, quests, challenges, themes and the rest.</li>
    <li><b>No profit.</b> There are no ads, no paywall and nothing is sold. Nobody is paid for this.</li>
    <li><b>Your data is yours.</b> Download everything you have logged any time (Settings → Your data) or delete your account and all of it.</li>
    <li><b>Open to everyone who wants to help.</b> Ideas, bug reports and offers to build together are welcome through the <a href="<?= e(url('feedback')) ?>">feedback form</a>.</li>
    <li><b>Open source.</b> The whole site and the Android app are public on <a href="https://github.com/Niemil/myfitlevel" target="_blank" rel="noopener">GitHub</a> under the AGPL licence. Read the code, run your own copy, report a bug or send an improvement as a pull request.</li>
    <li><b>Open API.</b> Read and log your own workouts from other apps. See the <a href="<?= e(url('api-docs')) ?>">API guide</a>, or <a href="<?= e(url('connect')) ?>">Connect &amp; automate</a> for no-code recipes (Sheets, calendar, Discord, Shortcuts).</li>
  </ul>
  <p class="muted small"><?= e(site_name()) ?> is inspired by Fitocracy but is not affiliated with or endorsed by it. Names, art, texts and the points formulas here are our own. <a href="<?= e(url('points')) ?>">How points work</a> · <a href="<?= e(url('privacy')) ?>">Privacy</a></p>
</div>
<?php layout_footer(''); ?>
