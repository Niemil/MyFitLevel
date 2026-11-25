<?php
declare(strict_types=1);

/* Routines and weekly schedule helpers. */

const DOW_NAMES = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

function routine_items(array $r): array
{
    $j = json_decode((string)($r['payload'] ?? '[]'), true);
    return is_array($j) ? $j : [];
}

/** Today's day of week (1 = Monday) in the member's time zone. */
function today_dow(array $u): int
{
    try {
        return (int)(new DateTime('now', user_tz($u)))->format('N');
    } catch (Throwable $e) {
        return (int)date('N');
    }
}

function today_card(array $u): void
{
    $uid = (int)$u['id'];
    $dow = today_dow($u);
    $r = row('SELECT r.id, r.name, r.payload FROM routine_schedule s JOIN routines r ON r.id = s.routine_id WHERE s.user_id = ? AND s.dow = ?', [$uid, $dow]);
    if (!$r) {
        return;
    }
    // already trained today? then show a quiet confirmation instead of a call to action
    $done = (int)val('SELECT COUNT(*) FROM workouts WHERE user_id = ? AND DATE(performed_at) = ?', [$uid, (new DateTime('now', user_tz($u)))->format('Y-m-d')]) > 0;
    $n = count(routine_items($r));
    ?>
<div class="card todaycard">
  <div><span class="muted small">Today · <?= e(DOW_NAMES[$dow]) ?></span>
    <h3><?= e($r['name']) ?></h3>
    <span class="muted small"><?= $n ?> exercise<?= $n === 1 ? '' : 's' ?><?= $done ? ' · you already trained today 💪' : '' ?></span></div>
  <a class="btn" href="<?= e(url('log?routine=' . (int)$r['id'])) ?>"><?= $done ? 'Start again' : 'Start workout' ?></a>
</div>
<?php
}
