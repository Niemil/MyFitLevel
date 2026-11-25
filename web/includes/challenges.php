<?php
declare(strict_types=1);

/* Head-to-head challenges: most points earned from workouts during the challenge wins a bonus. */

const CHALLENGE_BONUS = 300;

function challenge_score(int $uid, string $from, string $to): int
{
    return (int)val('SELECT COALESCE(SUM(points),0) FROM workouts WHERE user_id = ? AND performed_at >= ? AND performed_at < ?', [$uid, $from, $to]);
}

/** Finishes challenges whose time is up. Winner gets a bonus; a tie pays both half. */
function challenges_settle(): void
{
    ql_ensure_tables();
    foreach (rows("SELECT * FROM challenges WHERE status = 'active' AND ends_at <= ?", [now()]) as $c) {
        $a = challenge_score((int)$c['challenger_id'], $c['starts_at'], $c['ends_at']);
        $b = challenge_score((int)$c['opponent_id'], $c['starts_at'], $c['ends_at']);
        $win = $a > $b ? (int)$c['challenger_id'] : ($b > $a ? (int)$c['opponent_id'] : null);
        q("UPDATE challenges SET status = 'done', winner_id = ? WHERE id = ? AND status = 'active'", [$win, $c['id']]);
        $ids = $win ? [$win => CHALLENGE_BONUS] : [(int)$c['challenger_id'] => (int)(CHALLENGE_BONUS / 2), (int)$c['opponent_id'] => (int)(CHALLENGE_BONUS / 2)];
        foreach ($ids as $uid => $bonus) {
            q('INSERT IGNORE INTO quest_steps (user_id, quest, step, reward, done_at) VALUES (?,?,?,?,?)', [$uid, 'challenge', (int)$c['id'], $bonus, now()]);
            q('INSERT INTO notifications (user_id, type, text, created_at) VALUES (?,?,?,?)',
              [$uid, 'quest', ($win === $uid ? 'You won a challenge!' : ($win ? 'Challenge finished. Better luck next time.' : 'Challenge ended in a tie.')) . " (+$bonus pts)", now()]);
            recalc_xp($uid);
        }
    }
}
