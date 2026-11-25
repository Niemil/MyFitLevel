<?php
declare(strict_types=1);

$name = site_name();
$open = setting('registration', '1') === '1';
$faq = [
    ['Is ' . $name . ' made by the Fitocracy team?', 'No. ' . $name . ' is an independent, community-run project inspired by the gamified approach people loved. It is not affiliated with or endorsed by Fitocracy.'],
    ['Can I import my old Fitocracy history?', 'Not yet. If you saved an export of your old workouts, tell us: import tools for popular formats are on the wishlist.'],
    ['Is the points the same as on Fitocracy?', 'It follows the same idea (points for every set, levels on top) but uses its own model, built on the estimated one-rep max and a diminishing return on reps, so point totals will not match the old site.'],
    ['Will it stay free?', 'The core app is free to use with no ads. If running costs ever need help, the plan is community donations rather than a paywall on basics like logging and levels.'],
];
$schema = [[
    '@context' => 'https://schema.org', '@type' => 'FAQPage',
    'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq),
]];

layout_header('Fitocracy alternative: free workout tracker with points & levels', '', [
    'index' => true, 'path' => 'fitocracy-alternative', 'schema' => $schema,
    'desc' => 'Looking for a Fitocracy alternative? ' . $name . ' is a free workout tracker with points, levels, achievements, quests, groups and props. Works on desktop and phone.',
]);
?>
<div class="prose">
  <h1>A Fitocracy alternative that's free and alive</h1>
  <p class="lead" style="margin-left:0">Fitocracy made lifting feel like a game: points for every set, levels, achievements, quests and a friendly community that cheered you on. When it went offline, a lot of people were left looking for somewhere new to log their training. <?= e($name) ?> is a free, community-run place built around the same idea.</p>

  <div class="cta" style="justify-content:flex-start;margin:14px 0 24px">
    <?php if ($open): ?><a class="btn big" href="<?= e(url('register')) ?>">Start free</a><?php endif; ?>
    <a class="btn ghost big" href="<?= e(url('points')) ?>">How points works</a>
  </div>

  <h2>What you'll recognise</h2>
  <ul>
    <li><b>points for every set</b>, with heavier lifts paying more and extra reps paying less and less.</li>
    <li><b>Levels</b> that climb as you train, with a progress bar you'll want to fill.</li>
    <li><b>Achievements and quests</b> for streaks, milestones, volume and trying new things.</li>
    <li><b>Props and comments</b> on friends' workouts, plus a <b>feed</b> of people you follow.</li>
    <li><b>Groups</b> and a weekly <b>leaderboard</b> to keep things friendly and competitive.</li>
  </ul>

  <h2>What's new</h2>
  <ul>
    <li><b>Autosave:</b> every set is saved as you log it, so closing the page or losing signal at the gym never costs you a workout.</li>
    <li><b>Smart pre-fill:</b> sets start from your last session, with +/- buttons for weight and reps.</li>
    <li><b>Warm-up sets</b> that count for half points, so your whole session adds up.</li>
    <li><b>Any movement:</b> walks, hikes, runs, swims, yoga and sports count as well as lifting.</li>
    <li><b>kg or lb</b>, light or dark mode, and it installs on your phone like an app.</li>
    <li><b>Your data is yours:</b> export it any time.</li>
  </ul>

  <div class="card">
    <table class="cmp">
      <thead><tr><th></th><th><?= e($name) ?></th><th>Fitocracy</th></tr></thead>
      <tbody>
        <tr><td>Currently online</td><td class="y">✓</td><td class="n">Offline</td></tr>
        <tr><td>points &amp; levels</td><td class="y">✓</td><td class="y">✓</td></tr>
        <tr><td>Achievements &amp; quests</td><td class="y">✓</td><td class="y">✓</td></tr>
        <tr><td>Props, feed &amp; groups</td><td class="y">✓</td><td class="y">✓</td></tr>
        <tr><td>Free to use</td><td class="y">✓</td><td class="n">n/a</td></tr>
        <tr><td>Autosaving logger</td><td class="y">✓</td><td class="n">n/a</td></tr>
        <tr><td>Data export</td><td class="y">✓</td><td class="n">n/a</td></tr>
      </tbody>
    </table>
  </div>
  <p class="muted small"><?= e($name) ?> is independent and not affiliated with, sponsored by or endorsed by Fitocracy. Fitocracy is a trademark of its respective owner.</p>

  <section class="faq">
    <h2>Questions</h2>
    <?php foreach ($faq as $f): ?>
    <details><summary><?= e($f[0]) ?></summary><p><?= e($f[1]) ?></p></details>
    <?php endforeach; ?>
  </section>

  <?php if ($open): ?>
  <div class="band">
    <?= mascot('cheer', 100) ?>
    <h2>Come level up with us</h2>
    <a class="btn big" href="<?= e(url('register')) ?>">Create a free account</a>
  </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>
