<?php
declare(strict_types=1);

game_ensure();
$u = me();

if ($path === 'boss/state') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $tid = isset($_GET['team']) ? (int)$_GET['team'] : 0;
    $b = $tid ? row("SELECT * FROM bosses WHERE team_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1", [$tid])
              : row("SELECT * FROM bosses WHERE team_id IS NULL AND status = 'active' ORDER BY id DESC LIMIT 1");
    echo json_encode($b ? ['id' => (int)$b['id'], 'hp' => (int)$b['hp'], 'max' => (int)$b['max_hp'], 'left' => game_fmt_left((string)$b['ends_at'])] : ['id' => 0]);
    exit;
}

$b = game_boss_public();
$mine = ($b && $u) ? (int)val('SELECT COALESCE(damage,0) FROM boss_hits WHERE boss_id = ? AND user_id = ?', [$b['id'], $u['id']]) : null;
$top = $b ? rows('SELECT h.user_id, h.damage, h.hits, us.username, us.display_name, us.level FROM boss_hits h JOIN users us ON us.id = h.user_id
                  WHERE h.boss_id = ? AND h.damage > 0 ORDER BY h.damage DESC LIMIT 25', [$b['id']]) : [];
$total = $top ? max(1, (int)val('SELECT SUM(damage) FROM boss_hits WHERE boss_id = ?', [$b['id']])) : 1;
$icons = game_icons_for(array_map(fn($r) => (int)$r['user_id'], $top));
$fallen = rows("SELECT b.*, (SELECT COUNT(*) FROM boss_hits h WHERE h.boss_id = b.id AND h.damage > 0) AS heroes FROM bosses b WHERE b.team_id IS NULL AND b.status = 'dead' ORDER BY b.id DESC LIMIT 6");
$roster = $b ? game_roster_by_key((string)$b['boss_key']) : null;
$cat = game_items();
$drop = $roster ? ($cat[$roster[3]] ?? null) : null;
$w = $u ? game_wallet((int)$u['id']) : null;
$d = $u ? game_damage_for($u, 100) : null;
$partic = $b ? (int)val('SELECT COUNT(*) FROM boss_hits WHERE boss_id = ? AND damage > 0', [$b['id']]) : 0;
$poolNow = min(500, 100 + 20 * max(1, $partic));

define('GAME_CSS', true);
layout_header('Weekly boss', 'quests', ['index' => true, 'path' => 'boss', 'desc' => 'Every workout logged on ' . site_name() . ' damages the weekly boss. Join in, share the loot and level up together.']);
?>
<h1 class="pagetitle">⚔️ Weekly boss</h1>
<p class="muted">Every workout you log hits the boss automatically. Higher levels hit harder, and everyone who helps shares the prize and has a chance at its unique loot.</p>

<div class="card gbig">
  <?php game_boss_card($b, 'full', $mine); ?>
  <?php if ($b): ?>
  <div class="gb-facts">
    <div><b>🪙 <?= $poolNow ?>+</b><span>prize pool, split by damage</span></div>
    <div><b><?= $drop ? e($drop[1]) . ' ' . e($drop[0]) : '–' ?></b><span>unique drop, bigger share = better odds</span></div>
    <div><b><?= $partic ?></b><span><?= $partic === 1 ? 'hero' : 'heroes' ?> fighting</span></div>
  </div>
  <?php endif; ?>
  <?php if ($u && $d): ?>
  <p class="small muted" style="margin:.8rem 0 0">Your hits: <b>×<?= number_format($d['level_mult'], 2) ?></b> from level<?= $d['gear'] ? ', <b>+' . $d['gear'] . '%</b> from gear' : '' ?><?= $d['boost'] ? ', <b>+' . $d['boost'] . '%</b> from your potion' : '' ?><?= !empty($d['team']) ? ', <b>+' . $d['team'] . '%</b> from your team' : '' ?>.
    A 100-point workout does about <b><?= number_format($d['dmg']) ?></b> damage. <a href="<?= e(url('shop')) ?>">Gear &amp; potions</a></p>
  <?php elseif (!$u): ?>
  <p class="center" style="margin:1rem 0 0"><a class="btn big" href="<?= e(url('register')) ?>">Join free and hit the boss</a></p>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Damage leaderboard</h3>
  <?php if (!$top): ?><p class="muted">Nobody has hit it yet. Be the first: log a workout.</p><?php endif; ?>
  <?php foreach ($top as $i => $r): $share = (int)round($r['damage'] / $total * 100); ?>
    <div class="gb-row<?= $u && (int)$u['id'] === (int)$r['user_id'] ? ' me' : '' ?>">
      <span class="gb-rank"><?= $i + 1 ?></span>
      <a class="grow" href="<?= e(url('u/' . $r['username'])) ?>"><?= e(display_name($r)) ?> <span class="gb-gear"><?= e($icons[(int)$r['user_id']] ?? '') ?></span> <small class="muted">Lv <?= (int)$r['level'] ?></small></a>
      <span class="gb-dmg"><b><?= number_format((int)$r['damage']) ?></b> <small class="muted"><?= $share ?>%</small></span>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($b) { game_boss_log_card((int)$b['id'], $u ? (int)$u['id'] : null); } ?>
<?php game_event_banner(); ?>
<?php if ($u) { game_advent_card((int)$u['id'], 'boss'); game_chest_card((int)$u['id']); } ?>
<?php
$seasonKey = date('Y-m');
$seasonTop = game_season_top_players($seasonKey, 10);
$daysLeft = max(0, (int)ceil((strtotime(date('Y-m-01', strtotime('first day of next month'))) - time()) / 86400));
$lastKey = date('Y-m', strtotime('first day of last month'));
$winners = rows('SELECT gt.title, us.username, us.display_name FROM game_titles gt JOIN users us ON us.id = gt.user_id WHERE gt.season = ? AND gt.title NOT LIKE ? ORDER BY gt.id ASC LIMIT 3', [$lastKey, 'Champion Team%']);
?>
<div class="card">
  <h3>🏆 <?= e(date('F')) ?> season</h3>
  <p class="muted small" style="margin-top:0">Total boss damage this month. <?= $daysLeft ?> day<?= $daysLeft === 1 ? '' : 's' ?> left. Top 3 players win a title and a rare item (#1 gets the Champion's Helm), and the best team (points per member) wins 1,200 coins for its vault.</p>
  <?php if (!$seasonTop): ?><p class="muted">No damage yet this month.</p><?php endif; ?>
  <?php foreach ($seasonTop as $i => $r): ?>
    <div class="gb-row<?= $u && (int)$u['id'] === (int)$r['user_id'] ? ' me' : '' ?>"><span class="gb-rank"><?= ['🥇', '🥈', '🥉'][$i] ?? $i + 1 ?></span>
      <a class="grow" href="<?= e(url('u/' . $r['username'])) ?>"><?= e(display_name($r)) ?></a><b><?= number_format((int)$r['dmg']) ?></b></div>
  <?php endforeach; ?>
  <?php if ($winners): ?><p class="muted small" style="margin-bottom:0">Last season: <?php foreach ($winners as $i => $wn): ?><?= $i ? ', ' : '' ?><?= e(display_name($wn)) ?> (<?= e($wn['title']) ?>)<?php endforeach; ?></p><?php endif; ?>
</div>
<div class="card"><?php game_news_card(10); ?></div>
<?php if ($fallen): ?>
<div class="card">
  <h3>Hall of fame</h3>
  <?php foreach ($fallen as $f): $fr = game_roster_by_key((string)$f['boss_key']); ?>
    <div class="gb-row"><span class="gb-rank"><?= e($fr[2]) ?></span><span class="grow"><?= e($f['name']) ?></span>
      <span class="muted small"><?= (int)$f['heroes'] ?> heroes · <?= e(rel_time((string)$f['ended_at'])) ?></span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <h3>How it works</h3>
  <ul class="gb-how">
    <li><b>Damage</b> = the points of each workout × a level bonus (level 20 hits twice as hard as level 0, level 40 three times) × your gear and potion.</li>
    <li><b>Prize</b>: when the boss falls, the coin pool is split by your share of the damage. Everyone who hit it gets paid.</li>
    <li><b>Loot</b>: each hero rolls for the boss's unique item. A bigger share means better odds, and a small share still has a real chance.</li>
    <li><b>Legendary relic</b>: every boss kill has a 1% chance to drop a permanent relic (+10% to +20% damage or coins, better from tougher bosses) for ONE hero, picked by damage share. Solo, that is a 1% chance; on average one relic per 100 bosses.</li>
    <li><b>Advent</b>: in December a calendar gives everyone the same gift each day, but only on that day. Doors 20 to 24 hold permanent treasures like wings and auras.</li>
    <li><b>Coins</b> also come from workouts (the first two each day), team quests and streaks. Spend them in the <a href="<?= e(url('shop')) ?>">shop</a>.</li>
    <li>A boss that escapes at the end of the week still pays a small consolation prize.</li>
  </ul>
</div>
<?php layout_footer('', ['game.js']); ?>
