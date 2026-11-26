<?php
declare(strict_types=1);

/** Fito's one-line coaching note on the feed. Rule based, from the member's own training. */
function fito_tip(int $uid): array
{
    $last = val('SELECT MAX(performed_at) FROM workouts WHERE user_id = ?', [$uid]);
    if (!$last) {
        return ['Log your first workout and I will start keeping score. A walk counts too.', 'Track a workout', 'log'];
    }
    $days = (int)floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime((string)$last)))) / 86400);
    if ($days >= 5) {
        return ["It has been $days days. A short session is enough to get back in the groove.", 'Track a workout', 'log'];
    }
    $pr = row("SELECT e.name FROM workout_sets s JOIN workouts w ON w.id = s.workout_id JOIN exercises e ON e.id = s.exercise_id
        WHERE s.user_id = ? AND s.is_pr = 1 AND w.performed_at >= ? ORDER BY w.performed_at DESC LIMIT 1", [$uid, date('Y-m-d', strtotime('-7 days'))]);
    if ($pr) {
        return ['New record on ' . $pr['name'] . ' this week. That is the lift to build on.', 'See your records', 'insights#explore'];
    }
    $g = ['chest' => 'push', 'shoulders' => 'push', 'triceps' => 'push', 'back' => 'pull', 'biceps' => 'pull', 'legs' => 'legs'];
    $c = ['push' => 0, 'pull' => 0, 'legs' => 0];
    foreach (rows("SELECT e.muscle m, COUNT(*) n FROM workout_sets s JOIN workouts w ON w.id = s.workout_id JOIN exercises e ON e.id = s.exercise_id
        WHERE s.user_id = ? AND s.is_warmup = 0 AND w.performed_at >= ? GROUP BY e.muscle", [$uid, date('Y-m-d', strtotime('-30 days'))]) as $r) {
        if (isset($g[$r['m']])) { $c[$g[$r['m']]] += (int)$r['n']; }
    }
    $top = max($c);
    if ($top >= 20) {
        if ($c['legs'] < $top * 0.4) { return ['Legs are lagging behind the last 30 days. Squats or leg press next time?', 'See your balance', 'insights']; }
        if ($c['pull'] < $c['push'] * 0.6) { return ['You push a lot more than you pull. Add rows or pull-ups to even it out.', 'See your balance', 'insights']; }
        if ($c['push'] < $c['pull'] * 0.6) { return ['Lots of pulling lately. Give the chest and shoulders some love too.', 'See your balance', 'insights']; }
    }
    if ($days === 0) { return ['Good session today. Eat well and rest, that is when you grow.', 'Your numbers', 'insights']; }
    if ($days === 1) { return ['Back at it? A day of rest is fine, but you are on a roll.', 'Track a workout', 'log']; }
    return ["Ready when you are. Your last workout was $days days ago.", 'Track a workout', 'log'];
}

function fito_coach_card(array $u): void
{
    [$text, $label, $to] = fito_tip((int)$u['id']);
    ?>
<section class="card fcoach">
  <?= fito('idle', 48, 'nomotion', true) ?>
  <div class="grow"><b>Fito says</b><p><?= e($text) ?></p></div>
  <a class="btn ghost small" href="<?= e(url($to)) ?>"><?= e($label) ?></a>
</section>
<?php
}
