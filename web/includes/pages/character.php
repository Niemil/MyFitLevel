<?php
declare(strict_types=1);

/* Your character: Fito with your gear, animal and eye colour. */
$u = require_login();
$uid = (int)$u['id'];
game_ensure();
staff_ensure();
define('GAME_CSS', true);

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $cur = fito_look($uid);
    if ($do === 'eye' && isset(FITO_EYES[(string)($_POST['eye'] ?? '')])) {
        q('INSERT INTO char_look (user_id, species, eye, picks, updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE eye = VALUES(eye), updated_at = VALUES(updated_at)',
          [$uid, $cur['species'], (string)$_POST['eye'], $cur['picks'], now()]);
        flash('Eye colour changed.');
    } elseif ($do === 'species' && isset(FITO_SPECIES[(string)($_POST['species'] ?? '')])) {
        $sp = (string)$_POST['species'];
        if ($sp === $cur['species']) {
            redirect('character');
        }
        $cost = $cur['picks'] > 0 ? FITO_SPECIES_COST : 0;
        $w = game_wallet($uid);
        if ($cost > 0 && (int)$w['coins'] < $cost) {
            flash('Changing animal costs ' . $cost . ' coins. Keep training!', 'err');
        } else {
            if ($cost > 0) {
                game_coins_add($uid, -$cost, 'shop', 'New animal: ' . FITO_SPECIES[$sp]);
            }
            q('INSERT INTO char_look (user_id, species, eye, picks, updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE species = VALUES(species), picks = picks + 1, updated_at = VALUES(updated_at)',
              [$uid, $sp, $cur['eye'], 1, now()]);
            flash('Your character is now a ' . strtolower(FITO_SPECIES[$sp]) . '.');
        }
    }
    redirect('character');
}

$look = fito_look($uid);
$last = val('SELECT MAX(performed_at) FROM workouts WHERE user_id = ?', [$uid]);
$days = $last ? (int)floor((time() - strtotime((string)$last)) / 86400) : 99;
$mood = max(8, 100 - $days * 14);
[$moodTxt, $moodCls] = $days <= 1 ? ['Pumped. You trained recently.', 'ok'] : ($days <= 3 ? ['Doing fine. A workout soon would make Fito happy.', ''] : ['Fito is missing the gym.', 'warn']);
$slotNames = ['weapon' => 'Weapon', 'armor' => 'Armor', 'helmet' => 'Helmet', 'gloves' => 'Gloves', 'boots' => 'Boots', 'charm' => 'Charm', 'wings' => 'Wings', 'aura' => 'Aura'];

layout_header('Character', 'me');
?>
<h1>Your character</h1>
<div class="card charcard">
  <div class="charstage"><?= fito_dressed($look, 300) ?></div>
  <div class="charmood">
    <span class="muted small">Mood</span>
    <div class="xpbar"><i style="width:<?= $mood ?>%"></i></div>
    <small class="<?= e($moodCls) ?>"><?= e($moodTxt) ?></small>
  </div>
</div>

<div class="card">
  <h3>Wearing</h3>
  <?php foreach ($slotNames as $sl => $label): $g = $look['gear'][$sl] ?? null; ?>
    <div class="gb-row"><span class="grow"><?= e($label) ?></span><b><?= $g ? e($g['name']) : '<span class="muted">Nothing</span>' ?></b></div>
  <?php endforeach; ?>
  <p class="muted small" style="margin-bottom:0">New gear shows up on Fito as soon as you equip it. Early gear looks rough and the best gear shines. <a href="<?= e(url('shop')) ?>">Open the Shop</a></p>
</div>

<div class="card">
  <h3>Animal</h3>
  <p class="muted small" style="margin-top:0">Your first pick is free. Changing later costs <?= FITO_SPECIES_COST ?> coins.</p>
  <div class="spgrid">
    <?php foreach (FITO_SPECIES as $k => $label): ?>
      <form method="post" class="spbtn<?= $look['species'] === $k ? ' on' : '' ?>"><?= csrf_field() ?><input type="hidden" name="do" value="species"><input type="hidden" name="species" value="<?= e($k) ?>">
        <?= fito_species_face($k, $look['eye'], 64) ?><b><?= e($label) ?></b>
        <button class="btn small<?= $look['species'] === $k ? ' ghost' : '' ?>" type="submit" <?= $look['species'] === $k ? 'disabled' : '' ?>><?= $look['species'] === $k ? 'Current' : ($look['picks'] > 0 ? 'Choose (' . FITO_SPECIES_COST . ')' : 'Choose') ?></button></form>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h3>Eye colour</h3>
  <div class="eyegrid">
    <?php $ec = ['ice' => '#62ccff', 'emerald' => '#4be28a', 'amber' => '#ffb54a', 'crimson' => '#ff6b6b', 'violet' => '#b58cff', 'gold' => '#ffe066']; ?>
    <?php foreach (FITO_EYES as $k => $label): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="eye"><input type="hidden" name="eye" value="<?= e($k) ?>">
        <button type="submit" class="eyeb<?= $look['eye'] === $k ? ' on' : '' ?>" style="--c:<?= e($ec[$k]) ?>" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"></button></form>
    <?php endforeach; ?>
  </div>
</div>
<?php layout_footer('me'); ?>
