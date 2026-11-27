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
    if (isset($cat[$k]) && (!fa_staff_only($k) || is_staff($u))) {
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

// what a "buy and wear" would really do, item by item, with the coins you have
$plan = []; $buyCost = 0; $left = (int)$w['coins']; $doKeys = [];
foreach ($tryKeys as $k) {
    $i = $cat[$k];
    $row = row('SELECT equipped FROM game_items WHERE user_id = ? AND item = ?', [$uid, $k]);
    $why = '';
    if ((int)$u['level'] < game_item_req($k)) { $why = 'needs level ' . game_item_req($k); }
    elseif (($me = game_item_muscle_error($uid, $k)) !== null) { $why = 'needs ' . game_item_muscle_text($k); }
    if ($row && (int)$row['equipped']) { $plan[$k] = ['worn', '']; }
    elseif ($row) { $plan[$k] = $why === '' ? ['wear', ''] : ['blocked', $why]; if ($why === '') { $doKeys[] = $k; } }
    elseif ($i[5] <= 0) { $plan[$k] = ['blocked', 'cannot be bought']; }
    elseif ($why !== '') { $plan[$k] = ['blocked', $why]; }
    elseif (($pc = game_price_for($u, $k, $cat) < 0 ? 0 : (int)$i[5]) > $left) { $plan[$k] = ['blocked', 'not enough coins']; }
    else { $plan[$k] = ['buy', '']; $left -= $pc; $buyCost += $pc; $doKeys[] = $k; }
}
$review = isset($_GET['review']) && $doKeys;
$wearN = count(array_filter($plan, fn($p) => $p[0] === 'wear'));
$stat = []; $looks = [];
foreach ($cat as $k => $i) {
    $slot = (string)$i[2];
    if ($slot === $s && ($i[5] > 0 || isset($owned[$k]))) { $stat[$k] = $i; }
    if ($slot === 'look_' . $s && (!fa_staff_only($k) || is_staff($u))) { $looks[$k] = $i; }
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
      <?php if ($review): ?>
      <div class="wd-review">
        <b>Please confirm</b>
        <?php foreach ($plan as $k => [$st, $why]): ?>
          <div class="wd-line <?= e($st) ?>"><span><?= e($cat[$k][0]) ?></span><em><?php
            echo $st === 'buy' ? (game_price_for($u, $k, $cat) < 0 ? 'free for staff' : 'buy for ' . number_format((int)$cat[$k][5]) . ' coins') : ($st === 'wear' ? 'you own it, will wear' : ($st === 'worn' ? 'already worn' : 'will NOT be bought: ' . e($why)));
          ?></em></div>
        <?php endforeach; ?>
        <p class="small" style="margin:6px 0"><?= $buyCost > 0 ? 'Total <b>' . number_format($buyCost) . ' coins</b>. You have ' . number_format((int)$w['coins']) . ', ' . number_format($left) . ' left after.' : 'Nothing to pay.' ?></p>
        <form method="post" action="<?= e(url('shop')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="wear_tried"><input type="hidden" name="keys" value="<?= e(implode(',', $doKeys)) ?>"><input type="hidden" name="back" value="wardrobe"><input type="hidden" name="bs" value="<?= e($s) ?>"><input type="hidden" name="bt" value="<?= e(implode(',', $tryKeys)) ?>">
          <button class="btn small" type="submit">Confirm<?= $buyCost > 0 ? ' and pay ' . number_format($buyCost) : '' ?></button></form>
        <a class="btn ghost small" href="<?= e(url('wardrobe?s=' . $s . '&t=' . implode(',', $tryKeys))) ?>">Cancel</a>
      </div>
      <?php elseif ($doKeys): ?>
        <a class="btn small" href="<?= e(url('wardrobe?s=' . $s . '&t=' . implode(',', $tryKeys) . '&review=1')) ?>"><?= $buyCost > 0 ? 'Review and buy (' . number_format($buyCost) . ' coins)' : 'Wear all of this' ?></a>
        <?php if (count(array_filter($plan, fn($p) => $p[0] === 'blocked'))): ?><small class="muted" style="display:block">Some items can not be bought yet, you will see which.</small><?php endif; ?>
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
  <?php $teamLooks = array_filter($looks, fn($i, $k) => fa_staff_only($k), ARRAY_FILTER_USE_BOTH); $pubLooks = array_diff_key($looks, $teamLooks); ?>
  <?php if ($teamLooks): ?>
  <h3>Team only <span class="muted small">free for admins and moderators</span></h3>
  <div class="gshop"><?php foreach ($teamLooks as $k => $i) { $tile($k); } ?></div>
  <?php endif; ?>
  <h3>Look only</h3>
  <div class="gshop"><?php foreach ($pubLooks as $k => $i) { $tile($k); } if (!$pubLooks) { echo '<p class="muted small">No look-only items for this slot yet.</p>'; } ?></div>
</div>
<div class="card">
  <h3>Gear with bonuses</h3>
  <div class="gshop"><?php foreach ($stat as $k => $i) { $tile($k); } if (!$stat) { echo '<p class="muted small">Nothing here yet.</p>'; } ?></div>
</div>
<?php layout_footer('', ['wardrobe.js']); ?>
