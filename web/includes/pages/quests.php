<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
ql_ensure_tables();
$lines = quest_lines();
$lvl = (int)$u['level'];
$bw = $u['bodyweight_kg'] !== null ? (float)$u['bodyweight_kg'] : null;
const QL_MAX_ACTIVE = 3;

if (is_post()) {
    csrf_check();
    $code = (string)($_POST['quest'] ?? '');
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'start' && isset($lines[$code])) {
        $active = (int)val('SELECT COUNT(*) FROM quest_runs WHERE user_id = ? AND finished_at IS NULL', [$uid]);
        $exists = row('SELECT 1 FROM quest_runs WHERE user_id = ? AND quest = ?', [$uid, $code]);
        if ($exists) {
            flash('You already started that quest.', 'err');
        } elseif ($lvl < $lines[$code][2]) {
            flash('That quest unlocks at level ' . $lines[$code][2] . '.', 'err');
        } elseif ($active >= QL_MAX_ACTIVE) {
            flash('You can run ' . QL_MAX_ACTIVE . ' quests at once. Finish or abandon one first.', 'err');
        } else {
            q('INSERT INTO quest_runs (user_id, quest, started_at) VALUES (?,?,?)', [$uid, $code, now()]);
            flash('Quest started: ' . $lines[$code][0] . '. Your next workout counts.');
        }
    } elseif ($do === 'abandon' && isset($lines[$code]) && row('SELECT 1 FROM quest_runs WHERE user_id = ? AND quest = ? AND finished_at IS NULL', [$uid, $code])) {
        q('DELETE FROM quest_runs WHERE user_id = ? AND quest = ? AND finished_at IS NULL', [$uid, $code]);
        q('DELETE FROM quest_steps WHERE user_id = ? AND quest = ? AND step < 99', [$uid, $code]);
        recalc_xp($uid);
        flash('Quest abandoned. Points already earned from its steps were removed.');
    }
    redirect('quests');
}

$ql = ql_check($uid);   // catches up on anything done since the last visit
if ($ql['steps'] || $ql['quests']) {
    recalc_xp($uid);
}
$runs = [];
foreach (rows('SELECT * FROM quest_runs WHERE user_id = ?', [$uid]) as $r) {
    $runs[$r['quest']] = $r;
}
$stepsDone = [];
foreach (rows('SELECT quest, step FROM quest_steps WHERE user_id = ? AND quest <> ?', [$uid, 'challenge']) as $r) {
    $stepsDone[$r['quest']][(int)$r['step']] = true;
}

$running = $available = $locked = $finished = [];
foreach ($lines as $code => $d) {
    $run = $runs[$code] ?? null;
    if ($run && $run['finished_at']) {
        $finished[$code] = $run;
    } elseif ($run) {
        $running[$code] = $run;
    } elseif ($lvl >= $d[2]) {
        $available[$code] = null;
    } else {
        $locked[$code] = null;
    }
}
layout_header('Quests', 'quests');
?>
<h1 class="pagetitle">Quests</h1>
<p class="muted">Start a quest, then follow its steps in your normal workouts. Each step pays points and the whole quest pays a bonus. Run up to <?= QL_MAX_ACTIVE ?> at a time. Counter-style goals such as "log 100 sets" are under <a href="<?= e(url('badges')) ?>">Achievements</a>.</p>

<?php if ($running): ?><h3>In progress</h3><?php endif; ?>
<?php foreach ($running as $code => $run): [$name, $story, $min, $icon, $steps, $bonus] = $lines[$code]; $n = count($stepsDone[$code] ?? []); ?>
<div class="card qline">
  <div class="qltop"><span class="qlicon"><?= $icon ?></span>
    <div class="grow"><h4><?= e($name) ?></h4><div class="muted small"><?= e($story) ?></div></div>
    <span class="reward">+<?= (int)$bonus ?> bonus</span></div>
  <ol class="qsteps">
  <?php foreach ($steps as $i => [$task, $cond, $reward]):
      $isDone = isset($stepsDone[$code][$i]); $isCur = !$isDone && $i === $n;
      $prog = '';
      if ($isCur) { [$cur, $need] = ql_value($uid, $cond, $run['started_at'], $bw);
          $prog = $cond['t'] === 'ratio' && !$bw ? 'Add your bodyweight in Settings' : fmt_num(min($cur, $need), $cond['t'] === 'ratio' ? 2 : 0) . ' / ' . fmt_num($need, $cond['t'] === 'ratio' ? 2 : 0); } ?>
    <li class="<?= $isDone ? 'sdone' : ($isCur ? 'scur' : 'swait') ?>">
      <span class="sdot"><?= $isDone ? '✓' : ($i + 1) ?></span>
      <div class="grow"><?= e($task) ?><?php if ($isCur): ?><div class="xpbar"><i style="width:<?= (int)min(100, floor($cur / max(0.01, $need) * 100)) ?>%"></i></div><div class="xpnote"><?= e($prog) ?></div><?php endif; ?></div>
      <span class="reward">+<?= (int)$reward ?></span>
    </li>
  <?php endforeach; ?>
  </ol>
  <form method="post" data-confirm="Abandon this quest? Points from its finished steps are removed."><?= csrf_field() ?><input type="hidden" name="quest" value="<?= e($code) ?>"><button class="btn ghost small danger" name="do" value="abandon" type="submit">Abandon</button></form>
</div>
<?php endforeach; ?>

<?php if ($available): ?><h3>Ready to start</h3><?php endif; ?>
<div class="questgrid">
<?php foreach ($available as $code => $x): [$name, $story, $min, $icon, $steps, $bonus] = $lines[$code]; $total = array_sum(array_column($steps, 2)) + $bonus; ?>
  <div class="card quest">
    <div class="qtop"><h4><?= $icon ?> <?= e($name) ?></h4><span class="reward">up to +<?= (int)$total ?></span></div>
    <p class="muted small"><?= e($story) ?></p>
    <ul class="qpreview"><?php foreach ($steps as $s): ?><li><?= e($s[0]) ?></li><?php endforeach; ?></ul>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="quest" value="<?= e($code) ?>"><button class="btn small" name="do" value="start" type="submit">Start quest</button></form>
  </div>
<?php endforeach; ?>
</div>

<?php if ($locked): ?><h3>Locked</h3><?php endif; ?>
<div class="questgrid">
<?php foreach ($locked as $code => $x): [$name, $story, $min, $icon] = $lines[$code]; ?>
  <div class="card quest lockq"><div class="qtop"><h4>🔒 <?= e($name) ?></h4><span class="reward">Level <?= (int)$min ?></span></div><p class="muted small"><?= e($story) ?></p></div>
<?php endforeach; ?>
</div>

<?php if ($finished): ?><h3>Completed (<?= count($finished) ?>)</h3>
<div class="questgrid">
<?php foreach ($finished as $code => $run): [$name, $story, , $icon] = $lines[$code]; ?>
  <div class="card quest doneq"><div class="qtop"><h4>✅ <?= e($name) ?></h4></div><p class="muted small">Finished <?= e(date('j M Y', strtotime($run['finished_at']))) ?></p></div>
<?php endforeach; ?>
</div><?php endif; ?>
<?php layout_footer('quests'); ?>
