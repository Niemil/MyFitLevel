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
        $cost = game_price_for($u, $key, $cat) < 0 ? 0 : (int)$cat[$key][5];
        $own = (int)val('SELECT COUNT(*) FROM game_items WHERE user_id = ? AND item = ?', [$uid, $key]);
        if ($own) {
            flash('You already own that.', 'err');
        } elseif ((int)$u['level'] < game_item_req($key)) {
            flash('You need level ' . game_item_req($key) . ' for that.', 'err');
        } elseif (($me = game_item_muscle_error($uid, $key)) !== null) {
            flash($me, 'err');
        } elseif ((int)$w['coins'] < $cost) {
            flash('Not enough coins yet. Keep training!', 'err');
        } else {
            if ($cost > 0) { game_coins_add($uid, -$cost, 'shop', $cat[$key][0]); }
            game_give_item($uid, $key, $cost > 0 ? 'shop' : 'staff');
            flash('Bought ' . trim($cat[$key][1] . ' ' . $cat[$key][0]) . '.');
        }
    } elseif ($do === 'equip' && isset($cat[$key])) {
        if ((int)$u['level'] < game_item_req($key)) {
            flash('You need level ' . game_item_req($key) . ' to wear that.', 'err');
        } elseif (($me = game_item_muscle_error($uid, $key)) !== null) {
            flash($me, 'err');
        } elseif ((int)val('SELECT COUNT(*) FROM game_items WHERE user_id = ? AND item = ?', [$uid, $key])) {
            $slot = $cat[$key][2];
            $same = array_keys(array_filter($cat, fn($i) => $i[2] === $slot));
            q('UPDATE game_items SET equipped = 0 WHERE user_id = ? AND item IN (' . in_clause($same) . ')', array_merge([$uid], $same));
            q('UPDATE game_items SET equipped = 1 WHERE user_id = ? AND item = ?', [$uid, $key]);
            flash('Equipped ' . $cat[$key][0] . '.');
        }
    } elseif ($do === 'wear_tried') {
        // wardrobe: buy whatever is missing (and affordable, and allowed) from the tried-on list, then wear it all
        $bought = 0; $worn = 0; $skipped = [];
        foreach (array_slice(array_unique(array_filter(explode(',', (string)($_POST['keys'] ?? '')))), 0, 12) as $k) {
            if (!isset($cat[$k])) { continue; }
            $has = (int)val('SELECT COUNT(*) FROM game_items WHERE user_id = ? AND item = ?', [$uid, $k]);
            if (!$has) {
                $w = game_wallet($uid);
                $cost = game_price_for($u, $k, $cat) < 0 ? 0 : (int)$cat[$k][5];
                if ($cat[$k][5] <= 0 || (int)$u['level'] < game_item_req($k) || game_item_muscle_error($uid, $k) !== null || (int)$w['coins'] < $cost) {
                    $skipped[] = $cat[$k][0];
                    continue;
                }
                if ($cost > 0) { game_coins_add($uid, -$cost, 'shop', $cat[$k][0]); }
                game_give_item($uid, $k, $cost > 0 ? 'shop' : 'staff');
                $bought++;
            } elseif ((int)$u['level'] < game_item_req($k) || game_item_muscle_error($uid, $k) !== null) {
                $skipped[] = $cat[$k][0];
                continue;
            }
            $slot = $cat[$k][2];
            $same = array_keys(array_filter($cat, fn($i) => $i[2] === $slot));
            q('UPDATE game_items SET equipped = 0 WHERE user_id = ? AND item IN (' . in_clause($same) . ')', array_merge([$uid], $same));
            q('UPDATE game_items SET equipped = 1 WHERE user_id = ? AND item = ?', [$uid, $k]);
            $worn++;
        }
        flash(($worn ? 'Now wearing ' . $worn . ' item' . ($worn > 1 ? 's' : '') . ($bought ? ' (bought ' . $bought . ')' : '') . '.' : 'Nothing could be worn.')
            . ($skipped ? ' Not possible yet: ' . implode(', ', $skipped) . ' (coins, level or muscle level).' : ''), $worn ? 'ok' : 'err');
    } elseif ($do === 'sell' && isset($cat[$key])) {
        $r = row('SELECT got_at, source FROM game_items WHERE user_id = ? AND item = ?', [$uid, $key]);
        if ($r && $r['source'] === 'shop' && $cat[$key][5] > 0) {
            $v = game_sell_value($key, (string)$r['got_at']);
            q('DELETE FROM game_items WHERE user_id = ? AND item = ?', [$uid, $key]);
            game_coins_add($uid, $v, 'shop', 'Sold ' . $cat[$key][0]);
            flash('Sold ' . $cat[$key][0] . ' for ' . $v . ' coins.');
        } else {
            flash('Only items you bought can be sold.', 'err');
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
    if ($back === 'wardrobe') {
        $bt = implode(',', array_filter(array_map(fn($k) => isset($cat[$k]) ? $k : '', explode(',', (string)($_POST['bt'] ?? '')))));
        redirect('wardrobe?s=' . preg_replace('/[^a-z_]/', '', (string)($_POST['bs'] ?? 'armor')) . ($bt !== '' ? '&t=' . $bt : '') . '#items');
    }
    redirect(in_array($back, ['feed', 'boss', 'shop'], true) ? $back : 'shop');
}

$w = game_wallet($uid);
$ownedRows = rows('SELECT item, equipped, source, got_at FROM game_items WHERE user_id = ?', [$uid]);
$owned = array_column($ownedRows, 'equipped', 'item');
$ownedBy = array_column($ownedRows, null, 'item');
$gear = game_gear($uid);
$boostPct = game_boost_active($w);
$hist = rows('SELECT * FROM game_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 8', [$uid]);
$slots = ['weapon' => 'Weapons', 'helmet' => 'Helmets', 'armor' => 'Armor', 'gloves' => 'Gloves', 'boots' => 'Boots', 'charm' => 'Charms', 'wings' => 'Wings', 'aura' => 'Auras', 'relic' => 'Boss relics', 'namefx' => 'Name effects', 'frame' => 'Avatar frames', 'banner' => 'Profile banners'];
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
<p><a href="<?= e(url('wardrobe')) ?>">Wardrobe and fitting room</a> <span class="muted small">· try on anything, and buy look-only outfits with no stats</span></p>
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
<?php
$cosSlots = ['namefx', 'frame', 'banner'];
$gearSlots = ['weapon' => 'Weapon', 'helmet' => 'Helmet', 'armor' => 'Armor', 'gloves' => 'Gloves', 'boots' => 'Boots', 'charm' => 'Charm', 'wings' => 'Wings', 'aura' => 'Aura'];
$wearing = $gear['by_slot'];
$setKeys = array_keys(game_set_items());

/** One item as a card with its button. */
$piece = function (string $k) use ($cat, $owned, $ownedBy, $wearing, $u, $w): void {
    [$n, $ic, $sl, $dm, $cn, $price, $fl] = $cat[$k];
    $has = isset($owned[$k]);
    $cur = $wearing[$sl] ?? null;
    $req = game_item_req($k);
    $locked = (int)$u['level'] < $req || game_item_muscle_error((int)$u['id'], $k) !== null;
    ?>
    <form method="post" class="gitem<?= $has && $owned[$k] ? ' on' : '' ?><?= $price === 0 ? ' rare' : '' ?>"><?= csrf_field() ?><input type="hidden" name="item" value="<?= e($k) ?>">
      <span class="gi-ic"><?= $ic === '' ? cosm_preview($k, $sl) : (in_array($sl, ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'charm', 'wings', 'aura'], true) ? gear_icon($k, 52) : e($ic)) ?></span><b><?= e($n) ?></b>
      <small><?= $ic === '' ? e($fl) : '' ?><?= $dm ? '+' . $dm . '% damage' : '' ?><?= $dm && $cn ? ' · ' : '' ?><?= $cn ? '+' . $cn . '% coins' : '' ?><?= $req > 1 ? ' · level ' . $req : '' ?><?= game_item_muscle_text($k) !== '' ? ' · ' . e(game_item_muscle_text($k)) : '' ?></small>
      <?php if (!$has && $cur && $cur !== $k && $ic !== ''):
          $cd = $cat[$cur][3]; $cc = $cat[$cur][4]; $dd = $dm - $cd; $dc = $cn - $cc; ?>
        <small class="muted">If worn instead of <?= e($cat[$cur][0]) ?>: <?= ($dd >= 0 ? '+' : '') . $dd ?>% damage, <?= ($dc >= 0 ? '+' : '') . $dc ?>% coins</small>
      <?php endif; ?>
      <?php if ($has): ?>
        <?php if ($owned[$k]): ?><input type="hidden" name="do" value="unequip"><button class="btn ghost small" type="submit">Worn ✔</button>
        <?php else: ?><input type="hidden" name="do" value="equip"><button class="btn small" type="submit" <?= $locked ? 'disabled' : '' ?>><?= $cur ? 'Swap in' : 'Wear' ?></button><?php endif; ?>
        <?php if (($ownedBy[$k]['source'] ?? '') === 'shop' && $price > 0): $sv = (int)game_sell_value($k, (string)$ownedBy[$k]['got_at']); ?><button class="btn ghost small" type="submit" name="do" value="sell" onclick="return confirm('Sell for <?= $sv ?> coins?')">Sell <?= $sv ?></button><?php endif; ?>
      <?php else: ?><input type="hidden" name="do" value="buy"><button class="btn small" type="submit" <?= (int)$w['coins'] < $price || $locked ? 'disabled' : '' ?>>🪙 <?= $price ?></button><?php endif; ?>
    </form>
    <?php
};
?>
<div class="card">
  <h3>Equipment</h3>
  <div class="eqgrid">
    <?php foreach ($gearSlots as $sl => $label): $k = $wearing[$sl] ?? null; ?>
      <div class="eqslot<?= $k ? '' : ' empty' ?>">
        <span class="eqic"><?= $k ? gear_icon($k, 44) : '' ?></span>
        <small class="muted"><?= e($label) ?></small>
        <b><?= $k ? e($cat[$k][0]) : 'Empty' ?></b>
      </div>
    <?php endforeach; ?>
  </div>
  <p style="margin-bottom:4px"><b>+<?= (int)$gear['dmg'] ?>% damage · +<?= (int)$gear['coin'] ?>% coins</b> <span class="muted small">from everything you wear</span></p>
  <?php foreach ($gear['set_bonus'] as $sb): ?><p class="small ok" style="margin:2px 0"><?= e($sb['set']) ?> set (<?= (int)$sb['n'] ?>/5 worn): <?= $sb['dmg'] ? '+' . (int)$sb['dmg'] . '% damage ' : '' ?><?= $sb['coin'] ? '+' . (int)$sb['coin'] . '% coins' : '' ?></p><?php endforeach; ?>
  <p class="muted small" style="margin-bottom:0">One item per slot, so items never stack. Matching set pieces give a bonus: 3 pieces a little, all 5 a lot.</p>
</div>

<?php foreach (game_set_defs() as $sid => $sdef):
    $cnt = 0; $wornN = 0;
    foreach ($sdef['pieces'] as $slot => $pp) { $kk = 'set_' . $sid . '_' . ['weapon' => 'w', 'helmet' => 'h', 'armor' => 'a', 'gloves' => 'g', 'boots' => 'b'][$slot]; if (isset($owned[$kk])) { $cnt++; } if (!empty($owned[$kk])) { $wornN++; } } ?>
<div class="card">
  <h3><?= e($sdef['name']) ?> set <small class="muted">· <?= e($sdef['theme']) ?> · <?= $cnt ?>/5 owned</small></h3>
  <p class="muted small" style="margin-top:0">3 pieces worn: <?= $sdef['b3'][0] || $sdef['b3'][1] ? ($sdef['b3'][0] ? '+' . $sdef['b3'][0] . '% damage ' : '') . ($sdef['b3'][1] ? '+' . $sdef['b3'][1] . '% coins' : '') : 'no bonus' ?> · all 5 worn: <?= ($sdef['b5'][0] ? '+' . $sdef['b5'][0] . '% damage ' : '') . ($sdef['b5'][1] ? '+' . $sdef['b5'][1] . '% coins' : '') ?></p>
  <div class="gshop">
    <?php foreach (['weapon' => 'w', 'armor' => 'a', 'helmet' => 'h', 'gloves' => 'g', 'boots' => 'b'] as $slot => $suf) { $piece('set_' . $sid . '_' . $suf); } ?>
  </div>
</div>
<?php endforeach; ?>

<?php
// Other gear you own: older items, boss drops and achievement items
$otherOwned = array_filter(array_keys($owned), fn($k) => isset($cat[$k]) && !in_array($cat[$k][2], $cosSlots, true) && !in_array($k, $setKeys, true) && strpos($k, 'look_') !== 0);
if ($otherOwned): ?>
<div class="card">
  <h3>Other gear you own</h3>
  <div class="gshop"><?php foreach ($otherOwned as $k) { $piece($k); } ?></div>
</div>
<?php endif; ?>

<?php foreach ($cosSlots as $slot): $label = $slots[$slot]; ?>
<div class="card">
  <h3><?= e($label) ?></h3>
  <div class="gshop"><?php foreach ($cat as $k => $i2) { if ($i2[2] === $slot) { $piece($k); } } ?></div>
</div>
<?php endforeach; ?>
<p class="muted small">Rare boss drops, relics and advent treasures show up here once you win them, and they cannot be bought.</p>

<?php if ($hist): ?>
<div class="card"><h4>Recent coins</h4>
  <?php foreach ($hist as $h): ?><div class="gb-row"><span class="grow small"><?= e($h['note'] ?: $h['kind']) ?></span><b class="<?= (int)$h['amount'] >= 0 ? 'ok' : '' ?>"><?= (int)$h['amount'] >= 0 ? '+' : '' ?><?= (int)$h['amount'] ?></b><span class="muted small"><?= e(rel_time((string)$h['created_at'])) ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>
<script src="<?= e(asset('keepscroll.js')) ?>" defer></script>
<?php layout_footer(); ?>
