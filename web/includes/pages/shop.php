<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
game_ensure();
$cat = game_items();
$boosts = game_boosts();

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $key = (string)($_POST['item'] ?? '');
    $w = game_wallet($uid);
    if ($do === 'buy' && isset($cat[$key]) && $cat[$key][5] > 0) {
        $own = (int)val('SELECT COUNT(*) FROM game_items WHERE user_id = ? AND item = ?', [$uid, $key]);
        if ($own) {
            flash('You already own that.', 'err');
        } elseif ((int)$w['coins'] < $cat[$key][5]) {
            flash('Not enough coins yet. Keep training!', 'err');
        } else {
            game_coins_add($uid, -$cat[$key][5], 'shop', $cat[$key][0]);
            game_give_item($uid, $key, 'shop');
            flash('Bought ' . $cat[$key][1] . ' ' . $cat[$key][0] . '.');
        }
    } elseif ($do === 'equip' && isset($cat[$key])) {
        if ((int)val('SELECT COUNT(*) FROM game_items WHERE user_id = ? AND item = ?', [$uid, $key])) {
            $slot = $cat[$key][2];
            $same = array_keys(array_filter($cat, fn($i) => $i[2] === $slot));
            q('UPDATE game_items SET equipped = 0 WHERE user_id = ? AND item IN (' . in_clause($same) . ')', array_merge([$uid], $same));
            q('UPDATE game_items SET equipped = 1 WHERE user_id = ? AND item = ?', [$uid, $key]);
            flash('Equipped ' . $cat[$key][0] . '.');
        }
    } elseif ($do === 'unequip' && isset($cat[$key])) {
        q('UPDATE game_items SET equipped = 0 WHERE user_id = ? AND item = ?', [$uid, $key]);
    } elseif ($do === 'chest') {
        $got = game_chest_claim($uid);
        flash($got ? '🎁 Chest opened: +' . $got . ' coins!' : 'Today\'s chest is already opened.', $got ? 'ok' : 'err');
    } elseif ($do === 'advent') {
        $m = game_advent_open($uid);
        flash($m !== '' ? $m : 'That door is not available (already opened, or it is not December).', $m !== '' ? 'ok' : 'err');
    } elseif ($do === 'boost' && isset($boosts[$key])) {
        [$bn, $bi, $pct, $hrs, $price, $kind] = $boosts[$key];
        if (($kind === 'coin' ? game_coin_boost_active($w) : game_boost_active($w)) >= $pct) {
            flash('You already have a potion at least that strong running.', 'err');
        } elseif ((int)$w['coins'] < $price) {
            flash('Not enough coins yet.', 'err');
        } else {
            game_coins_add($uid, -$price, 'shop', $bn);
            $col = $kind === 'coin' ? 'coin_boost' : 'boost';
            q("UPDATE game_wallet SET {$col}_pct = ?, {$col}_until = ? WHERE user_id = ?", [$pct, date('Y-m-d H:i:s', time() + $hrs * 3600), $uid]);
            flash($bi . ' ' . $bn . ' active: +' . $pct . '% ' . ($kind === 'coin' ? 'coins' : 'damage') . ' for ' . ($hrs >= 48 ? intdiv($hrs, 24) . ' days' : $hrs . ' hours') . '.');
        }
    }
    $back = (string)($_POST['back'] ?? '');
    redirect(in_array($back, ['feed', 'boss', 'shop'], true) ? $back : 'shop');
}

$w = game_wallet($uid);
$owned = array_column(rows('SELECT item, equipped FROM game_items WHERE user_id = ?', [$uid]), 'equipped', 'item');
$gear = game_gear($uid);
$boostPct = game_boost_active($w);
$hist = rows('SELECT * FROM game_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 8', [$uid]);
$slots = ['weapon' => 'Weapons', 'helmet' => 'Helmets', 'armor' => 'Armor', 'gloves' => 'Gloves', 'boots' => 'Boots', 'charm' => 'Charms', 'wings' => 'Wings', 'aura' => 'Auras', 'relic' => 'Boss relics'];
$coinBoost = game_coin_boost_active($w);

define('GAME_CSS', true);
layout_header('Shop', 'quests');
?>
<h1 class="pagetitle">🪙 Shop &amp; gear</h1>
<div class="card gwallet">
  <div><b><?= number_format((int)$w['coins']) ?></b><span>coins</span></div>
  <div><b>+<?= $gear['dmg'] + $boostPct ?>%</b><span>damage from gear<?= $boostPct ? ' &amp; potion' : '' ?></span></div>
  <div><b><?= number_format((int)$w['dmg_total']) ?></b><span>total damage</span></div>
  <div><b><?= (int)$w['kills'] ?></b><span>bosses helped down</span></div>
</div>
<p class="muted">Coins come from your first two workouts each day, team quests and boss prizes. Gear is permanent. Level still matters most: a high level hits much harder than any item.</p>

<?php game_chest_card($uid); ?>
<div class="card">
  <h3>Potions</h3>
  <?php if ($boostPct): ?><p class="ok">Active: +<?= $boostPct ?>% damage until <?= e(date('M j H:i', strtotime((string)$w['boost_until']))) ?>.</p><?php endif; ?>
  <div class="gshop">
  <?php if ($coinBoost): ?><p class="ok">Active: +<?= $coinBoost ?>% coins until <?= e(date('M j H:i', strtotime((string)$w['coin_boost_until']))) ?>.</p><?php endif; ?>
  <?php foreach ($boosts as $k => [$bn, $bi, $pct, $hrs, $price, $kind]): ?>
    <form method="post" class="gitem"><?= csrf_field() ?><input type="hidden" name="do" value="boost"><input type="hidden" name="item" value="<?= e($k) ?>">
      <span class="gi-ic"><?= e($bi) ?></span><b><?= e($bn) ?></b><small>+<?= $pct ?>% <?= $kind === 'coin' ? 'coins' : 'damage' ?> for <?= $hrs >= 48 ? intdiv($hrs, 24) . ' days' : $hrs . 'h' ?></small>
      <button class="btn small" type="submit" <?= (int)$w['coins'] < $price ? 'disabled' : '' ?>>🪙 <?= $price ?></button></form>
  <?php endforeach; ?>
  </div>
</div>

<?php game_advent_card($uid, 'shop'); ?>
<?php foreach ($slots as $slot => $label):
    $any = false;
    foreach ($cat as $k2 => $i2) { if ($i2[2] === $slot && ($i2[5] > 0 || isset($owned[$k2]))) { $any = true; break; } }
    if (!$any) { continue; } ?>
<div class="card">
  <h3><?= e($label) ?></h3>
  <div class="gshop">
  <?php foreach ($cat as $k => [$n, $ic, $sl, $dm, $cn, $price, $fl]): if ($sl !== $slot) { continue; }
      $has = isset($owned[$k]); if (!$has && $price === 0) { continue; } ?>
    <form method="post" class="gitem<?= $has && $owned[$k] ? ' on' : '' ?><?= $price === 0 ? ' rare' : '' ?>"><?= csrf_field() ?><input type="hidden" name="item" value="<?= e($k) ?>">
      <span class="gi-ic"><?= e($ic) ?></span><b><?= e($n) ?></b>
      <small><?= $dm ? '+' . $dm . '% damage' : '' ?><?= $dm && $cn ? ' · ' : '' ?><?= $cn ? '+' . $cn . '% coins' : '' ?></small>
      <?php if ($has): ?>
        <?php if ($owned[$k]): ?><input type="hidden" name="do" value="unequip"><button class="btn ghost small" type="submit">Equipped ✔</button>
        <?php else: ?><input type="hidden" name="do" value="equip"><button class="btn small" type="submit">Equip</button><?php endif; ?>
      <?php else: ?><input type="hidden" name="do" value="buy"><button class="btn small" type="submit" <?= (int)$w['coins'] < $price ? 'disabled' : '' ?>>🪙 <?= $price ?></button><?php endif; ?>
    </form>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
<p class="muted small">Rare boss drops, relics and advent treasures show up here once you win them, and they cannot be bought.</p>

<?php if ($hist): ?>
<div class="card"><h4>Recent coins</h4>
  <?php foreach ($hist as $h): ?><div class="gb-row"><span class="grow small"><?= e($h['note'] ?: $h['kind']) ?></span><b class="<?= (int)$h['amount'] >= 0 ? 'ok' : '' ?>"><?= (int)$h['amount'] >= 0 ? '+' : '' ?><?= (int)$h['amount'] ?></b><span class="muted small"><?= e(rel_time((string)$h['created_at'])) ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>
<?php layout_footer(); ?>
