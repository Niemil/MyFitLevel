<?php
declare(strict_types=1);

/* "My lists": your own pick-lists of exercises (Push, Pull, Legs, anything). In the tracker they show first, in colour. */

$u = require_login();
$uid = (int)$u['id'];
ql_ensure_tables();
$COLORS = ['#36a85c', '#3498db', '#e67e22', '#9b59b6', '#e74c3c', '#f1c40f', '#1abc9c', '#e84393'];
$lid = isset($args[0]) ? (int)$args[0] : 0;

function list_ids(array $l): array
{
    $ids = json_decode((string)$l['payload'], true);
    return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
}
function list_save_ids(int $id, int $uid, array $ids): void
{
    q('UPDATE ex_lists SET payload = ? WHERE id = ? AND user_id = ?', [json_encode(array_values(array_unique($ids))), $id, $uid]);
}
function list_color(string $c, array $palette): string
{
    return in_array($c, $palette, true) ? $c : $palette[0];
}

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $own = ($pid = (int)($_POST['lid'] ?? 0)) ? row('SELECT * FROM ex_lists WHERE id = ? AND user_id = ?', [$pid, $uid]) : null;
    if ($do === 'create') {
        $nm = trim(clean_text((string)($_POST['name'] ?? ''), 40));
        if ($nm === '' || (int)val('SELECT COUNT(*) FROM ex_lists WHERE user_id = ?', [$uid]) >= 12) {
            flash($nm === '' ? 'Give the list a name.' : 'You can have up to 12 lists.', 'err');
            redirect('lists');
        }
        q('INSERT INTO ex_lists (user_id, name, color, payload, pos) VALUES (?,?,?,?,?)', [$uid, $nm, list_color((string)($_POST['color'] ?? ''), $COLORS), '[]', (int)val('SELECT COUNT(*) FROM ex_lists WHERE user_id = ?', [$uid])]);
        redirect('lists/' . (int)val('SELECT LAST_INSERT_ID()'));
    }
    if ($do === 'preset') {
        // Push / Pull / Legs, filled with what you do most; sensible defaults when you have no history yet
        $defs = [
            ['Push', '#e67e22', ['chest', 'shoulders', 'triceps'], ['Barbell Bench Press', 'Seated Dumbbell Shoulder Press', 'Incline Dumbbell Press', 'Dumbbell Lateral Raise', 'Rope Triceps Pushdown']],
            ['Pull', '#3498db', ['back', 'biceps'], ['Pull-Up', 'Barbell Row', 'Lat Pulldown', 'Face Pull', 'Dumbbell Curl']],
            ['Legs', '#36a85c', ['legs'], ['Back Squat', 'Romanian Deadlift', 'Leg Press', 'Seated Leg Curl', 'Standing Calf Raise']],
        ];
        $pos = (int)val('SELECT COUNT(*) FROM ex_lists WHERE user_id = ?', [$uid]);
        foreach ($defs as [$nm, $col, $groups, $fallback]) {
            if ((int)val('SELECT COUNT(*) FROM ex_lists WHERE user_id = ?', [$uid]) >= 12 || row('SELECT 1 FROM ex_lists WHERE user_id = ? AND name = ?', [$uid, $nm])) {
                continue;
            }
            $in = implode(',', array_fill(0, count($groups), '?'));
            $ids = array_map('intval', array_column(rows("SELECT e.id FROM workout_sets s JOIN exercises e ON e.id = s.exercise_id WHERE s.user_id = ? AND s.is_warmup = 0 AND e.muscle IN ($in) AND e.active = 1 GROUP BY e.id ORDER BY COUNT(DISTINCT s.workout_id) DESC LIMIT 8", array_merge([$uid], $groups)), 'id'));
            if (count($ids) < 3) {
                foreach ($fallback as $fn) {
                    $x = row('SELECT id FROM exercises WHERE name = ? AND active = 1', [$fn]);
                    if ($x) { $ids[] = (int)$x['id']; }
                }
            }
            q('INSERT INTO ex_lists (user_id, name, color, payload, pos) VALUES (?,?,?,?,?)', [$uid, $nm, $col, json_encode(array_values(array_unique($ids))), $pos++]);
        }
        flash('Push, Pull and Legs created. Edit them any time.');
        redirect('lists');
    }
    if ($own) {
        $ids = list_ids($own);
        if ($do === 'save') {
            q('UPDATE ex_lists SET name = ?, color = ? WHERE id = ? AND user_id = ?', [trim(clean_text((string)($_POST['name'] ?? ''), 40)) ?: $own['name'], list_color((string)($_POST['color'] ?? ''), $COLORS), $pid, $uid]);
        } elseif ($do === 'delete') {
            q('DELETE FROM ex_lists WHERE id = ? AND user_id = ?', [$pid, $uid]);
            redirect('lists');
        } elseif ($do === 'remove') {
            list_save_ids($pid, $uid, array_values(array_diff($ids, [(int)($_POST['ex'] ?? 0)])));
        } elseif ($do === 'add') {
            $nm = clean_text((string)($_POST['exname'] ?? ''), 100);
            $ex = row('SELECT id FROM exercises WHERE name = ? AND active = 1', [$nm]);
            if (!$ex && $nm !== '') {
                $like = rows('SELECT id FROM exercises WHERE name LIKE ? AND active = 1 LIMIT 2', ['%' . str_replace(['%', '_'], '', $nm) . '%']);
                $ex = count($like) === 1 ? $like[0] : null;
            }
            if ($ex && count($ids) < 60) {
                list_save_ids($pid, $uid, array_merge($ids, [(int)$ex['id']]));
            } else {
                flash('Pick an exercise from the list.', 'err');
            }
        }
        redirect('lists/' . $pid);
    }
    redirect('lists');
}

$exInfo = [];
foreach (rows('SELECT id, name, muscle, equipment, kind FROM exercises WHERE active = 1') as $x) {
    $x['tier'] = exercise_tier($x);
    $exInfo[(int)$x['id']] = $x;
}
$all = rows('SELECT * FROM ex_lists WHERE user_id = ? ORDER BY pos, id', [$uid]);

if ($lid) {
    $l = row('SELECT * FROM ex_lists WHERE id = ? AND user_id = ?', [$lid, $uid]);
    if (!$l) { redirect('lists'); }
    $ids = list_ids($l);
    layout_header($l['name'], 'lists');
    ?>
<p class="rthead"><a href="<?= e(url('lists')) ?>">← My lists</a></p>
<div class="card" style="border-left:6px solid <?= e($l['color']) ?>">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="lid" value="<?= $lid ?>">
    <div class="row" style="gap:.5rem;align-items:center"><input name="name" value="<?= e($l['name']) ?>" maxlength="40" style="flex:1"><button class="btn small ghost" type="submit">Save</button></div>
    <div class="swatches"><?php foreach ($COLORS as $c): ?><label class="swatch" style="background:<?= e($c) ?>"><input type="radio" name="color" value="<?= e($c) ?>" <?= $l['color'] === $c ? 'checked' : '' ?>><span>✓</span></label><?php endforeach; ?></div>
  </form>
</div>
<div class="card">
  <h3>Exercises in this list</h3>
  <p class="muted small">These show first, in this colour, whenever you add exercises in the tracker. Tap the list in the tracker to add several at once.</p>
  <?php if (!$ids): ?><p class="muted">Empty. Search below to add your usual exercises.</p><?php endif; ?>
  <?php foreach ($ids as $eid): $inf = $exInfo[$eid] ?? null; if (!$inf) { continue; } ?>
  <form method="post" class="rtrow"><?= csrf_field() ?><input type="hidden" name="lid" value="<?= $lid ?>"><input type="hidden" name="ex" value="<?= $eid ?>">
    <span class="rtname"><?= e($inf['name']) ?> <?= tier_chip($inf['tier']) ?> <small class="muted"><?= e(ucfirst($inf['muscle'])) ?> · <?= e($inf['equipment']) ?></small></span>
    <button class="btn small ghost" name="do" value="remove" aria-label="Remove">✕</button></form>
  <?php endforeach; ?>
  <h4 style="margin-top:1rem">Add an exercise</h4>
  <form method="post" class="rtadd"><?= csrf_field() ?><input type="hidden" name="do" value="add"><input type="hidden" name="lid" value="<?= $lid ?>">
    <div class="rtsearch"><input name="exname" id="rtname" placeholder="Search, e.g. db press, bb row, bench" autocomplete="off" required><div class="rtresults" id="rtresults" hidden></div></div>
    <button class="btn" type="submit">Add</button></form>
  <script type="application/json" id="rtdata"><?= json_encode(array_values(array_map(static fn($x) => ['n' => $x['name'], 'm' => $x['muscle'], 'e' => $x['equipment'], 't' => $x['tier']], $exInfo)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</div>
<div class="card"><form method="post"><?= csrf_field() ?><input type="hidden" name="lid" value="<?= $lid ?>"><button class="btn small ghost danger" name="do" value="delete" data-confirm="Delete this list?">Delete list</button></form></div>
<?php layout_footer('lists', ['search-terms.js', 'routines.js']);
    return;
}

layout_header('My lists', 'lists');
?>
<h1 class="pagetitle">My lists</h1>
<p class="muted">Keep your own lists of favourite exercises, for example <b>Push</b>, <b>Pull</b> and <b>Legs</b>. In the tracker they show first and in colour, so you find your usual exercises fast. No schedule needed.</p>
<?php if (!$all): ?>
<div class="card center"><p>The quickest start: create Push, Pull and Legs filled with the exercises you do most.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="preset"><button class="btn" type="submit">Create Push / Pull / Legs</button></form></div>
<?php endif; ?>
<?php foreach ($all as $l): $ids = list_ids($l); ?>
<a class="card listcard" href="<?= e(url('lists/' . $l['id'])) ?>" style="border-left:6px solid <?= e($l['color']) ?>">
  <b><?= e($l['name']) ?></b> <span class="muted small">· <?= count($ids) ?> exercises</span>
  <div class="small muted"><?= e(implode(', ', array_slice(array_map(static fn($i) => $exInfo[$i]['name'] ?? '', $ids), 0, 6))) ?><?= count($ids) > 6 ? '…' : '' ?></div>
</a>
<?php endforeach; ?>
<div class="card"><h4>New list</h4>
  <form method="post" class="chalform"><?= csrf_field() ?><input type="hidden" name="do" value="create">
    <label>Name <input name="name" maxlength="40" placeholder="e.g. Upper body, Arm day" required></label>
    <div class="swatches"><?php foreach ($COLORS as $i => $c): ?><label class="swatch" style="background:<?= e($c) ?>"><input type="radio" name="color" value="<?= e($c) ?>" <?= $i === 0 ? 'checked' : '' ?>><span>✓</span></label><?php endforeach; ?></div>
    <button class="btn" type="submit">Create</button></form>
  <?php if ($all): ?><form method="post" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="do" value="preset"><button class="btn small ghost" type="submit">Add Push / Pull / Legs</button></form><?php endif; ?>
</div>
<?php layout_footer('lists'); ?>
