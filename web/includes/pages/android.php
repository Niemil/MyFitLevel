<?php
declare(strict_types=1);

$apk = 'https://github.com/Niemil/myfitlevel/releases/latest/download/MyFitLevel.apk';
$gh = 'https://github.com/Niemil/myfitlevel';
layout_header('Android app', '', ['index' => true, 'path' => 'android', 'desc' => 'The free, open-source ' . site_name() . ' Android app: your steps sync automatically through Health Connect.']);
?>
<h1 class="pagetitle">Android app</h1>
<div class="card prose">
  <p class="lead" style="margin-left:0">The same site as an app, plus automatic step syncing through Health Connect. Free, no ads, and the source code is open.</p>
  <p><a class="btn big" href="<?= e($apk) ?>">Download for Android (APK)</a></p>
  <p class="muted small">Always the latest build, straight from <a href="<?= e($gh) ?>/releases/latest" target="_blank" rel="noopener">GitHub Releases</a>. The code behind it is in the <a href="<?= e($gh) ?>/tree/main/android" target="_blank" rel="noopener">android folder</a>.</p>
  <h3>How to install</h3>
  <ol>
    <li>Tap <b>Download for Android</b>. Your browser may warn that the file can harm your device. That is the standard warning for any app that is not from Google Play. Choose to keep it.</li>
    <li>Open the downloaded file. Android asks to allow installs from your browser. Allow it for this one install.</li>
    <li>Google Play Protect may say the app is unrecognised or may be harmful, because it is new and not from Google Play. Tap <b>More details</b> (or <b>Install anyway</b>). The code is open for anyone to read on GitHub.</li>
    <li>Open the app and log in. Tap the link button once and the app connects itself to your account, so there is no API key to paste.</li>
    <li>Allow access to steps in Health Connect. Your steps then sync by themselves and become points, boss damage and coins.</li>
  </ol>
  <h3>Good to know</h3>
  <ul>
    <li>It is not in Google Play yet. Updates are installed the same way, by downloading the new file.</li>
    <li>Prefer no install? The website works in any browser and can be added to your home screen as an app.</li>
    <li>Every piece of it is open: the <a href="<?= e($gh) ?>" target="_blank" rel="noopener">website and the app on GitHub</a>, and the <a href="<?= e(url('api-docs')) ?>">API</a> that other apps and scripts can use.</li>
    <li>On iPhone you can sync steps with a Shortcut. See <a href="<?= e(url('steps')) ?>">Steps</a>.</li>
  </ul>
</div>
<?php layout_footer(); ?>
