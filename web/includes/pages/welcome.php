<?php
declare(strict_types=1);

/* First-run setup shown right after registering: weight, gender, goal and gym in one friendly step. Everything is optional. */

$u = require_login();
$uid = (int)$u['id'];

if (is_post()) {
    csrf_check();
    if (($_POST['do'] ?? '') === 'save') {
        $unit = ($_POST['unit'] ?? $u['unit']) === 'lb' ? 'lb' : 'kg';
        $bwIn = trim((string)($_POST['bodyweight'] ?? ''));
        $bw = null;
        if ($bwIn !== '') {
            $v = (float)str_replace(',', '.', $bwIn);
            $bw = $unit === 'lb' ? $v / LB_PER_KG : $v;
            $bw = ($bw >= 20 && $bw <= 400) ? round($bw, 1) : null;
        }
        $g = in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : null;
        $goal = in_array($_POST['goal'] ?? '', ['strength', 'muscle', 'fatloss', 'endurance', 'health'], true) ? $_POST['goal'] : null;
        $gym = (int)($_POST['gym'] ?? 0);
        $gymOk = $gym > 0 && row('SELECT id FROM gyms WHERE id = ? AND is_public = 1', [$gym]);
        q('UPDATE users SET unit = ?, bodyweight_kg = COALESCE(?, bodyweight_kg), gender = COALESCE(?, gender), goal = COALESCE(?, goal) WHERE id = ?', [$unit, $bw, $g, $goal, $uid]);
        if ($gymOk) {
            q('UPDATE users SET default_gym_id = ? WHERE id = ?', [$gym, $uid]);
            q('INSERT IGNORE INTO gym_saved (user_id, gym_id) VALUES (?, ?)', [$uid, $gym]);
        }
        flash('All set. Log your first workout to start earning points!');
    }
    redirect('log');
}

$gyms = rows("SELECT g.id, g.name, g.city FROM gyms g WHERE g.is_public = 1 AND g.kind = 'gym'
              ORDER BY (SELECT COUNT(DISTINCT w.user_id) FROM workouts w WHERE w.gym_id = g.id) DESC, g.name LIMIT 40");
$unit = $u['unit'] === 'lb' ? 'lb' : 'kg';

layout_header('Welcome', '', []);
?>
<div class="card narrow welcome">
  <div class="center"><?= mascot('cheer', 84) ?></div>
  <h2 class="center">Welcome to <?= e(site_name()) ?>, <?= e(display_name($u)) ?>!</h2>
  <p class="muted center">Four quick questions so your points and stats fit you. Everything is optional and you can change it any time in Settings.</p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="do" value="save">
    <fieldset class="seg">
      <legend>Units</legend>
      <label><input type="radio" name="unit" value="kg" <?= $unit === 'kg' ? 'checked' : '' ?>><span>kg</span></label>
      <label><input type="radio" name="unit" value="lb" <?= $unit === 'lb' ? 'checked' : '' ?>><span>lb</span></label>
    </fieldset>
    <label>Your bodyweight <small class="muted">(used for pull-ups, dips and other bodyweight moves; never shown to others)</small>
      <input name="bodyweight" inputmode="decimal" placeholder="<?= $unit === 'lb' ? '165' : '75' ?>" maxlength="6">
    </label>
    <fieldset class="seg">
      <legend>Gender <small class="muted">(optional, never shown on your profile)</small></legend>
      <label><input type="radio" name="gender" value="male"><span>Male</span></label>
      <label><input type="radio" name="gender" value="female"><span>Female</span></label>
      <label><input type="radio" name="gender" value="other"><span>Other</span></label>
      <label><input type="radio" name="gender" value="" checked><span>Prefer not to say</span></label>
    </fieldset>
    <fieldset class="seg">
      <legend>Main goal</legend>
      <?php foreach (['strength' => 'Get stronger', 'muscle' => 'Build muscle', 'fatloss' => 'Lose fat', 'endurance' => 'Endurance', 'health' => 'Stay healthy'] as $k => $l): ?>
        <label><input type="radio" name="goal" value="<?= $k ?>"><span><?= e($l) ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <?php if ($gyms): ?>
    <label>Where do you train? <small class="muted">(optional, you can add yours later under Gyms)</small>
      <select name="gym"><option value="">Skip for now</option>
        <?php foreach ($gyms as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['name'] . ($g['city'] !== '' ? ' · ' . $g['city'] : '')) ?></option><?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
    <button class="btn wide big" type="submit">Let's go</button>
  </form>
  <form method="post" class="center"><?= csrf_field() ?><input type="hidden" name="do" value="skip"><button class="btn ghost small" type="submit">Skip for now</button></form>
</div>
<?php layout_footer(''); ?>
