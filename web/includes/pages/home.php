<?php
declare(strict_types=1);

if (me()) {
    redirect('feed');
}

$members = (int)val('SELECT COUNT(*) FROM users');
$workouts = (int)val('SELECT COUNT(*) FROM workouts');
$lifted = (float)val('SELECT COALESCE(SUM(volume_kg),0) FROM workouts');
$liftedLb = $lifted * LB_PER_KG;
$setsN = (int)val("SELECT COUNT(*) FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.is_warmup = 0 AND e.kind IN ('weight','bodyweight')");
$repsN = (int)val("SELECT COALESCE(SUM(s.reps),0) FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.is_warmup = 0 AND e.kind IN ('weight','bodyweight')");
$stepsN = (int)val('SELECT COALESCE(SUM(steps),0) FROM daily_steps');
$popular = rows("SELECT e.name, COUNT(DISTINCT s.workout_id) n FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE e.kind IN ('cardio','duration') GROUP BY e.id, e.name HAVING n >= 3 ORDER BY n DESC LIMIT 4");
$lifters = (int)val('SELECT COUNT(DISTINCT user_id) FROM workouts');
$avgW = $lifters > 0 ? round($workouts / $lifters, 1) : 0;
$open = setting('registration', '1') === '1';
$name = site_name();
$pub = feed_items(null, 'everyone', null, 6)['items'];

$faq = [
    ['Is ' . $name . ' really free?', 'Yes. You can log unlimited workouts, earn points, join groups and use the leaderboard without paying. It is a community-run project with no ads.'],
    ['Is this a Fitocracy alternative?', $name . ' is inspired by the points, levels, achievements, quests and friendly feed that people loved about Fitocracy. It is a new, independent project and is not affiliated with Fitocracy.'],
    ['Do I need to log gym workouts only?', 'No. Lifting, running, walking, hiking, swimming, cycling, yoga, bodyweight training and sports all count. Walks and outdoor sessions earn points too.'],
    ['Can I use pounds or kilograms?', 'Both. Pick kg or lb in your settings and everything is converted for you.'],
    ['Can I use it on my phone?', 'Yes. ' . $name . ' works in any browser and can be added to your home screen like an app. Sets are autosaved as you log them, so you can switch apps or lose signal without losing your workout.'],
    ['Can I take my data with me?', 'Yes. You can export all your workouts as a file at any time and delete your account whenever you want.'],
];

$schema = [
    ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $name, 'url' => site_url(''), 'description' => site_tagline()],
    ['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => $name, 'url' => site_url(''),
     'applicationCategory' => 'HealthApplication', 'operatingSystem' => 'Any (web browser, installable PWA)',
     'description' => 'Free workout tracker and fitness community with points, levels, achievements, quests, groups and a leaderboard.',
     'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD']],
    ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => [
        '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $faq)],
];

$schema[] = ['@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => $name . ': the full tour',
    'description' => 'Track workouts, earn points, level from 1 to 100, finish quests, hit weekly goals and compete with friends. Free and non-profit.',
    'thumbnailUrl' => [site_url('media/promo-poster.jpg')], 'uploadDate' => '2026-11-27', 'duration' => 'PT47S',
    'contentUrl' => site_url('media/promo.mp4')];
define('GAME_CSS', true);
game_ensure();
$landBoss = game_boss_public();
layout_header($name . ': Free Workout Tracker & Fitocracy Alternative', '', [
    'index' => true, 'full_title' => true, 'path' => '',
    'desc' => 'Free workout tracker and fitness community. Log lifts, runs and walks, earn points, level up, unlock achievements and quests, and cheer on friends. A Fitocracy alternative.',
    'schema' => $schema,
]);
?>
<section class="hero2">
  <?php foreach ([8, 22, 38, 54, 70, 86] as $i => $left): ?><span class="fl" style="left:<?= $left ?>%;animation-delay:<?= $i * 1.4 ?>s">+<?= [12, 45, 80, 25, 120, 60][$i] ?></span><?php endforeach; ?>
  <div class="h2-copy">
    <div class="h2-pill"><i></i>Free forever · open source · no ads</div>
    <h1><span class="l1">Turn every <span class="rot"><span>set</span><span>run</span><span>walk</span></span></span> <span class="l2">into <span class="grad">points</span>.</span></h1>
    <p class="lead">The free workout tracker and fitness community where every set, run and walk earns points. Level up, unlock achievements, finish quests and cheer on your friends.</p>
    <div class="cta">
      <?php if ($open): ?><a class="btn big" href="<?= e(url('register')) ?>">Create a free account</a><?php endif; ?>
      <a class="btn ghost big" href="<?= e(url('login')) ?>">Log in</a>
    </div>
    <div class="trustbar">
      <a href="https://github.com/Niemil/myfitlevel" target="_blank" rel="noopener"><b>Open source</b><small>Code on GitHub</small></a>
      <a href="<?= e(url('android')) ?>"><b>Android app</b><small>Steps via Health Connect</small></a>
      <a href="<?= e(url('api-docs')) ?>"><b>Open API</b><small>Docs &amp; API keys</small></a>
    </div>
    <?php if ($members > 0): ?>
    <div class="stx">
      <div class="sc sc-wide sc-w">
        <i class="sc-ic">🏋️</i>
        <b class="sc-num" id="wt" data-kg="<?= (int)round($lifted) ?>" data-lb="<?= (int)round($liftedLb) ?>"><?= e(fmt_big($lifted)) ?></b>
        <span class="sc-lab"><u id="wtu">kg</u> lifted in total</span>
        <em class="sc-sw"><button type="button" class="on" data-u="kg">kg</button><button type="button" data-u="lb">lb</button></em>
      </div>
      <div class="sc"><i class="sc-ic">👥</i><b data-count="<?= $members ?>" data-fmt="c"><?= e(fmt_big($members)) ?></b><span class="sc-lab"><?= $members === 1 ? 'member' : 'members' ?></span></div>
      <div class="sc"><i class="sc-ic">💪</i><b data-count="<?= $lifters ?>" data-fmt="c"><?= e(fmt_big($lifters)) ?></b><span class="sc-lab"><?= $lifters === 1 ? 'lifter logging' : 'lifters logging' ?></span></div>
      <div class="sc"><i class="sc-ic">📋</i><b data-count="<?= $workouts ?>" data-fmt="c"><?= e(fmt_big($workouts)) ?></b><span class="sc-lab"><?= $workouts === 1 ? 'workout' : 'workouts' ?> logged</span></div>
      <div class="sc"><i class="sc-ic">🔁</i><b data-count="<?= $setsN ?>" data-fmt="c"><?= e(fmt_big($setsN)) ?></b><span class="sc-lab">gym sets</span></div>
      <div class="sc"><i class="sc-ic">🔥</i><b data-count="<?= $repsN ?>" data-fmt="c"><?= e(fmt_big($repsN)) ?></b><span class="sc-lab">gym reps</span></div>
      <?php if ($stepsN > 0): ?><div class="sc"><i class="sc-ic">👟</i><b data-count="<?= $stepsN ?>" data-fmt="c"><?= e(fmt_big($stepsN)) ?></b><span class="sc-lab">steps walked</span></div><?php endif; ?>
      <div class="sc"><i class="sc-ic">📈</i><b data-count="<?= (int)round($avgW * 10) ?>" data-fmt="d1"><?= e(number_format($avgW, 1)) ?></b><span class="sc-lab">workouts per lifter</span></div>
      <?php if ($popular): ?>
      <div class="sc sc-wide sc-pop"><span class="sc-lab">Popular beyond the gym</span>
        <div><?php foreach ($popular as $pp): ?><a><?= e($pp['name']) ?> <small><?= (int)$pp['n'] ?>×</small></a><?php endforeach; ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="h2-demo" id="demo" aria-hidden="true">
    <div class="mvstage" id="dstage"><?php foreach (['bench', 'squat', 'curl'] as $i => $m) echo fito_move($m, 150, 'mv' . ($i ? '' : ' on')); ?></div>
    <div class="dcard">
      <div class="dhead"><span>Push day</span><span class="dpts">+<b id="dpts">0</b> pts</span></div>
      <div class="dex">Bench Press</div>
      <div class="drow"><i>✓</i><span>60 kg × 8</span><small>+31</small></div>
      <div class="drow"><i>✓</i><span>70 kg × 6</span><small>+38</small></div>
      <div class="drow"><i>✓</i><span>75 kg × 5</span><small>+44 PR</small></div>
      <div class="dbar"><i id="dbar"></i></div>
      <div class="dlvl"><span>Level 7</span><span>Level 8</span></div>
    </div>
    <div class="dup" id="dup">LEVEL UP! 🎉</div>
  </div>
</section>

<section class="sect rv dressdemo" id="dressdemo">
  <h2>Dress your hero</h2>
  <p class="sub">Earn coins from every workout and spend them on gear. Try some of the outfits, wings and auras.</p>
  <div class="dd-wrap">
    <div class="dd-stage" id="ddstage"><div class="dd-layer on"></div><div class="dd-layer"></div><i class="dd-burst"></i></div>
    <div class="dd-pick" data-base="<?= e(url('fito-demo')) ?>">
      <h4>Outfit</h4>
      <div class="dd-row" data-k="o">
        <?php foreach (['wan' => 'Wanderer', 'iro' => 'Ironclad', 'cha' => 'Champion', 'myt' => 'Mythril', 'sto' => 'Stormforged', 'sam' => 'Samurai', 'vik' => 'Viking', 'mag' => 'Archmage', 'nin' => 'Shadow', 'pir' => 'Pirate', 'dra' => 'Dragonscale', 'rea' => 'Reaper'] as $id => $nm): ?>
          <button type="button" class="btn ghost small<?= $id === 'cha' ? ' on' : '' ?>" data-v="<?= $id ?>"><?= e($nm) ?></button>
        <?php endforeach; ?>
      </div>
      <h4>Wings</h4>
      <div class="dd-row" data-k="w">
        <button type="button" class="btn ghost small" data-v="">None</button>
        <button type="button" class="btn ghost small on" data-v="angel_gold">Golden angel</button>
        <button type="button" class="btn ghost small" data-v="bat_obsidian">Bat</button>
        <button type="button" class="btn ghost small" data-v="dragon_crimson">Dragon</button>
        <button type="button" class="btn ghost small" data-v="fairy_emerald">Fairy</button>
      </div>
      <h4>Aura</h4>
      <div class="dd-row" data-k="a">
        <button type="button" class="btn ghost small" data-v="">None</button>
        <button type="button" class="btn ghost small on" data-v="ring_gold">Halo ring</button>
        <button type="button" class="btn ghost small" data-v="flame_lava">Flame</button>
        <button type="button" class="btn ghost small" data-v="sparks_sakura">Sparkle</button>
        <button type="button" class="btn ghost small" data-v="glow_mythril">Glow</button>
      </div>
      <h4>Every muscle levels up</h4>
      <div class="dd-mus" id="ddmus">
        <?php foreach ([['Chest', 8], ['Back', 6], ['Shoulders', 5], ['Arms', 7], ['Core', 4], ['Legs', 9]] as [$mn, $ml]): ?>
          <div data-lv="<?= $ml ?>"><span><?= e($mn) ?></span><i><b></b></i><em>Lv <u><?= $ml ?></u></em><strong class="dd-lu">LEVEL UP</strong></div>
        <?php endforeach; ?>
      </div>
      <h4>Train every area consistently</h4>
      <div class="dd-week" id="ddweek">
        <div class="dd-dots"><?php foreach (['Chest', 'Back', 'Shoulders', 'Arms', 'Core', 'Legs'] as $mn): ?><span><?= e($mn) ?></span><?php endforeach; ?></div>
        <b class="dd-bonus">+<u id="ddbonus">0</u>% boss damage</b>
      </div>
      <p class="muted small" style="margin:.4rem 0 0">Every muscle you train levels up and unlocks stronger gear for that body part. Train all areas, week after week, for the biggest damage bonus.</p>
      <?php if ($open): ?><p style="margin:.6rem 0 0"><a class="btn" href="<?= e(url('register')) ?>">Create a free account</a></p><?php endif; ?>
    </div>
  </div>
</section>

<section class="sect rv landboss">
  <h2>This week's boss is waiting</h2>
  <p class="sub">Every workout anyone logs damages it. Level up to hit harder, share the loot, and team up with friends.</p>
  <div class="card gbig"><?php game_boss_card($landBoss, 'full'); ?></div>
  <p class="center"><a class="btn ghost" href="<?= e(url('boss')) ?>">See the damage leaderboard</a></p>
</section>

<section class="sect rv promo">
  <h2>See it in 47 seconds</h2>
  <p class="sub">Dress your hero, level every muscle, fight the boss and team up. Sound on for the beat.</p>
  <video controls preload="none" playsinline poster="<?= e(url('media/promo-poster.jpg')) ?>">
    <source src="<?= e(url('media/promo.mp4')) ?>" type="video/mp4">
  </video>
</section>

<?php if ($pub): ?>
<section class="sect rv">
  <h2>Happening now</h2>
  <p class="sub">Fresh workouts and posts from the community.</p>
  <div class="narrowcol"><?php render_feed_items($pub, null); ?></div>
</section>
<?php endif; ?>

<section class="features rv" aria-label="Features">
  <div class="card"><span class="fic">🎮</span><h3>Level up</h3><p>Every set earns points. Heavier lifts and harder work pay more, and personal records give bonus points.</p></div>
  <div class="card"><span class="fic">🏅</span><h3>Achievements &amp; quests</h3><p>Streaks, milestones, volume clubs and leg-day quests: there is always a next thing to unlock.</p></div>
  <div class="card"><span class="fic">👥</span><h3>Community</h3><p>Follow friends, give props, comment, join groups and climb the weekly leaderboard together.</p></div>
  <div class="card"><span class="fic">📲</span><h3>Works everywhere</h3><p>Use it in your browser or add it to your phone's home screen. Export your data any time.</p></div>
</section>

<section class="sect rv">
  <h2>Log a workout in seconds</h2>
  <p class="sub">Built for the gym floor: big buttons, pre-filled sets and nothing lost if you close the page.</p>
  <div class="steps">
    <div class="card"><h3>Pick an exercise</h3><p>Search hundreds of lifts, bodyweight moves, cardio and outdoor activities. Your usual exercises are one tap away.</p></div>
    <div class="card"><h3>Tap, tweak, tick</h3><p>Sets are pre-filled from your last session. Nudge weight and reps with the +/- buttons, add warm-ups, and tick each set when it's done.</p></div>
    <div class="card"><h3>Collect your points</h3><p>Finish the workout, watch your level bar fill, and share it with your followers if you like.</p></div>
  </div>
</section>

<section class="sect rv">
  <h2>Not just for the gym</h2>
  <p class="sub">Walks, hikes, runs, swims, yoga, sports and bodyweight sessions all earn points. If you moved, it counts.</p>
  <div class="band">
    <?= mascot('happy', 90) ?>
    <h3>Missing the old Fitocracy feeling?</h3>
    <p class="muted">See how <?= e($name) ?> brings back points, levels, achievements, quests and props.</p>
    <a class="btn ghost" href="<?= e(url('fitocracy-alternative')) ?>">The Fitocracy alternative</a>
  </div>
</section>

<?php $latest = blog_latest(3); if ($latest): ?>
<section class="sect rv">
  <h2>From the blog</h2>
  <div class="steps">
    <?php foreach ($latest as $bp): ?>
      <a class="card" href="<?= e(url('blog/' . $bp['slug'])) ?>"><h3><?= e($bp['title']) ?></h3><p><?= e($bp['excerpt']) ?></p></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="sect faq rv">
  <h2>Questions</h2>
  <?php foreach ($faq as $f): ?>
  <details><summary><?= e($f[0]) ?></summary><p><?= e($f[1]) ?></p></details>
  <?php endforeach; ?>
</section>

<?php if ($open): ?>
<div class="band">
  <?= mascot('cheer', 110) ?>
  <h2>Ready to start your streak?</h2>
  <p class="muted">Free to join. Your first workout is worth an achievement.</p>
  <a class="btn big" href="<?= e(url('register')) ?>">Create a free account</a>
</div>
<?php endif; ?>
<?php layout_footer('', ['home.js', 'game.js', 'landdress.js']); ?>
