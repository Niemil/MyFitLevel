<?php
declare(strict_types=1);

/* Wardrobe and fitting room: try on anything, buy look-only outfits, wear them over your stat gear. */
$u = require_login();
$uid = (int)$u['id'];
game_ensure();
staff_ensure();
define('GAME_CSS', true);
$cat = game_items();
$w = game_wallet($uid);
$slotNames = ['weapon' => 'Weapon', 'helmet' => 'Helmet', 'armor' => 'Armor', 'gloves' => 'Gloves', 'boots' => 'Boots', 'charm' => 'Charm', 'wings' => 'Wings', 'aura' => 'Aura'];
$s = isset($slotNames[(string)($_GET['s'] ?? '')]) ? (string)$_GET['s'] : 'armor';

// items being tried on: one per slot, the last one wins
$try = [];
foreach (array_slice(array_filter(explode(',', (string)($_GET['t'] ?? ''))), 0, 12) as $k) {
    if (isset($cat[$k])) {
        $try[preg_replace('/^look_/', '', (string)$cat[$k][2])] = $k;
    }
}
$tryKeys = array_values($try);
$look = fito_look($uid);
$shown = fito_try_on($look, $tryKeys);
$tryUrl = function (string $key) use ($try, $cat, $s): string {
    $slot = preg_replace('/^look_/', '', (string)$cat[$key][2]);
    $n = $try;
    if (($n[$slot] ?? '') === $key) { unset($n[$slot]); } else { $n[$slot] = $key; }
    return url('wardrobe?s=' . $s . ($n ? '&t=' . implode(',', $n) : ''));
};

$ownedRows = rows('SELECT item, equipped FROM game_items WHERE user_id = ?', [$uid]);
$owned = array_column($ownedRows, 'equipped', 'item');
$dm = 0; $cn = 0;
foreach ($tryKeys as $k) { if (strpos($k, 'look_') !== 0) { $dm += $cat[$k][3]; $cn += $cat[$k][4]; } }

$buyCost = 0; $wearN = 0;
foreach ($tryKeys as $k) {
    $on = (int)val('SELECT equipped FROM game_items WHERE user_id = ? AND item = ?', [$uid, $k]);
    $has = (int)val('SELECT COUNT(*) FROM game_items WHERE user_id = ? AND item = ?', [$uid, $k]);
    if (!$has && $cat[$k][5] > 0) { $buyCost += (int)$cat[$k][5]; } elseif ($has && !$on) { $wearN++; }
}
$stat = []; $looks = [];
foreach ($cat as $k => $i) {
    $slot = (string)$i[2];
    if ($slot === $s && ($i[5] > 0 || isset($owned[$k]))) { $stat[$k] = $i; }
    if ($slot === 'look_' . $s) { $looks[$k] = $i; }
}
uasort($stat, fn($a, $b) => [game_item_tier_of($a), $a[5]] <=> [game_item_tier_of($b), $b[5]]);
function game_item_tier_of(array $i): int { return (int)($i[7] ?? 0); }

$tile = function (string $k) use ($cat, $owned, $w, $u, $uid, $tryUrl, $try, $s): void {
    [$n, $ic, $sl, $dmg, $coin, $price, $fl] = $cat[$k];
    $isLook = strpos($sl, 'look_') === 0;
    $has = isset($owned[$k]);
    $req = game_item_req($k);
    $mt = game_item_muscle_text($k);
    $locked = (int)$u['level'] < $req || game_item_muscle_error($uid, $k) !== null;
    $trying = in_array($k, $try, true);
    ?>
    <form method="post" action="<?= e(url('shop')) ?>" class="gitem<?= $has && $owned[$k] ? ' on' : '' ?><?= $trying ? ' on' : '' ?>"><?= csrf_field() ?>
      <input type="hidden" name="item" value="<?= e($k) ?>"><input type="hidden" name="back" value="wardrobe"><input type="hidden" name="bs" value="<?= e($s) ?>"><input type="hidden" name="bt" value="<?= e(implode(',', $try)) ?>">
      <span class="gi-ic"><?= gear_icon($k, 52) ?></span><b><?= e($n) ?></b>
      <small><?= $isLook ? 'Look only, no bonus' : ($dmg ? '+' . $dmg . '% damage' : '') . ($dmg && $coin ? ' · ' : '') . ($coin ? '+' . $coin . '% coins' : '') ?><?= $req > 1 ? ' · level ' . $req : '' ?><?= $mt !== '' ? ' · ' . e($mt) : '' ?></small>
      <a class="btn ghost small" href="<?= e($tryUrl($k)) ?>"><?= $trying ? 'Take off' : 'Try on' ?></a>
      <?php if ($has): ?>
        <?php if ($owned[$k]): ?><input type="hidden" name="do" value="unequip"><button class="btn ghost small" type="submit">Worn, take off</button>
        <?php else: ?><input type="hidden" name="do" value="equip"><button class="btn small" type="submit" <?= $locked ? 'disabled' : '' ?>>Wear</button><?php endif; ?>
      <?php elseif ($price > 0): ?><input type="hidden" name="do" value="buy"><button class="btn small" type="submit" <?= (int)$w['coins'] < $price || $locked ? 'disabled' : '' ?>>🪙 <?= $price ?></button>
        <?php if ($locked): ?><small class="muted">Locked, but you can still try it on</small><?php endif; ?>
      <?php endif; ?>
    </form>
    <?php
};

layout_header('Wardrobe', 'me');
?>
<h1 class="pagetitle">Wardrobe</h1>
<p class="muted">Try on anything, even gear you cannot buy yet. Look-only items cover how your stat gear looks, give no bonus and need no level. <a href="<?= e(url('character')) ?>">Back to your character</a></p>
<div class="card charcard" id="wpreview">
  <button type="button" class="btn ghost small" id="wsticky" style="float:right">Keep on screen</button>
  <div class="charstage"><?= fito_dressed($shown, 300) ?></div>
  <div class="charmood">
    <?php if ($tryKeys): ?>
      <span class="muted small">Trying on</span>
      <p class="small" style="margin:2px 0"><?= e(implode(', ', array_map(fn($k) => $cat[$k][0], $tryKeys))) ?></p>
      <?php if ($dm || $cn): ?><p class="small" style="margin:2px 0">Stat items tried would give <b>+<?= $dm ?>% damage<?= $cn ? ', +' . $cn . '% coins' : '' ?></b></p><?php endif; ?>
      <?php if ($buyCost > 0 || $wearN > 0): ?>
      <form method="post" action="<?= e(url('shop')) ?>" style="margin:6px 0"><?= csrf_field() ?><input type="hidden" name="do" value="wear_tried"><input type="hidden" name="keys" value="<?= e(implode(',', $tryKeys)) ?>"><input type="hidden" name="back" value="wardrobe"><input type="hidden" name="bs" value="<?= e($s) ?>"><input type="hidden" name="bt" value="<?= e(implode(',', $tryKeys)) ?>">
        <button class="btn small" type="submit"><?= $buyCost > 0 ? 'Buy and wear all (' . number_format($buyCost) . ' coins)' : 'Wear all of this' ?></button></form>
      <?php endif; ?>
      <a class="btn ghost small" href="<?= e(url('wardrobe?s=' . $s)) ?>">Back to what I wear</a>
    <?php else: ?><span class="muted small">This is how you look now. Pick something and press Try on.</span><?php endif; ?>
    <p class="small" style="margin:8px 0 0"><b><?= number_format((int)$w['coins']) ?></b> coins</p>
  </div>
</div>
<div class="card" id="items">
  <p class="sx-filter small" style="margin-top:0">
    <?php foreach ($slotNames as $k => $l): ?><a class="<?= $s === $k ? 'on' : '' ?>" href="<?= e(url('wardrobe?s=' . $k . ($tryKeys ? '&t=' . implode(',', $tryKeys) : ''))) ?>"><?= e($l) ?></a><?php endforeach; ?>
  </p>
  <h3>Look only</h3>
  <div class="gshop"><?php foreach ($looks as $k => $i) { $tile($k); } if (!$looks) { echo '<p class="muted small">No look-only items for this slot yet.</p>'; } ?></div>
</div>
<div class="card">
  <h3>Gear with bonuses</h3>
  <div class="gshop"><?php foreach ($stat as $k => $i) { $tile($k); } if (!$stat) { echo '<p class="muted small">Nothing here yet.</p>'; } ?></div>
</div>
<?php layout_footer('', ['wardrobe.js']); ?>
