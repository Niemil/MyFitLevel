<?php
declare(strict_types=1);

$u = require_login();
$uid = (int)$u['id'];
$gid = isset($args[0]) ? (int)$args[0] : 0;
$g = null;
if ($gid) {
    $g = row('SELECT * FROM gyms WHERE id = ?', [$gid]);
    if (!$g || !gym_can_edit($g, $u)) {
        http_response_code(404);
        layout_header('Not found');
        echo '<div class="card center"><h2>Gym not found</h2></div>';
        layout_footer();
        exit;
    }
}

if (is_post()) {
    csrf_check();
    $act = (string)($_POST['act'] ?? 'save');

    if ($act === 'delete' && $g) {
        q('UPDATE users SET default_gym_id = NULL WHERE default_gym_id = ?', [$gid]);
        q('UPDATE workouts SET gym_id = NULL WHERE gym_id = ?', [$gid]);
        q('DELETE FROM gyms WHERE id = ?', [$gid]);
        flash('Gym deleted.');
        redirect('gyms');
    }
    if ($act === 'add_item' && $g) {
        $cat = array_key_exists($_POST['cat'] ?? '', GYM_CATS) ? $_POST['cat'] : 'other';
        $name = trim((string)($_POST['iname'] ?? ''));
        if ($name === '') {
            $name = GYM_CATS[$cat];
        }
        $w = in_array($cat, GYM_WEIGHT_CATS, true) ? gym_parse_weights((string)($_POST['weights'] ?? ''), $u['unit']) : [];
        if (val('SELECT COUNT(*) FROM gym_items WHERE gym_id = ?', [$gid]) >= 150) {
            flash('That is a lot of equipment already.', 'err');
        } else {
            q('INSERT INTO gym_items (gym_id, cat, name, weights, note) VALUES (?,?,?,?,?)',
              [$gid, $cat, mb_substr($name, 0, 80), $w ? json_encode($w) : null, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 160)]);
            flash('Added.');
        }
        redirect('gym/' . $gid . '/edit#equip');
    }
    if ($act === 'del_item' && $g) {
        q('DELETE FROM gym_items WHERE id = ? AND gym_id = ?', [(int)($_POST['item'] ?? 0), $gid]);
        redirect('gym/' . $gid . '/edit#equip');
    }

    // save / create
    $name = trim((string)($_POST['name'] ?? ''));
    $kind = ($_POST['kind'] ?? '') === 'home' ? 'home' : 'gym';
    $adv = !empty($_POST['advanced']) ? 1 : 0;
    $pub = !empty($_POST['is_public']) ? 1 : 0;
    $city = mb_substr(trim((string)($_POST['city'] ?? '')), 0, 80);
    $addr = $adv && $kind === 'gym' ? mb_substr(trim((string)($_POST['address'] ?? '')), 0, 160) : '';
    $info = $adv ? mb_substr(trim((string)($_POST['info'] ?? '')), 0, 2000) : '';
    $web = '';
    if ($adv && $kind === 'gym') {
        $web = trim((string)($_POST['website'] ?? ''));
        if ($web !== '' && !preg_match('#^https?://#i', $web)) {
            $web = 'https://' . $web;
        }
        if (!filter_var($web, FILTER_VALIDATE_URL)) {
            $web = '';
        }
        $web = mb_substr($web, 0, 200);
    }
    if ($kind === 'home') {
        $city = '';   // home gyms never carry a place
    }
    if (mb_strlen($name) < 2) {
        flash('Give the gym a name (at least 2 characters).', 'err');
        redirect($g ? 'gym/' . $gid . '/edit' : 'gym/new');
    }
    if ($g) {
        q('UPDATE gyms SET name=?, kind=?, city=?, address=?, info=?, website=?, is_public=?, advanced=? WHERE id=?',
          [mb_substr($name, 0, 80), $kind, $city, $addr, $info ?: null, $web, $pub, $adv, $gid]);
        flash('Saved.');
        redirect('gym/' . $gid);
    }
    if (val('SELECT COUNT(*) FROM gyms WHERE owner_id = ?', [$uid]) >= 20) {
        flash('You have created 20 gyms already.', 'err');
        redirect('gyms');
    }
    q('INSERT INTO gyms (owner_id, name, kind, city, address, info, website, is_public, advanced, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$uid, mb_substr($name, 0, 80), $kind, $city, $addr, $info ?: null, $web, $pub, $adv, now()]);
    $newId = (int)db()->lastInsertId();
    if (!$u['default_gym_id']) {
        q('UPDATE users SET default_gym_id = ? WHERE id = ?', [$newId, $uid]);
    }
    flash('Gym created. Add its equipment so logging gets quicker.');
    redirect('gym/' . $newId . '/edit#equip');
}

$d = $g ?: ['name' => '', 'kind' => (($_GET['kind'] ?? '') === 'home' ? 'home' : 'gym'), 'city' => '', 'address' => '', 'info' => '', 'website' => '', 'is_public' => 0, 'advanced' => 0];
if (!$g && $d['kind'] === 'gym') {
    $d['is_public'] = 1;   // shared gyms are published by default, home gyms stay private
}
$items = $g ? rows('SELECT * FROM gym_items WHERE gym_id = ? ORDER BY cat, name, id', [$gid]) : [];

layout_header($g ? 'Edit gym' : 'New gym', 'gyms');
?>
<div class="narrowcol">
<h1 class="pagetitle"><?= $g ? 'Edit ' . e($g['name']) : 'New gym' ?></h1>
<form method="post" class="card gymform">
  <?= csrf_field() ?>
  <input type="hidden" name="act" value="save">
  <label>Name
    <input name="name" maxlength="80" required value="<?= e($d['name']) ?>" placeholder="e.g. Gym974 or My garage">
  </label>
  <div class="seg">
    <label><input type="radio" name="kind" value="gym" <?= $d['kind'] === 'gym' ? 'checked' : '' ?>> Gym (shared place)</label>
    <label><input type="radio" name="kind" value="home" <?= $d['kind'] === 'home' ? 'checked' : '' ?>> Home gym (no location)</label>
  </div>
  <label class="check"><input type="checkbox" name="is_public" value="1" <?= $d['is_public'] ? 'checked' : '' ?>> Publish, so other members can find and use it</label>
  <label>Town / city <small class="muted">(optional, gyms only)</small>
    <input name="city" maxlength="80" value="<?= e($d['city']) ?>" placeholder="e.g. Luleå">
  </label>
  <label class="check"><input type="checkbox" name="advanced" value="1" <?= $d['advanced'] ? 'checked' : '' ?>> Advanced: address, website, notes and equipment</label>
  <p class="muted small">Tick Advanced to fill in more. Basic is just a name, and your workouts will say where you trained.</p>
  <?php if ($d['advanced']): ?>
  <label>Address <small class="muted">(gyms only)</small>
    <input name="address" maxlength="160" value="<?= e($d['address']) ?>" placeholder="Street, area">
  </label>
  <label>Website <small class="muted">(optional)</small>
    <input name="website" maxlength="200" value="<?= e($d['website']) ?>" placeholder="https://…">
  </label>
  <label>Notes
    <textarea name="info" rows="3" maxlength="2000" placeholder="Opening hours, tips, what the place is good for…"><?= e((string)$d['info']) ?></textarea>
  </label>
  <?php endif; ?>
  <div class="modalbtns">
    <button class="btn" type="submit"><?= $g ? 'Save' : 'Create gym' ?></button>
    <a class="btn ghost" href="<?= e(url($g ? 'gym/' . $gid : 'gyms')) ?>">Cancel</a>
  </div>
</form>

<?php if ($g && $g['advanced']): ?>
<section class="card" id="equip">
  <h3>Equipment</h3>
  <?php if (!$items): ?><p class="muted">Nothing added yet.</p><?php endif; ?>
  <?php foreach ($items as $it): $ws = gym_weights_display($it['weights'], $u['unit']); ?>
    <form method="post" class="gitem">
      <?= csrf_field() ?><input type="hidden" name="act" value="del_item"><input type="hidden" name="item" value="<?= (int)$it['id'] ?>">
      <div class="grow"><b><?= e($it['name']) ?></b> <span class="muted small">· <?= e(GYM_CATS[$it['cat']] ?? 'Other') ?></span>
        <?php if ($ws): ?><div class="small muted"><?= e(implode(', ', array_slice($ws, 0, 40))) ?> <?= e($u['unit']) ?></div><?php endif; ?>
        <?php if ($it['note'] !== ''): ?><div class="small muted"><?= e($it['note']) ?></div><?php endif; ?>
      </div>
      <button class="btn ghost small danger" type="submit" aria-label="Remove">Remove</button>
    </form>
  <?php endforeach; ?>
  <h4>Add equipment</h4>
  <form method="post" class="gymform">
    <?= csrf_field() ?><input type="hidden" name="act" value="add_item">
    <div class="row2">
      <label>Type
        <select name="cat"><?php foreach (GYM_CATS as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select>
      </label>
      <label>Name <small class="muted">(optional)</small>
        <input name="iname" maxlength="80" placeholder="e.g. Hex dumbbells, Leg press">
      </label>
    </div>
    <label>Available weights in <?= e($u['unit']) ?> <small class="muted">(dumbbells, bars, plates, machine stacks)</small>
      <input name="weights" maxlength="400" inputmode="text" placeholder="e.g. 2.5-30/2.5 32.5 35 40 (from-to/step, space between items)">
    </label>
    <label>Note <small class="muted">(optional)</small>
      <input name="note" maxlength="160">
    </label>
    <button class="btn small" type="submit">Add</button>
  </form>
  <p class="muted small">Dumbbell weights you add here become the +/- steps in the logger when you pick this gym.</p>
</section>
<?php elseif ($g): ?>
<p class="muted small">Tick <b>Advanced</b> above and save to add equipment.</p>
<?php endif; ?>

<?php if ($g): ?>
<form method="post" class="card dangerbox" data-confirm="Delete this gym? Workouts logged here are kept but lose the gym.">
  <?= csrf_field() ?><input type="hidden" name="act" value="delete">
  <button class="btn ghost danger" type="submit">Delete gym</button>
</form>
<?php endif; ?>
</div>
<?php layout_footer('gyms'); ?>
