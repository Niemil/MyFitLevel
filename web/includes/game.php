<?php
declare(strict_types=1);

/*
 * The game layer: a public weekly boss, coins, a shop with gear and boosts, and teams.
 * Training is the only fuel. Every logged workout hits the boss automatically.
 * Tuning lives in settings (Admin > Game) and the constants below.
 */

const GAME_BOSS_DAYS = 7;
const GAME_BOSS_MIN_HP = 2500;
const GAME_COIN_WORKOUTS_PER_DAY = 2;

function game_defaults(): array
{
    return ['max_team_level' => '10', 'team_slots' => '5,6,7,8,10,11,13,15,17,20', 'boss_hp_mult' => '1',
            'coin_mult' => '1', 'team_xp_mult' => '1', 'respawn_hours' => '6', 'team_create_cost' => '100', 'team_solo_days' => '14',
            'steps_per_point' => '120', 'steps_cap_day' => '20000'];
}

function game_cfg(string $k): string
{
    $d = game_defaults();
    $v = setting('game_' . $k, null);
    return ($v === null || $v === '') ? (string)($d[$k] ?? '') : (string)$v;
}

/** Second schema step: team vault and buffs (added in 3.20.2). */
function game_ensure(): void
{
    // 3.24: steps are worth more. Move saved old defaults (300 / 12000) to the new ones, once.
    if (setting('steps_rates_v2') !== '1') {
        if (setting('game_steps_per_point', null) === '300') { set_setting('game_steps_per_point', '120'); }
        if (setting('game_steps_cap_day', null) === '12000') { set_setting('game_steps_cap_day', '20000'); }
        set_setting('steps_rates_v2', '1');
    }
    if (setting('boss_log_ok1') !== '1') {
        db()->exec("CREATE TABLE IF NOT EXISTS boss_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, boss_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
            damage INT NOT NULL, src VARCHAR(10) NOT NULL DEFAULT 'workout', created_at DATETIME NOT NULL, KEY idx_bl (boss_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if ((int)val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_hits'")) {
            try {
                db()->exec("INSERT INTO boss_log (boss_id, user_id, damage, src, created_at) SELECT boss_id, user_id, damage, 'workout', created_at FROM game_hits WHERE boss_id IS NOT NULL AND damage > 0");
                db()->exec("INSERT INTO boss_log (boss_id, user_id, damage, src, created_at) SELECT tboss_id, user_id, damage, 'workout', created_at FROM game_hits WHERE tboss_id IS NOT NULL AND damage > 0");
            } catch (Throwable $e) { error_log('boss_log backfill: ' . $e->getMessage()); }
        }
        set_setting('boss_log_ok1', '1');
    }
    if (setting('advent_ok1') !== '1') {
        db()->exec("CREATE TABLE IF NOT EXISTS advent_opens (
            user_id INT UNSIGNED NOT NULL, yr SMALLINT NOT NULL, day TINYINT NOT NULL, opened_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, yr, day)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        set_setting('advent_ok1', '1');
    }
    seasons_sync();
    static $done = false;
    game_ensure_v1();
    if ($done || setting('game_tables_ok9') === '1') {
        $done = true;
        return;
    }
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    try {
        $pdo = db();
        if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teams' AND COLUMN_NAME = 'vault'")) {
            $pdo->exec('ALTER TABLE teams ADD COLUMN vault INT NOT NULL DEFAULT 0');
        }
        if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_wallet' AND COLUMN_NAME = 'chest_day'")) {
            $pdo->exec('ALTER TABLE game_wallet ADD COLUMN chest_day DATE NULL, ADD COLUMN chest_streak SMALLINT NOT NULL DEFAULT 0');
        }
        if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_wallet' AND COLUMN_NAME = 'coin_boost_pct'")) {
            $pdo->exec('ALTER TABLE game_wallet ADD COLUMN coin_boost_pct SMALLINT NOT NULL DEFAULT 0, ADD COLUMN coin_boost_until DATETIME NULL');
        }
        if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teams' AND COLUMN_NAME = 'vault_who'")) {
            $pdo->exec("ALTER TABLE teams ADD COLUMN vault_who VARCHAR(8) NOT NULL DEFAULT 'officer'");
        }
        if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_wallet' AND COLUMN_NAME = 'steps_home'")) {
            $pdo->exec('ALTER TABLE game_wallet ADD COLUMN steps_home TINYINT NULL, ADD COLUMN steps_goal INT NOT NULL DEFAULT 10000');
        }
        if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_steps' AND COLUMN_NAME = 'pts_hit'")) {
            if ((int)val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_steps'")) {
                $pdo->exec('ALTER TABLE daily_steps ADD COLUMN pts_hit INT NOT NULL DEFAULT 0, ADD COLUMN coin_paid INT NOT NULL DEFAULT 0');
            }
        }
        events_ensure();
        if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'game_coin_pct'")) {
            $pdo->exec('ALTER TABLE events ADD COLUMN game_coin_pct INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN game_dmg_pct INT UNSIGNED NOT NULL DEFAULT 0');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS daily_steps (
            user_id INT UNSIGNED NOT NULL, day DATE NOT NULL, steps INT UNSIGNED NOT NULL DEFAULT 0, distance_m INT UNSIGNED NOT NULL DEFAULT 0,
            source VARCHAR(10) NOT NULL DEFAULT 'manual', updated_at DATETIME NOT NULL, pts_hit INT NOT NULL DEFAULT 0, coin_paid INT NOT NULL DEFAULT 0, PRIMARY KEY (user_id, day),
            CONSTRAINT fk_ds_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS game_events (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(12) NOT NULL, icon VARCHAR(16) NOT NULL DEFAULT '',
            text VARCHAR(220) NOT NULL, user_id INT UNSIGNED NULL, team_id INT UNSIGNED NULL, created_at DATETIME NOT NULL,
            KEY idx_ge (created_at)) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS game_titles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, title VARCHAR(60) NOT NULL,
            season CHAR(7) NOT NULL, created_at DATETIME NOT NULL, KEY idx_gt (user_id),
            CONSTRAINT fk_gt_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS team_buffs (
            team_id INT UNSIGNED NOT NULL, code VARCHAR(12) NOT NULL, until DATETIME NOT NULL, PRIMARY KEY (team_id, code),
            CONSTRAINT fk_tb_t FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS team_gifts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, team_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
            amount INT NOT NULL, created_at DATETIME NOT NULL, KEY idx_tg (team_id, created_at),
            CONSTRAINT fk_tg_t FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            CONSTRAINT fk_tg_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        set_setting('game_tables_ok9', '1');
        $done = true;
    } catch (Throwable $e) {
        error_log('game tables v2: ' . $e->getMessage());
    }
}

function game_ensure_v1(): void
{
    static $done = false;
    if ($done || setting('game_tables_ok1') === '1') {
        $done = true;
        return;
    }
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    try {
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS game_wallet (
            user_id INT UNSIGNED NOT NULL PRIMARY KEY, coins INT NOT NULL DEFAULT 0, boost_pct SMALLINT NOT NULL DEFAULT 0,
            boost_until DATETIME NULL, dmg_total BIGINT NOT NULL DEFAULT 0, kills INT NOT NULL DEFAULT 0,
            CONSTRAINT fk_gw_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS game_ledger (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, amount INT NOT NULL,
            kind VARCHAR(20) NOT NULL, note VARCHAR(120) NULL, created_at DATETIME NOT NULL, KEY idx_gl (user_id, id),
            CONSTRAINT fk_gl_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS game_items (
            user_id INT UNSIGNED NOT NULL, item VARCHAR(30) NOT NULL, source VARCHAR(12) NOT NULL DEFAULT 'shop',
            got_at DATETIME NOT NULL, equipped TINYINT(1) NOT NULL DEFAULT 0, PRIMARY KEY (user_id, item),
            CONSTRAINT fk_gi_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS teams (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(30) NOT NULL, emoji VARCHAR(16) NOT NULL DEFAULT '🛡️',
            description VARCHAR(300) NULL, owner_id INT UNSIGNED NULL, is_open TINYINT(1) NOT NULL DEFAULT 1,
            level TINYINT UNSIGNED NOT NULL DEFAULT 1, xp BIGINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL,
            UNIQUE KEY uq_team_name (name)) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS team_members (
            team_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, role VARCHAR(8) NOT NULL DEFAULT 'member',
            joined_at DATETIME NOT NULL, PRIMARY KEY (user_id), KEY idx_tm_team (team_id),
            CONSTRAINT fk_tm_t FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            CONSTRAINT fk_tm_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS team_requests (
            team_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, kind VARCHAR(8) NOT NULL DEFAULT 'apply',
            created_at DATETIME NOT NULL, PRIMARY KEY (team_id, user_id), KEY idx_tr_user (user_id),
            CONSTRAINT fk_tr_t FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            CONSTRAINT fk_tr_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS team_quest_done (
            team_id INT UNSIGNED NOT NULL, week_key CHAR(10) NOT NULL, code VARCHAR(10) NOT NULL, done_at DATETIME NOT NULL,
            PRIMARY KEY (team_id, week_key, code),
            CONSTRAINT fk_tq_t FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS bosses (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, team_id INT UNSIGNED NULL, tier INT UNSIGNED NOT NULL DEFAULT 1,
            boss_key VARCHAR(20) NOT NULL, name VARCHAR(60) NOT NULL, max_hp BIGINT NOT NULL, hp BIGINT NOT NULL, pool INT NOT NULL DEFAULT 0,
            status VARCHAR(8) NOT NULL DEFAULT 'active', started_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, ended_at DATETIME NULL,
            KEY idx_b_scope (team_id, status, id),
            CONSTRAINT fk_b_t FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS boss_hits (
            boss_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, damage BIGINT NOT NULL DEFAULT 0, hits INT NOT NULL DEFAULT 0,
            PRIMARY KEY (boss_id, user_id), KEY idx_bh_user (user_id),
            CONSTRAINT fk_bh_b FOREIGN KEY (boss_id) REFERENCES bosses(id) ON DELETE CASCADE,
            CONSTRAINT fk_bh_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
        $pdo->exec("CREATE TABLE IF NOT EXISTS game_hits (
            workout_id INT UNSIGNED NOT NULL PRIMARY KEY, user_id INT UNSIGNED NOT NULL, damage BIGINT NOT NULL DEFAULT 0,
            coins INT NOT NULL DEFAULT 0, boss_id INT UNSIGNED NULL, tboss_id INT UNSIGNED NULL, created_at DATETIME NOT NULL,
            KEY idx_gh (user_id, created_at), KEY idx_gh_t (created_at)) $t");
        set_setting('game_tables_ok1', '1');
        $done = true;
    } catch (Throwable $e) {
        error_log('game tables: ' . $e->getMessage());
    }
}

/* ================= Catalogue ================= */

/** key => [name, icon, slot, dmg %, coin %, price (0 = boss drop), flavour] */
function game_items(): array
{
    return [
        'iron_blade'     => ['Iron Blade', '⚔️', 'weapon', 3, 0, 150, 'A plain, honest sword.'],
        'steel_axe'      => ['Steel Axe', '🪓', 'weapon', 6, 0, 400, 'Heavy and well balanced.'],
        'rune_hammer'    => ['Rune Hammer', '🔨', 'weapon', 10, 0, 900, 'Glows when you hit a PR.'],
        'leather_vest'   => ['Leather Vest', '🦺', 'armor', 2, 0, 120, 'Better than a gym tank top.'],
        'chain_mail'     => ['Chain Mail', '⛓️', 'armor', 5, 0, 350, 'Jingles on every rep.'],
        'plate_armor'    => ['Plate Armor', '🛡️', 'armor', 8, 0, 800, 'For serious lifters.'],
        'lucky_coin'     => ['Lucky Coin', '🪙', 'charm', 0, 5, 200, '+5% coins from workouts.'],
        'gold_ring'      => ['Gold Ring', '💍', 'charm', 0, 10, 600, '+10% coins from workouts.'],
        'power_gem'      => ['Power Gem', '💎', 'charm', 5, 0, 800, 'A little extra punch.'],
        'wood_club'      => ['Wooden Club', '🏏', 'weapon', 1, 0, 60, 'Everybody starts somewhere.'],
        'war_spear'      => ['War Spear', '🔱', 'weapon', 5, 0, 280, 'Long reach, sharp point.'],
        'storm_blade'    => ['Stormblade', '🌩️', 'weapon', 13, 0, 1800, 'Crackles with lightning.'],
        'titan_maul'     => ['Titan Maul', '⚒️', 'weapon', 17, 0, 3500, 'Only for the truly dedicated.'],
        'cloth_tunic'    => ['Cloth Tunic', '👕', 'armor', 1, 0, 50, 'Comfy and cheap.'],
        'dragon_scale'   => ['Dragonscale Armor', '🐲', 'armor', 12, 0, 2000, 'Forged from a dragon you did not slay.'],
        'mythril_plate'  => ['Mythril Plate', '🏰', 'armor', 15, 0, 3600, 'Light as a feather, hard as a deadline.'],
        'leather_cap'    => ['Leather Cap', '🧢', 'helmet', 2, 0, 100, 'Keeps the sweat out.'],
        'iron_helm'      => ['Iron Helm', '⛑️', 'helmet', 4, 0, 300, 'Dents are character.'],
        'knight_helm'    => ['Knight Helm', '🪖', 'helmet', 7, 0, 900, 'Visor down, focus up.'],
        'crown_gold'     => ['Golden Crown', '🤴', 'helmet', 6, 6, 2400, 'Rich and powerful.'],
        'sandals'        => ['Rep Sandals', '🩴', 'boots', 0, 3, 90, '+3% coins. Light steps.'],
        'iron_boots'     => ['Iron Boots', '🥾', 'boots', 4, 0, 320, 'Planted like a deadlift.'],
        'wind_boots'     => ['Windrunner Boots', '👟', 'boots', 5, 6, 1200, 'For the cardio crowd.'],
        'wraps'          => ['Lifting Wraps', '🧤', 'gloves', 2, 0, 110, 'Grip it and rip it.'],
        'iron_gauntlets' => ['Gauntlets', '🥊', 'gloves', 5, 0, 450, 'Crush anything you grab.'],
        'gold_gloves'    => ['Gilded Gloves', '✨', 'gloves', 4, 8, 1500, 'Gold fingers.'],
        'clover'         => ['Four-leaf Clover', '🍀', 'charm', 0, 8, 450, '+8% coins from workouts.'],
        'ruby_amulet'    => ['Ruby Amulet', '📿', 'charm', 7, 4, 1400, 'Warm to the touch.'],
        'dragon_heart'   => ['Dragon Heart', '❤️‍🔥', 'charm', 12, 10, 3000, 'Still beating.'],
        'champ_helm'     => ['Champion\'s Helm', '👑', 'helmet', 10, 6, 0, 'Season champion reward. Not for sale.'],
        'medal_charm'    => ['Season Medal', '🏅', 'charm', 6, 4, 0, 'Season podium reward. Not for sale.'],
        'slime_crown'    => ['Slime Crown', '👑', 'charm', 4, 3, 0, 'Dropped by the Slime King.'],
        'goblin_cleaver' => ['Goblin Cleaver', '🗡️', 'weapon', 8, 0, 0, 'Dropped by the Goblin Warlord.'],
        'frost_mantle'   => ['Frost Mantle', '🧊', 'armor', 7, 0, 0, 'Dropped by the Frost Wyrm.'],
        'golem_fist'     => ['Golem Fist', '🥊', 'weapon', 9, 0, 0, 'Dropped by the Iron Golem.'],
        'wraith_cloak'   => ['Wraith Cloak', '🧥', 'armor', 8, 0, 0, 'Dropped by the Shadow Wraith.'],
        'drake_fang'     => ['Drake Fang', '🦷', 'weapon', 11, 0, 0, 'Dropped by the Fire Drake.'],
        'hydra_scale'    => ['Hydra Scale', '🐲', 'armor', 10, 0, 0, 'Dropped by the Hydra.'],
        'lich_charm'     => ['Lich Phylactery', '🔮', 'charm', 8, 8, 0, 'Dropped by the Lich Lord.'],
        // Achievement items (not sold; earned from milestones)
        'a_trainer'      => ['Trainer\'s Cap', '🧢', 'helmet', 3, 0, 0, 'Earned: Getting Serious.'],
        'a_regular'      => ['Regular\'s Gloves', '🧤', 'gloves', 3, 3, 0, 'Earned: 50 workouts.'],
        'a_centurion'    => ['Centurion Helm', '🪖', 'helmet', 8, 6, 0, 'Earned: 100 workouts.'],
        'a_swift'        => ['Swiftfoot Boots', '👟', 'boots', 4, 4, 0, 'Earned: a seven day streak.'],
        'a_unbroken'     => ['Unbroken Greaves', '🥾', 'boots', 7, 7, 0, 'Earned: a 30 day streak.'],
        'a_benchbar'     => ['Benchmark Bar', '🏋️', 'weapon', 9, 0, 0, 'Earned: bench your bodyweight.'],
        'a_quad'         => ['Quadplate', '🛡️', 'armor', 9, 0, 0, 'Earned: squat 1.5x your bodyweight.'],
        'a_grip'         => ['Iron Grip', '🥊', 'gloves', 8, 5, 0, 'Earned: deadlift 2x your bodyweight.'],
        'a_wolf'         => ['Wolf\'s Tooth', '🐺', 'charm', 4, 4, 0, 'Earned: 30 days of visits.'],
        'a_pack'         => ['Pack Aura', '🌕', 'aura', 6, 6, 0, 'Earned: 100 days of visits.'],
        'a_voice'        => ['Storyteller\'s Charm', '📜', 'charm', 3, 5, 0, 'Earned: posts on 10 days.'],
        'a_wings'        => ['Wanderer\'s Wings', '🪽', 'wings', 6, 6, 0, 'Earned: 20 days of 10,000 steps.'],
        'a_gym'          => ['Iron Home Aura', '🔥', 'aura', 8, 8, 0, 'Earned: 100 hours of training.'],
        // More gear for sale
        'trail_runners'  => ['Trail Runners', '👟', 'boots', 3, 3, 650, 'Light and quick.'],
        'mythril_boots'  => ['Mythril Boots', '🥾', 'boots', 8, 5, 2600, 'Barely feel them.'],
        'rune_gloves'    => ['Rune Gloves', '🧤', 'gloves', 7, 3, 1100, 'Engraved grips.'],
        'rune_helm'      => ['Rune Helm', '⛑️', 'helmet', 9, 3, 1600, 'Hums on heavy sets.'],
        'ruby_charm'     => ['Ruby Charm', '❤️', 'charm', 8, 0, 1400, 'Red and heavy.'],
        'emerald_charm'  => ['Emerald Charm', '💚', 'charm', 0, 14, 1500, 'Gold finds you.'],
        'dragon_scale'   => ['Dragon Scale Armor', '🐲', 'armor', 12, 0, 2200, 'Fire does nothing to it.'],
        'ember_aura'     => ['Ember Aura', '🔥', 'aura', 5, 5, 1800, 'Warm sparks around you.'],
        'spirit_wings'   => ['Spirit Wings', '🪽', 'wings', 8, 8, 3200, 'For the long haul.'],
        // Cosmetics (no stats): name effects, avatar frames, profile banners
        'c_nm_ember'     => ['Ember Name', '', 'namefx', 0, 0, 600, 'Your name burns orange.'],
        'c_nm_frost'     => ['Frost Name', '', 'namefx', 0, 0, 800, 'Cold, clean, icy.'],
        'c_nm_gold'      => ['Gold Name', '', 'namefx', 0, 0, 1200, 'A slow golden shine.'],
        'c_nm_neon'      => ['Neon Name', '', 'namefx', 0, 0, 1800, 'Glows in the dark.'],
        'c_nm_aurora'    => ['Aurora Name', '', 'namefx', 0, 0, 2500, 'Northern lights, in text.'],
        'c_nm_glitch'    => ['Glitch Name', '', 'namefx', 0, 0, 3500, 'Flickers like a bad signal.'],
        'c_fr_steel'     => ['Steel Frame', '', 'frame', 0, 0, 500, 'A solid ring around your photo.'],
        'c_fr_ember'     => ['Ember Frame', '', 'frame', 0, 0, 900, 'A warm, pulsing ring.'],
        'c_fr_ice'       => ['Ice Frame', '', 'frame', 0, 0, 900, 'Sharp and frosty.'],
        'c_fr_gold'      => ['Gold Frame', '', 'frame', 0, 0, 1600, 'For regulars.'],
        'c_fr_neon'      => ['Neon Frame', '', 'frame', 0, 0, 2200, 'A glowing cyan ring.'],
        'c_fr_prism'     => ['Prism Frame', '', 'frame', 0, 0, 3200, 'Slowly cycles through every colour.'],
        'c_bn_dusk'      => ['Dusk Banner', '', 'banner', 0, 0, 500, 'A calm evening gradient.'],
        'c_bn_forge'     => ['Forge Banner', '', 'banner', 0, 0, 1200, 'Hot metal and sparks.'],
        'c_bn_aurora'    => ['Aurora Banner', '', 'banner', 0, 0, 2000, 'Green and violet sky.'],
        'c_bn_grid'      => ['Neon Grid Banner', '', 'banner', 0, 0, 2800, 'Retro grid at night.'],
        'c_bn_summit'    => ['Summit Banner', '', 'banner', 0, 0, 3500, 'Peaks above the clouds.'],
        // Advent calendar, doors 20-24 (permanent, once a year, not for sale)
        'adv_star'       => ['Yule Star', '🌟', 'charm', 5, 5, 0, 'Advent door 20. Shines brighter every winter.'],
        'adv_aura'       => ['Frostlight Aura', '❄️', 'aura', 6, 3, 0, 'Advent door 21. A cold glow around you.'],
        'adv_antlers'    => ['Reindeer Antlers', '🦌', 'helmet', 4, 7, 0, 'Advent door 22. Surprisingly aerodynamic.'],
        'adv_cane'       => ['Candy Cane Blade', '🍭', 'weapon', 9, 0, 0, 'Advent door 23. Sweet, sharp and striped.'],
        'adv_wings'      => ['Angel Wings', '🪽', 'wings', 8, 8, 0, 'Advent door 24. The rarest gift of the year.'],
        // Boss relics: 1% chance per boss kill, ONE hero gets it. Better tiers from tougher bosses.
        'relic_1d'       => ['Bronze Relic of Might', '🟤', 'relic', 10, 0, 0, 'Boss relic. Permanent +10% damage.'],
        'relic_1c'       => ['Bronze Relic of Greed', '🟫', 'relic', 0, 10, 0, 'Boss relic. Permanent +10% coins.'],
        'relic_2d'       => ['Silver Relic of Might', '⚪', 'relic', 14, 0, 0, 'Boss relic. Permanent +14% damage.'],
        'relic_2c'       => ['Silver Relic of Greed', '🔘', 'relic', 0, 14, 0, 'Boss relic. Permanent +14% coins.'],
        'relic_3d'       => ['Golden Relic of Might', '🟡', 'relic', 17, 0, 0, 'Boss relic. Permanent +17% damage.'],
        'relic_3c'       => ['Golden Relic of Greed', '🟨', 'relic', 0, 17, 0, 'Boss relic. Permanent +17% coins.'],
        'relic_4d'       => ['Mythic Relic of Might', '🔴', 'relic', 20, 0, 0, 'Boss relic. Permanent +20% damage.'],
        'relic_4c'       => ['Mythic Relic of Greed', '🟣', 'relic', 0, 20, 0, 'Boss relic. Permanent +20% coins.'],
    ];
}

/** key => [name, icon, percent, hours, price, kind (dmg|coin)]. Longer potions are better value per day. */
function game_boosts(): array
{
    return [
        'rage'     => ['Rage Potion', '🧪', 25, 24, 30, 'dmg'],
        'rage3'    => ['Rage Draught (3 days)', '🧪', 25, 72, 70, 'dmg'],
        'rage7'    => ['Rage Brew (7 days)', '🧪', 25, 168, 140, 'dmg'],
        'titan'    => ['Titan Elixir', '⚗️', 50, 24, 80, 'dmg'],
        'titan3'   => ['Titan Elixir (3 days)', '⚗️', 50, 72, 190, 'dmg'],
        'titan7'   => ['Titan Elixir (7 days)', '⚗️', 50, 168, 400, 'dmg'],
        'giant7'   => ['Giant\'s Blood (7 days)', '🩸', 80, 168, 750, 'dmg'],
        'rage30'   => ['Rage Tonic (30 days)', '🧪', 25, 720, 450, 'dmg'],
        'titan30'  => ['Titan Tonic (30 days)', '⚗️', 50, 720, 1100, 'dmg'],
        'fortune30'=> ['Fortune Tonic (30 days)', '🍯', 20, 720, 450, 'coin'],
        'midas30'  => ['Midas Tonic (30 days)', '🏺', 40, 720, 1200, 'coin'],
        'fortune3' => ['Fortune Tonic (3 days)', '🍯', 20, 72, 90, 'coin'],
        'fortune7' => ['Fortune Tonic (7 days)', '🍯', 20, 168, 180, 'coin'],
        'midas7'   => ['Midas Brew (7 days)', '🏺', 40, 168, 450, 'coin'],
    ];
}

/** [key, name, icon, drop item] */
function game_roster(): array
{
    return [
        ['slime', 'Slime King', '🦠', 'slime_crown'], ['goblin', 'Goblin Warlord', '👺', 'goblin_cleaver'],
        ['wyrm', 'Frost Wyrm', '🐉', 'frost_mantle'], ['golem', 'Iron Golem', '🗿', 'golem_fist'],
        ['wraith', 'Shadow Wraith', '👻', 'wraith_cloak'], ['drake', 'Fire Drake', '🔥', 'drake_fang'],
        ['hydra', 'Hydra', '🐍', 'hydra_scale'], ['lich', 'Lich Lord', '💀', 'lich_charm'],
    ];
}

function game_roster_by_key(string $key): array
{
    foreach (game_roster() as $r) {
        if ($r[0] === $key) {
            return $r;
        }
    }
    return game_roster()[0];
}

/* ================= Holiday events and steps ================= */

/** Game boosts from active admin events: ['coin' => %, 'dmg' => %, 'events' => [rows]] */
function game_event_bonus(): array
{
    static $c = null;
    if ($c !== null && $c['t'] === time()) {
        return $c['v'];
    }
    $out = ['coin' => 0, 'dmg' => 0, 'events' => []];
    if (setting('game_tables_ok9') === '1') {
        $n = now();
        foreach (rows('SELECT * FROM events WHERE starts_at <= ? AND ends_at > ? AND (game_coin_pct > 0 OR game_dmg_pct > 0) ORDER BY starts_at', [$n, $n]) as $e) {
            $out['coin'] += (int)$e['game_coin_pct'];
            $out['dmg'] += (int)$e['game_dmg_pct'];
            $out['events'][] = $e;
        }
    }
    $c = ['t' => time(), 'v' => $out];
    return $out;
}

function game_event_banner(): void
{
    $b = game_event_bonus();
    $all = rows('SELECT title, points_pct FROM (SELECT title, pct AS points_pct FROM events WHERE kind = ? AND pct > 0 AND starts_at <= ? AND ends_at > ?) x', ['boost', now(), now()]);
    $names = array_unique(array_merge(array_column($b['events'], 'title'), array_column($all, 'title')));
    if (!$names) {
        return;
    }
    $bits = [];
    foreach ($all as $a) {
        $bits[] = '+' . (int)$a['points_pct'] . '% points';
    }
    if ($b['coin']) {
        $bits[] = '+' . $b['coin'] . '% coins';
    }
    if ($b['dmg']) {
        $bits[] = '+' . $b['dmg'] . '% boss damage';
    }
    echo '<div class="gevent">🎉 <b>' . e(implode(', ', $names)) . '</b>' . ($bits ? ': ' . e(implode(', ', array_unique($bits))) : '') . ' <a href="' . e(url('events')) . '">Details</a></div>';
}

/** Home page card: today's steps against the member's goal. Shows automatically once they have steps; they can switch it on or off on the Steps page. */
function game_steps_bar(array $u): void
{
    $w = game_wallet((int)$u['id']);
    $pref = $w['steps_home'] ?? null;
    if ($pref !== null && (int)$pref === 0) {
        return;
    }
    $recent = (int)val('SELECT COUNT(*) FROM daily_steps WHERE user_id = ? AND day >= ?', [(int)$u['id'], date('Y-m-d', strtotime('-3 days'))]);
    if ($pref === null && !$recent) {
        return;
    }
    $goal = max(1000, (int)($w['steps_goal'] ?? 10000));
    $steps = (int)val('SELECT COALESCE(steps,0) FROM daily_steps WHERE user_id = ? AND day = ?', [(int)$u['id'], date('Y-m-d')]);
    $pct = (int)min(100, round($steps / $goal * 100));
    echo '<a class="card stepsbar" href="' . e(url('steps')) . '"><div class="sb-top"><b>👟 ' . number_format($steps) . '</b><span class="muted small">of ' . number_format($goal) . ' steps today' . ($steps >= $goal ? ' ✅' : '') . '</span></div>'
        . '<div class="sb-track"><i style="width:' . $pct . '%"></i></div></a>';
}

/** Points earned from daily steps: one point per N steps, counting at most the daily cap. Derived, so corrections stay right. */
function game_steps_points(int $uid): int
{
    if (setting('game_tables_ok9') !== '1') {
        return 0;
    }
    $per = max(1, (int)game_cfg('steps_per_point'));
    $cap = max(0, (int)game_cfg('steps_cap_day'));
    return (int)val('SELECT COALESCE(SUM(FLOOR(LEAST(steps, ?) / ?)),0) FROM daily_steps WHERE user_id = ?', [$cap, $per, $uid]);
}

/** Steps feed the game too: new step points hit the bosses, and 5k / 10k / 15k days pay a few coins. Only for the last 2 days. */
function game_steps_rewards(int $uid, string $day): string
{
    try {
        if ($day < date('Y-m-d', strtotime('-2 days'))) {
            return '';
        }
        $r = row('SELECT steps, pts_hit, coin_paid FROM daily_steps WHERE user_id = ? AND day = ?', [$uid, $day]);
        if (!$r) {
            return '';
        }
        $per = max(1, (int)game_cfg('steps_per_point'));
        $cap = (int)game_cfg('steps_cap_day');
        $pts = intdiv(min((int)$r['steps'], $cap), $per);
        $new = $pts - (int)$r['pts_hit'];
        $msg = '';
        if ($new > 0) {
            $claimed = q('UPDATE daily_steps SET pts_hit = ? WHERE user_id = ? AND day = ? AND pts_hit = ?', [$pts, $uid, $day, (int)$r['pts_hit']])->rowCount();
            if ($claimed) {
                $user = row('SELECT * FROM users WHERE id = ?', [$uid]);
                $dmg = game_damage_for($user, $new)['dmg'];
                $boss = game_boss_public();
                $team = game_team_of($uid);
                $tboss = $team ? game_boss_team((int)$team['id']) : null;
                q('UPDATE game_wallet SET dmg_total = dmg_total + ? WHERE user_id = ?', [$dmg, $uid]);
                if ($boss) {
                    game_boss_hit($boss, $uid, $dmg, 'steps');
                }
                if ($tboss) {
                    game_boss_hit($tboss, $uid, $dmg, 'steps');
                }
                $msg = 'Your steps hit the boss for ' . number_format($dmg) . ' damage.';
            }
        }
        $tiers = [5000 => 6, 10000 => 12, 15000 => 18];
        foreach ($tiers as $need => $coins) {
            if ((int)$r['steps'] >= $need && (int)$r['coin_paid'] < $need) {
                $got = q('UPDATE daily_steps SET coin_paid = ? WHERE user_id = ? AND day = ? AND coin_paid < ?', [$need, $uid, $day, $need])->rowCount();
                if ($got) {
                    $c = (int)round($coins * ((float)game_cfg('coin_mult') ?: 1.0));
                    game_coins_add($uid, $c, 'steps', number_format($need) . ' steps on ' . $day);
                }
            }
        }
        return $msg;
    } catch (Throwable $e) {
        error_log('game_steps_rewards: ' . $e->getMessage());
        return '';
    }
}

/** One time: coins for workouts logged before the game existed for this member. */
function game_catchup(int $uid): void
{
    static $done = [];
    if (isset($done[$uid])) {
        return;
    }
    $done[$uid] = true;
    if ((int)val("SELECT COUNT(*) FROM game_ledger WHERE user_id = ? AND kind = 'catchup'", [$uid])) {
        return;
    }
    $first = (string)val('SELECT MIN(created_at) FROM game_ledger WHERE user_id = ?', [$uid]);
    $before = $first !== '' ? $first : now();
    $n = (int)val('SELECT COUNT(*) FROM workouts WHERE user_id = ? AND created_at < ?', [$uid, $before]);
    $coins = max(50, min(300, $n * 12));
    q('INSERT INTO game_ledger (user_id, amount, kind, note, created_at) VALUES (?,?,?,?,?)', [$uid, $coins, 'catchup', 'Welcome bonus for ' . $n . ' earlier workouts', now()]);
    if ($coins > 0) {
        q('UPDATE game_wallet SET coins = coins + ? WHERE user_id = ?', [$coins, $uid]);
    }
}

/** Saves a day of steps. Real phones count a whole day, so the highest value seen for the day wins for the API; manual entry replaces. */
function game_steps_set(int $uid, string $day, int $steps, int $distM, string $source): array
{
    game_ensure();
    $ts = strtotime($day);
    if (!$ts || date('Y-m-d', $ts) !== $day) {
        return ['ok' => false, 'error' => 'Bad date'];
    }
    if ($ts > strtotime('today 23:59:59') || $ts < strtotime('today -14 days')) {
        return ['ok' => false, 'error' => 'Date must be within the last 14 days'];
    }
    if ($steps < 0 || $steps > 100000 || $distM < 0 || $distM > 120000) {
        return ['ok' => false, 'error' => 'That looks too high to be real (max 100,000 steps and 120 km per day)'];
    }
    if ($distM === 0 && $steps > 0) {
        $distM = (int)round($steps * 0.75);   // average stride
    }
    if ($source === 'manual') {
        // typing a number is a deliberate correction: it replaces whatever is there
        q('INSERT INTO daily_steps (user_id, day, steps, distance_m, source, updated_at) VALUES (?,?,?,?,?,?)
           ON DUPLICATE KEY UPDATE steps = ?, distance_m = ?, source = ?, updated_at = ?', [$uid, $day, $steps, $distM, $source, now(), $steps, $distM, $source, now()]);
    } else {
        // phones and apps send running totals: a day's count only goes up, so a late or duplicate sync never lowers it
        q('INSERT INTO daily_steps (user_id, day, steps, distance_m, source, updated_at) VALUES (?,?,?,?,?,?)
           ON DUPLICATE KEY UPDATE distance_m = IF(VALUES(steps) > steps, VALUES(distance_m), distance_m), source = IF(VALUES(steps) > steps, VALUES(source), source),
           updated_at = IF(VALUES(steps) > steps, VALUES(updated_at), updated_at), steps = GREATEST(steps, VALUES(steps))', [$uid, $day, $steps, $distM, $source, now()]);
    }
    recalc_xp($uid);
    $hit = game_steps_rewards($uid, $day);
    check_awards($uid);
    $tm = game_team_of($uid);
    if ($tm) {
        game_team_check((int)$tm['id']);
    }
    return ['ok' => true, 'steps' => $steps, 'distance_m' => $distM, 'game' => $hit];
}

/* ================= News, seasons, badges ================= */

function game_announce(string $kind, string $icon, string $text, ?int $uid = null, ?int $tid = null): void
{
    try {
        q('INSERT INTO game_events (kind, icon, text, user_id, team_id, created_at) VALUES (?,?,?,?,?,?)', [$kind, $icon, mb_substr($text, 0, 220), $uid, $tid, now()]);
    } catch (Throwable $e) {
        error_log('game_announce: ' . $e->getMessage());
    }
}

function game_news(int $limit = 5, int $days = 4): array
{
    return rows('SELECT * FROM game_events WHERE created_at > ? ORDER BY id DESC LIMIT ' . (int)$limit, [date('Y-m-d H:i:s', time() - $days * 86400)]);
}

function game_news_card(int $limit = 5): void
{
    $ev = game_news($limit);
    if (!$ev) {
        return;
    }
    echo '<div class="gnews"><b>📰 Game news</b>';
    foreach ($ev as $e) {
        echo '<div class="gn-row"><span>' . e($e['icon']) . '</span><span class="grow">' . e($e['text']) . '</span><small class="muted">' . e(rel_time((string)$e['created_at'])) . '</small></div>';
    }
    echo '</div>';
}

/** Small icons after a name: team emblem (links to the team) and a trophy for season titles. Cached per request. */
function game_user_badges(string $username): string
{
    static $teams = null, $titles = null;
    if (setting('game_tables_ok9') !== '1') {
        return '';
    }
    if ($teams === null) {
        $teams = [];
        foreach (rows('SELECT us.username, t.id, t.name, t.emoji FROM team_members m JOIN users us ON us.id = m.user_id JOIN teams t ON t.id = m.team_id') as $r) {
            $teams[$r['username']] = $r;
        }
        $titles = [];
        foreach (rows('SELECT us.username, gt.title FROM game_titles gt JOIN users us ON us.id = gt.user_id ORDER BY gt.id ASC') as $r) {
            $titles[$r['username']] = $r['title'];
        }
    }
    $out = '';
    if (isset($teams[$username])) {
        $t = $teams[$username];
        $out .= ' <a class="tbadge" href="' . e(url('team/' . $t['id'])) . '" title="' . e($t['name']) . '">' . e($t['emoji']) . '</a>';
    }
    if (isset($titles[$username])) {
        $out .= ' <span class="tbadge" title="' . e($titles[$username]) . '">🏆</span>';
    }
    return $out;
}

function game_season_range(string $key): array
{
    return [$key . '-01 00:00:00', date('Y-m-d H:i:s', strtotime($key . '-01 +1 month'))];
}

function game_season_top_players(string $key, int $limit): array
{
    [$from, $to] = game_season_range($key);
    return rows('SELECT h.user_id, SUM(h.damage) AS dmg, us.username, us.display_name FROM game_hits h JOIN users us ON us.id = h.user_id
                 WHERE h.created_at >= ? AND h.created_at < ? GROUP BY h.user_id ORDER BY dmg DESC LIMIT ' . (int)$limit, [$from, $to]);
}

/** Pays out last month's season once: titles, a rare helm and coins for the top 3 players, vault and coins for the top team. */
function game_season_settle(): void
{
    $last = date('Y-m', strtotime('first day of last month'));
    if (setting('game_season_done') === $last) {
        return;
    }
    set_setting('game_season_done', $last);
    $label = date('F Y', strtotime($last . '-01'));
    $titles = ['Boss Slayer', 'Season Runner-up', 'Season Bronze'];
    $coins = [400, 250, 150];
    foreach (game_season_top_players($last, 3) as $i => $p) {
        $uid = (int)$p['user_id'];
        q('INSERT INTO game_titles (user_id, title, season, created_at) VALUES (?,?,?,?)', [$uid, $titles[$i] . ' ' . $label, $last, now()]);
        game_coins_add($uid, $coins[$i], 'season', 'Season ' . $label);
        game_give_item($uid, $i === 0 ? 'champ_helm' : 'medal_charm', 'season');
        game_notify($uid, 'boss', '🏆 Season ' . $label . ': you placed #' . ($i + 1) . '! +' . $coins[$i] . ' coins, the title "' . $titles[$i] . '" and a rare ' . ($i === 0 ? "Champion's Helm" : 'Season Medal') . '.');
        game_announce('season', ['🥇', '🥈', '🥉'][$i], display_name($p) . ' placed #' . ($i + 1) . ' in the ' . $label . ' boss season (' . number_format((int)$p['dmg']) . ' damage).', $uid);
    }
    [$from, $to] = game_season_range($last);
    $best = null;
    foreach (rows('SELECT t.id, t.name, (SELECT COUNT(*) FROM team_members m WHERE m.team_id = t.id) AS n FROM teams t') as $t) {
        if ((int)$t['n'] < 2) {
            continue;
        }
        $pts = (int)val('SELECT COALESCE(SUM(w.points),0) FROM workouts w JOIN team_members m ON m.user_id = w.user_id AND m.team_id = ? WHERE w.performed_at >= ? AND w.performed_at < ? AND w.performed_at >= m.joined_at', [$t['id'], $from, $to]);
        $avg = $pts / (int)$t['n'];
        if ($pts > 0 && (!$best || $avg > $best['avg'])) {
            $best = ['t' => $t, 'avg' => $avg];
        }
    }
    if ($best) {
        $t = $best['t'];
        q('UPDATE teams SET vault = vault + 1200 WHERE id = ?', [$t['id']]);
        foreach (rows('SELECT user_id FROM team_members WHERE team_id = ?', [$t['id']]) as $m) {
            game_coins_add((int)$m['user_id'], 100, 'season', 'Season ' . $label . ' champion team');
            q('INSERT INTO game_titles (user_id, title, season, created_at) VALUES (?,?,?,?)', [(int)$m['user_id'], 'Champion Team ' . $label, $last, now()]);
        }
        game_team_notify_all((int)$t['id'], 'team', '🏆 ' . $t['name'] . ' is the champion team of ' . $label . '! +1200 coins in the vault, +100 coins each and a title.');
        game_announce('season', '🏆', $t['name'] . ' is the champion team of ' . $label . '!', null, (int)$t['id']);
    }
}

/* ================= Daily chest ================= */

/** Coins for day 1..7 of the streak. Day 7 is the big one, then it starts over. */
function game_chest_rewards(): array
{
    return [1 => 15, 2 => 20, 3 => 25, 4 => 30, 5 => 40, 6 => 55, 7 => 120];
}

/** ['can' => bool, 'streak' => current streak, 'next_day' => 1..7, 'reward' => coins] */
function game_chest_state(int $uid): array
{
    $w = game_wallet($uid);
    $today = date('Y-m-d');
    $last = (string)($w['chest_day'] ?? '');
    $streak = (int)($w['chest_streak'] ?? 0);
    if ($last !== $today && $last !== date('Y-m-d', strtotime('yesterday'))) {
        $streak = 0;   // missed a day
    }
    $can = $last !== $today;
    $day = ($can ? $streak : $streak - 1) % 7 + 1;
    $day = $can ? $streak % 7 + 1 : $day;
    return ['can' => $can, 'streak' => $streak, 'next_day' => $day, 'reward' => (int)round(game_chest_rewards()[$day] * ((float)game_cfg('coin_mult') ?: 1.0))];
}

function game_chest_claim(int $uid): int
{
    $st = game_chest_state($uid);
    if (!$st['can']) {
        return 0;
    }
    $n = q('UPDATE game_wallet SET chest_day = ?, chest_streak = ? WHERE user_id = ? AND (chest_day IS NULL OR chest_day < ?)', [date('Y-m-d'), $st['streak'] + 1, $uid, date('Y-m-d')])->rowCount();
    if (!$n) {
        return 0;
    }
    game_coins_add($uid, $st['reward'], 'chest', 'Daily chest, day ' . $st['next_day']);
    return $st['reward'];
}

function game_chest_card(int $uid): void
{
    $st = game_chest_state($uid);
    $rw = game_chest_rewards();
    echo '<div class="card"><h3>🎁 Daily chest</h3><div class="chest-days">';
    foreach ($rw as $d => $c) {
        $done = $st['can'] ? $d <= $st['streak'] % 7 : $d <= (($st['streak'] - 1) % 7 + 1);
        echo '<span class="chest-d' . ($done ? ' done' : '') . ($d === 7 ? ' big' : '') . '"><small>Day ' . $d . '</small><b>' . ($d === 7 ? '🎁' : '🪙') . ' ' . $c . '</b></span>';
    }
    echo '</div>';
    if ($st['can']) {
        echo '<form method="post" action="' . e(url('shop')) . '">' . csrf_field() . '<input type="hidden" name="do" value="chest"><button class="btn big" type="submit">Open today\'s chest: +' . $st['reward'] . ' coins</button></form>';
    } else {
        echo '<p class="muted small" style="margin-bottom:0">Come back tomorrow to keep your streak (' . $st['streak'] . ' day' . ($st['streak'] === 1 ? '' : 's') . ').</p>';
    }
    echo '</div>';
}

/* ================= Boss relics (1% per kill, one lucky hero) ================= */

/** Roster index 0..7 -> relic tier 1..4 (tougher bosses give stronger relics). */
function game_relic_tier(string $bossKey): int
{
    foreach (game_roster() as $i => $r) {
        if ($r[0] === $bossKey) {
            return 1 + intdiv($i, 2);
        }
    }
    return 1;
}

/** 1% per killed boss; the winner is picked among the heroes weighted by damage. Returns user id or null. */
function game_relic_roll(array $hits, int $total): ?int
{
    if (!$hits || random_int(1, 10000) > 100) {
        return null;
    }
    $pick = random_int(1, max(1, $total));
    foreach ($hits as $h) {
        $pick -= (int)$h['damage'];
        if ($pick <= 0) {
            return (int)$h['user_id'];
        }
    }
    return (int)$hits[0]['user_id'];
}

function game_relic_award(int $uid, string $bossKey, string $bossName, string $bossIcon): string
{
    $cat = game_items();
    $tier = game_relic_tier($bossKey);
    $opts = ['relic_' . $tier . 'd', 'relic_' . $tier . 'c'];
    shuffle($opts);
    foreach ($opts as $code) {
        if (!(int)val('SELECT COUNT(*) FROM game_items WHERE user_id = ? AND item = ?', [$uid, $code])) {
            game_give_item($uid, $code, 'relic');
            $dn = row('SELECT username, display_name FROM users WHERE id = ?', [$uid]);
            game_announce('relic', $cat[$code][1], ($dn ? display_name($dn) : 'Someone') . ' found the legendary ' . $cat[$code][0] . ' on ' . $bossName . '! (' . ($cat[$code][3] ? '+' . $cat[$code][3] . '% damage' : '+' . $cat[$code][4] . '% coins') . ', permanent)', $uid);
            return ' ✨ LEGENDARY: you found the ' . $cat[$code][1] . ' ' . $cat[$code][0] . '! It is yours forever.';
        }
    }
    game_coins_add($uid, 400, 'drop_dup', 'Relic (duplicate)');
    return ' ✨ You rolled a legendary relic but own them already: +400 coins instead.';
}

/* ================= Advent calendar (December) ================= */

/** Today as the calendar sees it. An admin can fake the date with the setting advent_test_date (YYYY-MM-DD) to try it. */
function game_advent_date(): string
{
    $t = (string)setting('advent_test_date', '');
    return preg_match('/^\d{4}-\d\d-\d\d$/', $t) ? $t : date('Y-m-d');
}

/** ['on' => bool, 'day' => 1..24 (today's door), 'year' => int, 'over' => after the 24th] */
function game_advent_state(): array
{
    $d = game_advent_date();
    $y = (int)substr($d, 0, 4);
    $m = (int)substr($d, 5, 2);
    $n = (int)substr($d, 8, 2);
    return ['on' => $m === 12 && $n <= 24, 'over' => $m === 12 && $n > 24, 'day' => $m === 12 ? min(24, $n) : 0, 'year' => $y];
}

/** Doors 1-19: [icon, label, kind (dmg|coin|coins), pct or coins, hours]. Doors 20-24 are permanent items. */
function game_advent_rewards(): array
{
    return [
        1 => ['🧪', 'Rage Potion', 'dmg', 25, 24], 2 => ['🍯', 'Fortune Tonic', 'coin', 20, 24], 3 => ['🪙', '70 coins', 'coins', 70, 0],
        4 => ['⚗️', 'Titan Elixir', 'dmg', 50, 24], 5 => ['🏺', 'Midas Brew', 'coin', 40, 24], 6 => ['🧪', 'Rage Draught', 'dmg', 25, 48],
        7 => ['🪙', '90 coins', 'coins', 90, 0], 8 => ['⚗️', 'Titan Elixir', 'dmg', 50, 24], 9 => ['🍯', 'Fortune Tonic', 'coin', 20, 48],
        10 => ['🪙', '100 coins', 'coins', 100, 0], 11 => ['⚗️', 'Titan Elixir', 'dmg', 50, 36], 12 => ['🏺', 'Midas Brew', 'coin', 40, 24],
        13 => ['🪙', '110 coins', 'coins', 110, 0], 14 => ['🩸', 'Giant\'s Blood', 'dmg', 80, 24], 15 => ['🍯', 'Fortune Tonic', 'coin', 20, 72],
        16 => ['🪙', '120 coins', 'coins', 120, 0], 17 => ['⚗️', 'Titan Elixir', 'dmg', 50, 48], 18 => ['🏺', 'Midas Brew', 'coin', 40, 36],
        19 => ['🩸', 'Giant\'s Blood', 'dmg', 80, 36],
    ];
}

function game_advent_items(): array
{
    return [20 => 'adv_star', 21 => 'adv_aura', 22 => 'adv_antlers', 23 => 'adv_cane', 24 => 'adv_wings'];
}

function game_advent_opened(int $uid, int $year): array
{
    if (setting('advent_ok1') !== '1') {
        return [];
    }
    return array_map('intval', array_column(rows('SELECT day FROM advent_opens WHERE user_id = ? AND yr = ?', [$uid, $year]), 'day'));
}

/** Opens today's door. Returns a message, or '' when nothing could be opened. */
function game_advent_open(int $uid): string
{
    game_ensure();
    $st = game_advent_state();
    if (!$st['on']) {
        return '';
    }
    $day = $st['day'];
    $ins = q('INSERT IGNORE INTO advent_opens (user_id, yr, day, opened_at) VALUES (?,?,?,?)', [$uid, $st['year'], $day, now()]);
    if (!$ins->rowCount()) {
        return '';
    }
    $items = game_advent_items();
    if (isset($items[$day])) {
        $cat = game_items();
        $code = $items[$day];
        game_give_item($uid, $code, 'advent');
        $dn = row('SELECT username, display_name FROM users WHERE id = ?', [$uid]);
        game_announce('advent', $cat[$code][1], ($dn ? display_name($dn) : 'Someone') . ' opened door ' . $day . ' and got the ' . $cat[$code][0] . '!', $uid);
        return '🎄 Door ' . $day . ': ' . $cat[$code][1] . ' ' . $cat[$code][0] . ' (' . ($cat[$code][3] ? '+' . $cat[$code][3] . '% damage' : '') . ($cat[$code][3] && $cat[$code][4] ? ', ' : '') . ($cat[$code][4] ? '+' . $cat[$code][4] . '% coins' : '') . ', permanent). Merry Christmas!';
    }
    [$ic, $label, $kind, $pct, $hrs] = game_advent_rewards()[$day];
    if ($kind === 'coins') {
        game_coins_add($uid, (int)round($pct * ((float)game_cfg('coin_mult') ?: 1.0)), 'advent', 'Advent door ' . $day);
        return '🎄 Door ' . $day . ': ' . $ic . ' ' . $label . '!';
    }
    $col = $kind === 'coin' ? 'coin_boost' : 'boost';
    $w = game_wallet($uid);
    $curUntil = strtotime((string)($w[$col . '_until'] ?? '')) ?: 0;
    $curPct = $curUntil > time() ? (int)$w[$col . '_pct'] : 0;
    $until = max(time(), $curUntil) + $hrs * 3600;
    q("UPDATE game_wallet SET {$col}_pct = ?, {$col}_until = ? WHERE user_id = ?", [max($curPct, $pct), date('Y-m-d H:i:s', $until), $uid]);
    return '🎄 Door ' . $day . ': ' . $ic . ' ' . $label . ' (+' . max($curPct, $pct) . '% ' . ($kind === 'coin' ? 'coins' : 'damage') . ', ' . ($hrs >= 48 ? intdiv($hrs, 24) . ' days' : $hrs . ' hours') . ')';
}

/** The full calendar. $back is the page the open button returns to. */
function game_advent_card(int $uid, string $back = 'shop'): void
{
    $st = game_advent_state();
    if (!$st['on'] && !$st['over']) {
        return;
    }
    $opened = game_advent_opened($uid, $st['year']);
    $rw = game_advent_rewards();
    $it = game_advent_items();
    $cat = game_items();
    echo '<div class="card advent"><h3>🎄 Advent calendar ' . (int)$st['year'] . '</h3>';
    echo '<p class="muted small" style="margin-top:0">Everyone gets the same gift. Open each door <b>on its own day</b>, a missed door stays closed. The last five doors hold permanent treasures.</p><div class="adv-grid">';
    for ($d = 1; $d <= 24; $d++) {
        $isOpen = in_array($d, $opened, true);
        $today = $st['on'] && $d === $st['day'];
        $past = $st['over'] || $d < $st['day'];
        $ic = isset($it[$d]) ? $cat[$it[$d]][1] : $rw[$d][0];
        $cls = 'adv-d' . ($d >= 20 ? ' rare' : '') . ($isOpen ? ' got' : ($today ? ' today' : ($past ? ' missed' : ' locked')));
        if ($today && !$isOpen) {
            echo '<form method="post" action="' . e(url('shop')) . '" class="' . $cls . '">' . csrf_field() . '<input type="hidden" name="do" value="advent"><input type="hidden" name="back" value="' . e($back) . '"><button type="submit" title="Open door ' . $d . '"><b>' . $d . '</b><span>🎁</span></button></form>';
        } else {
            $label = $isOpen ? ($it[$d] ?? false ? $cat[$it[$d]][0] : $rw[$d][1]) : ($past ? 'Missed' : 'Opens Dec ' . $d);
            echo '<div class="' . $cls . '" title="' . e($label) . '"><b>' . $d . '</b><span>' . ($isOpen ? $ic : ($past ? '✖' : '🔒')) . '</span></div>';
        }
    }
    echo '</div>';
    if ($st['on']) {
        $tmr = $st['day'] + 1;
        if ($tmr <= 24) {
            echo '<p class="muted small" style="margin-bottom:0">Tomorrow: door ' . $tmr . ($tmr >= 20 ? ' — a permanent item! 🌟' : '') . '</p>';
        }
    }
    echo '</div>';
}

/** Big, hard-to-miss card at the top of the feed: today's chest and today's advent door. Slim when everything is opened. */
function game_today_card(array $u): void
{
    game_ensure();
    $uid = (int)$u['id'];
    $ch = game_chest_state($uid);
    $adv = game_advent_state();
    $advOpen = $adv['on'] && !in_array($adv['day'], game_advent_opened($uid, $adv['year']), true);
    if (!$ch['can'] && !$advOpen) {
        echo '<div class="todaybar"><span>🎁 Chest opened · ' . (int)$ch['streak'] . '-day streak</span>' . ($adv['on'] ? '<a href="' . e(url('shop')) . '">🎄 Advent calendar</a>' : '') . '</div>';
        return;
    }
    echo '<section class="card todaycard">';
    if ($ch['can']) {
        echo '<form method="post" action="' . e(url('shop')) . '" class="tc-row">' . csrf_field() . '<input type="hidden" name="do" value="chest"><input type="hidden" name="back" value="feed">'
           . '<span class="tc-ic">🎁</span><div class="tc-txt"><b>Daily chest is ready</b><small>Day ' . (int)$ch['next_day'] . ' of 7 · +' . (int)$ch['reward'] . ' coins' . ($ch['streak'] ? ' · keep your ' . (int)$ch['streak'] . '-day streak' : '') . '</small></div>'
           . '<button class="btn" type="submit">Open</button></form>';
    }
    if ($advOpen) {
        $isRare = $adv['day'] >= 20;
        echo '<form method="post" action="' . e(url('shop')) . '" class="tc-row adv">' . csrf_field() . '<input type="hidden" name="do" value="advent"><input type="hidden" name="back" value="feed">'
           . '<span class="tc-ic">🎄</span><div class="tc-txt"><b>Advent door ' . (int)$adv['day'] . ' is open today</b><small>' . ($isRare ? 'A permanent treasure! Only today.' : 'Same gift for everyone. Only today.') . '</small></div>'
           . '<button class="btn" type="submit">Open</button></form>';
    }
    echo '</section>';
}

/* ================= Wallet ================= */

function game_wallet(int $uid): array
{
    game_ensure();
    q('INSERT IGNORE INTO game_wallet (user_id) VALUES (?)', [$uid]);
    game_catchup($uid);
    return row('SELECT * FROM game_wallet WHERE user_id = ?', [$uid]) ?: ['coins' => 0, 'boost_pct' => 0, 'boost_until' => null, 'dmg_total' => 0, 'kills' => 0];
}

function game_coins_add(int $uid, int $n, string $kind, string $note = ''): void
{
    if ($n === 0) {
        return;
    }
    game_wallet($uid);
    q('UPDATE game_wallet SET coins = GREATEST(0, coins + ?) WHERE user_id = ?', [$n, $uid]);
    q('INSERT INTO game_ledger (user_id, amount, kind, note, created_at) VALUES (?,?,?,?,?)', [$uid, $n, $kind, mb_substr($note, 0, 120), now()]);
}

function game_notify(int $uid, string $type, string $text): void
{
    q('INSERT INTO notifications (user_id, type, text, created_at) VALUES (?,?,?,?)', [$uid, $type, mb_substr($text, 0, 200), now()]);
}

/** Equipped gear totals: ['dmg'=>%, 'coin'=>%, 'icons'=>[...], 'by_slot'=>[slot=>key]] */
function game_gear(int $uid): array
{
    game_ensure();
    $cat = game_items();
    $out = ['dmg' => 0, 'coin' => 0, 'icons' => [], 'by_slot' => []];
    foreach (rows('SELECT item FROM game_items WHERE user_id = ? AND equipped = 1', [$uid]) as $r) {
        if (!isset($cat[$r['item']])) {
            continue;
        }
        [$n, $ic, $slot, $d, $c] = $cat[$r['item']];
        $out['dmg'] += $d;
        $out['coin'] += $c;
        if ($ic !== '') {
            $out['icons'][] = $ic;
        }
        $out['by_slot'][$slot] = $r['item'];
    }
    return $out;
}

/** Equipped cosmetics for every user that has any: username => ['namefx'=>'ember','frame'=>'gold','banner'=>'dusk']. One small query per request. */
function cosm_all(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    game_ensure();
    $cat = game_items();
    foreach (rows("SELECT u.username, i.item FROM game_items i JOIN users u ON u.id = i.user_id WHERE i.equipped = 1 AND i.item LIKE 'c\\_%' LIMIT 3000") as $r) {
        if (isset($cat[$r['item']])) {
            $map[$r['username']][$cat[$r['item']][2]] = substr($r['item'], 5);
        }
    }
    return $map;
}

/** CSS class (with leading space) for a member's equipped name effect. */
function uname_cls(string $username): string
{
    $c = cosm_all()[$username]['namefx'] ?? '';
    return $c !== '' ? ' nfx nfx-' . $c : '';
}

/** Small CSS-only preview for a cosmetic shop item. */
function cosm_preview(string $key, string $slot): string
{
    $id = substr($key, 5);
    if ($slot === 'namefx') {
        return '<span class="cpv"><b class="nfx nfx-' . e($id) . '">Your name</b></span>';
    }
    if ($slot === 'frame') {
        return '<span class="cpv"><span class="frm frm-' . e($id) . '"><span class="cpv-av"></span></span></span>';
    }
    return '<span class="cpv"><span class="cpv-bn bn-' . e($id) . '"></span></span>';
}

function game_coin_boost_active(array $wallet): int
{
    return (!empty($wallet['coin_boost_until']) && strtotime((string)$wallet['coin_boost_until']) > time()) ? (int)$wallet['coin_boost_pct'] : 0;
}

function game_boost_active(array $wallet): int
{
    return (!empty($wallet['boost_until']) && strtotime((string)$wallet['boost_until']) > time()) ? (int)$wallet['boost_pct'] : 0;
}

/** Damage for a workout: points x level bonus x gear x boost. Higher levels hit harder. */
function game_damage_for(array $user, int $points): array
{
    $w = game_wallet((int)$user['id']);
    $gear = game_gear((int)$user['id']);
    $lvlMult = 1 + (int)$user['level'] / 20;
    $boost = game_boost_active($w);
    $tm = game_team_of((int)$user['id']);
    $team = $tm ? game_team_bonus((int)$tm['id'])['dmg'] : 0;
    $dmg = (int)round($points * $lvlMult * (1 + $gear['dmg'] / 100) * (1 + $boost / 100) * (1 + $team / 100) * (1 + game_event_bonus()['dmg'] / 100));
    return ['dmg' => max(1, $dmg), 'level_mult' => $lvlMult, 'gear' => $gear['dmg'], 'boost' => $boost, 'team' => $team];
}

/* ================= Bosses ================= */

/** Team level needed to challenge each roster boss (same order as game_roster). */
function game_raid_unlocks(): array
{
    return [1, 1, 2, 3, 4, 5, 7, 9];
}

/** Harder raid bosses have more HP and pay more: 1.0, 1.2, 1.4 ... */
function game_raid_diff(string $key): float
{
    foreach (game_roster() as $i => $r) {
        if ($r[0] === $key) {
            return 1 + 0.2 * $i;
        }
    }
    return 1.0;
}

function game_boss_spawn(?int $teamId, ?string $key = null): ?array
{
    game_ensure();
    $lock = (string)val('SELECT GET_LOCK(?, 3)', ['fit_boss_' . ($teamId ?? 0)]);
    if ($lock !== '1') {
        return null;
    }
    try {
        $exists = row("SELECT * FROM bosses WHERE " . ($teamId === null ? 'team_id IS NULL' : 'team_id = ' . (int)$teamId) . " AND status = 'active' ORDER BY id DESC LIMIT 1");
        if ($exists) {
            return $exists;
        }
        $tier = 1 + (int)val('SELECT COUNT(*) FROM bosses WHERE ' . ($teamId === null ? 'team_id IS NULL' : 'team_id = ' . (int)$teamId));
        if ($teamId !== null && $key !== null) {
            $r = game_roster_by_key($key);
            $round = 1 + (int)val('SELECT COUNT(*) FROM bosses WHERE team_id = ? AND boss_key = ?', [$teamId, $r[0]]);
        } else {
            $r = game_roster()[($tier - 1) % count(game_roster())];
            $round = intdiv($tier - 1, count(game_roster())) + 1;
        }
        $mult = (float)game_cfg('boss_hp_mult') ?: 1.0;
        if ($teamId === null) {
            $recent = (int)val('SELECT COALESCE(SUM(damage),0) FROM game_hits WHERE created_at > ?', [date('Y-m-d H:i:s', time() - 7 * 86400)]);
            $hp = (int)round(max(GAME_BOSS_MIN_HP, $recent * 1.1) * $mult * (1 + 0.04 * ($tier - 1)));
            $name = $r[1] . ($round > 1 ? ' (Lv ' . $round . ')' : '');
        } else {
            $members = max(1, (int)val('SELECT COUNT(*) FROM team_members WHERE team_id = ?', [$teamId]));
            $lvl = (int)val('SELECT level FROM teams WHERE id = ?', [$teamId]);
            $hp = (int)round((900 + $members * 1300) * $mult * (1 + 0.10 * ($lvl - 1)) * (1 + 0.03 * ($tier - 1)) * game_raid_diff($r[0]));
            $name = 'Raid: ' . $r[1] . ($round > 1 ? ' (Lv ' . $round . ')' : '');
        }
        q("INSERT INTO bosses (team_id, tier, boss_key, name, max_hp, hp, status, started_at, ends_at) VALUES (?,?,?,?,?,?,?,?,?)",
          [$teamId, $tier, $r[0], $name, $hp, $hp, 'active', now(), date('Y-m-d H:i:s', time() + GAME_BOSS_DAYS * 86400)]);
        return row('SELECT * FROM bosses WHERE id = ?', [(int)db()->lastInsertId()]);
    } finally {
        q('SELECT RELEASE_LOCK(?)', ['fit_boss_' . ($teamId ?? 0)]);
    }
}

/** The boss that can be hit right now (spawns or retires bosses as needed). Null while the next one is on its way. */
function game_boss_public(): ?array
{
    game_ensure();
    $b = row("SELECT * FROM bosses WHERE team_id IS NULL AND status = 'active' ORDER BY id DESC LIMIT 1");
    if ($b && strtotime((string)$b['ends_at']) <= time()) {
        game_boss_end($b, false);
        $b = null;
    }
    if ($b) {
        return $b;
    }
    $last = row('SELECT * FROM bosses WHERE team_id IS NULL ORDER BY id DESC LIMIT 1');
    if ($last && $last['status'] === 'dead' && $last['ended_at'] && strtotime((string)$last['ended_at']) + (int)game_cfg('respawn_hours') * 3600 > time()) {
        return null;
    }
    return game_boss_spawn(null);
}

function game_boss_team(int $teamId): ?array
{
    game_ensure();
    $b = row("SELECT * FROM bosses WHERE team_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1", [$teamId]);
    if ($b && strtotime((string)$b['ends_at']) <= time()) {
        game_boss_end($b, false);
        $b = null;
    }
    if ($b) {
        return $b;
    }
    $last = row('SELECT * FROM bosses WHERE team_id = ? ORDER BY id DESC LIMIT 1', [$teamId]);
    $ref = $last ? strtotime((string)($last['ended_at'] ?: $last['started_at'])) : strtotime((string)val('SELECT created_at FROM teams WHERE id = ?', [$teamId]));
    if ($ref + (int)game_cfg('respawn_hours') * 3600 > time()) {
        return null;
    }
    // Leaders pick the next raid boss. If nobody does within 24 hours, the next one in the cycle appears.
    return $ref + 86400 > time() ? null : game_boss_spawn($teamId);
}

function game_boss_hit(array $boss, int $uid, int $dmg, string $src = 'workout'): void
{
    $left = (int)val("SELECT hp FROM bosses WHERE id = ? AND status = 'active'", [$boss['id']]);
    if ($left <= 0) {
        return;
    }
    $dmg = min($dmg, $left);   // the killing blow only counts up to the boss's remaining health
    if ($dmg > 0 && setting('boss_log_ok1') === '1') {
        try { q('INSERT INTO boss_log (boss_id, user_id, damage, src, created_at) VALUES (?,?,?,?,?)', [$boss['id'], $uid, $dmg, $src, now()]); } catch (Throwable $e) { error_log('boss_log: ' . $e->getMessage()); }
    }
    q('INSERT INTO boss_hits (boss_id, user_id, damage, hits) VALUES (?,?,?,1)
       ON DUPLICATE KEY UPDATE damage = damage + VALUES(damage), hits = hits + 1', [$boss['id'], $uid, $dmg]);
    q("UPDATE bosses SET hp = GREATEST(0, hp - ?) WHERE id = ? AND status = 'active'", [$dmg, $boss['id']]);
    if ((int)val('SELECT hp FROM bosses WHERE id = ?', [$boss['id']]) <= 0) {
        $n = q("UPDATE bosses SET status = 'dead', ended_at = ? WHERE id = ? AND status = 'active'", [now(), $boss['id']])->rowCount();
        if ($n) {
            game_boss_end(row('SELECT * FROM bosses WHERE id = ?', [$boss['id']]), true);
        }
    }
}

/** Recent hits on a boss. Several hits by the same person close together fold into one row you can open. */
function game_boss_log_card(int $bossId, ?int $meId = null, bool $inner = false): void
{
    if (setting('boss_log_ok1') !== '1') {
        return;
    }
    $rows = rows('SELECT l.*, us.username, us.display_name FROM boss_log l JOIN users us ON us.id = l.user_id WHERE l.boss_id = ? ORDER BY l.id DESC LIMIT 120', [$bossId]);
    echo $inner ? '<div class="hitlog"><h4 style="margin:.8rem 0 .3rem">Recent hits</h4>' : '<div class="card"><h3>⚔️ Recent hits</h3>';
    if (!$rows) {
        echo '<p class="muted">No hits yet.</p></div>';
        return;
    }
    $groups = [];
    foreach ($rows as $r) {
        $last = count($groups) - 1;
        if ($last >= 0 && (int)$groups[$last]['uid'] === (int)$r['user_id'] && strtotime((string)$groups[$last]['oldest']) - strtotime((string)$r['created_at']) < 21600) {
            $groups[$last]['rows'][] = $r;
            $groups[$last]['oldest'] = $r['created_at'];
            continue;
        }
        $groups[] = ['uid' => (int)$r['user_id'], 'rows' => [$r], 'oldest' => $r['created_at'], 'u' => $r];
    }
    $label = fn(array $r) => $r['src'] === 'steps' ? 'Steps' : 'Workout';
    foreach (array_slice($groups, 0, 25) as $gp) {
        $u = $gp['u'];
        $name = '<a href="' . e(url('u/' . $u['username'])) . '">' . e(display_name($u)) . '</a>';
        $cls = $meId && $gp['uid'] === $meId ? ' me' : '';
        $n = count($gp['rows']);
        $sum = array_sum(array_map(fn($x) => (int)$x['damage'], $gp['rows']));
        if ($n === 1) {
            $r = $gp['rows'][0];
            echo '<div class="gb-row hitrow' . $cls . '"><span>' . ($r['src'] === 'steps' ? '👟' : '🏋️') . '</span><span class="grow">' . $name . ' <small class="muted">' . ($r['src'] === 'steps' ? 'steps' : 'workout') . '</small><br><small class="muted">' . e(rel_time((string)$r['created_at'])) . '</small></span><b>' . number_format($sum) . '</b></div>';
            continue;
        }
        $w = count(array_filter($gp['rows'], fn($x) => $x['src'] !== 'steps'));
        $st = $n - $w;
        $parts = array_filter([$w ? $w . ' workout' . ($w > 1 ? 's' : '') : '', $st ? $st . ' step update' . ($st > 1 ? 's' : '') : '']);
        echo '<details class="hitgrp' . $cls . '"><summary class="gb-row hitrow"><span class="chev">▸</span><span class="grow">' . $name . ' <small class="muted">' . $n . ' hits · ' . e(implode(', ', $parts)) . '</small><br><small class="muted">' . e(rel_time((string)$gp['rows'][0]['created_at'])) . '</small></span><b>' . number_format($sum) . '</b></summary>';
        foreach ($gp['rows'] as $r) {
            echo '<div class="gb-row hitsub"><span>' . ($r['src'] === 'steps' ? '👟' : '🏋️') . '</span><span class="grow"><small class="muted">' . e($label($r)) . ' · ' . e(rel_time((string)$r['created_at'])) . '</small></span><span>' . number_format((int)$r['damage']) . '</span></div>';
        }
        echo '</details>';
    }
    echo '</div>';
}

/** Pays out a finished boss. $killed false = it ran away (a quarter of the pool is still shared out). */
function game_boss_end(array $b, bool $killed): void
{
    if (!$killed) {
        $n = q("UPDATE bosses SET status = 'fled', ended_at = ? WHERE id = ? AND status = 'active'", [now(), $b['id']])->rowCount();
        if (!$n) {
            return;
        }
    }
    $hits = rows('SELECT user_id, damage FROM boss_hits WHERE boss_id = ? AND damage > 0', [$b['id']]);
    if ($killed && $b['team_id'] === null && $hits) {
        usort($hits, fn($x, $y) => (int)$y['damage'] <=> (int)$x['damage']);
        $topU = row('SELECT username, display_name FROM users WHERE id = ?', [$hits[0]['user_id']]);
        $rr = game_roster_by_key((string)$b['boss_key']);
        game_announce('boss', $rr[2], $b['name'] . ' was slain by ' . count($hits) . ' heroes! Biggest hitter: ' . ($topU ? display_name($topU) : '?') . ' (' . number_format((int)$hits[0]['damage']) . ').');
    }
    $total = max(1, array_sum(array_map(fn($h) => (int)$h['damage'], $hits)));
    $isTeam = $b['team_id'] !== null;
    $cm = (float)game_cfg('coin_mult') ?: 1.0;
    $diff = $isTeam ? game_raid_diff((string)$b['boss_key']) : 1.0;
    $pool = $isTeam ? (int)round((40 + 15 * count($hits)) * $diff) : min(500, 100 + 20 * count($hits));
    $pool = (int)round($pool * $cm);
    if (!$killed) {
        $pool = (int)round($pool * 0.25);
    }
    q('UPDATE bosses SET pool = ? WHERE id = ?', [$pool, $b['id']]);
    $roster = game_roster_by_key((string)$b['boss_key']);
    $cat = game_items();
    $mythWin = $killed ? game_relic_roll($hits, $total) : null;
    foreach ($hits as $h) {
        $uid = (int)$h['user_id'];
        $share = (int)$h['damage'] / $total;
        $coins = $pool > 0 ? max($killed ? 5 : 1, (int)round($pool * $share)) : 0;
        $msg = $killed ? $b['name'] . ' was defeated! You did ' . round($share * 100) . '% of the damage: +' . $coins . ' coins' : $b['name'] . ' escaped. You still earn +' . $coins . ' coins';
        if ($killed) {
            q('UPDATE game_wallet SET kills = kills + 1 WHERE user_id = ?', [$uid]);
        }
        game_coins_add($uid, $coins, $killed ? 'boss' : 'boss_fled', $b['name']);
        if ($killed && !$isTeam) {
            $chance = min(0.95, 0.05 + sqrt($share));
            $drop = $roster[3];
            if (isset($cat[$drop]) && random_int(1, 10000) <= (int)round($chance * 10000)) {
                $has = (int)val('SELECT COUNT(*) FROM game_items WHERE user_id = ? AND item = ?', [$uid, $drop]);
                if ($has) {
                    game_coins_add($uid, 40, 'drop_dup', $cat[$drop][0]);
                    $msg .= ' and a spare ' . $cat[$drop][0] . ' (sold for 40)';
                } else {
                    game_give_item($uid, $drop, 'drop');
                    $dn = row('SELECT username, display_name FROM users WHERE id = ?', [$uid]);
                    game_announce('drop', $cat[$drop][1], ($dn ? display_name($dn) : 'Someone') . ' won the rare ' . $cat[$drop][0] . ' from ' . $b['name'] . '!', $uid);
                    $msg .= ' and a ' . $cat[$drop][1] . ' ' . $cat[$drop][0] . '!';
                }
            }
        }
        if ($mythWin === $uid) {
            $msg .= game_relic_award($uid, (string)$b['boss_key'], (string)$b['name'], (string)$roster[2]);
        }
        game_notify($uid, 'boss', $msg);
        if ($killed) {
            check_awards($uid);
        }
    }
    if ($isTeam && $killed) {
        $vaultGain = (int)round((60 + 15 * count($hits)) * $diff);
        game_announce('raid', $roster[2], (string)val('SELECT name FROM teams WHERE id = ?', [$b['team_id']]) . ' defeated ' . $b['name'] . '!', null, (int)$b['team_id']);
        q('UPDATE teams SET vault = vault + ? WHERE id = ?', [$vaultGain, $b['team_id']]);
        game_team_notify_all((int)$b['team_id'], 'team', '🏦 ' . $b['name'] . ' dropped loot: +' . $vaultGain . ' coins in the team vault.');
        game_team_xp_add((int)$b['team_id'], (int)round((120 + 15 * count($hits)) * $diff * (float)game_cfg('team_xp_mult')), 'raid');
    }
}

function game_give_item(int $uid, string $item, string $source): void
{
    $cat = game_items();
    if (!isset($cat[$item])) {
        return;
    }
    q('INSERT IGNORE INTO game_items (user_id, item, source, got_at, equipped) VALUES (?,?,?,?,0)', [$uid, $item, $source, now()]);
    $slot = $cat[$item][2];
    $same = array_keys(array_filter($cat, fn($i) => $i[2] === $slot));
    $taken = (int)val('SELECT COUNT(*) FROM game_items WHERE user_id = ? AND equipped = 1 AND item IN (' . in_clause($same) . ')', array_merge([$uid], $same));
    if (!$taken) {
        q('UPDATE game_items SET equipped = 1 WHERE user_id = ? AND item = ?', [$uid, $item]);
    }
}

/* ================= Workout hooks ================= */

/** Called after a new workout is saved. Returns a short text for the user, or ''. */
function game_on_workout(array $user, int $workoutId, int $points): string
{
    try {
        game_ensure();
        $uid = (int)$user['id'];
        if ($points <= 0) {
            return '';
        }
        $ins = q('INSERT IGNORE INTO game_hits (workout_id, user_id, damage, coins, created_at) VALUES (?,?,0,0,?)', [$workoutId, $uid, now()]);
        if (!$ins->rowCount()) {
            return '';
        }
        $d = game_damage_for($user, $points);
        $dmg = $d['dmg'];
        // coins: the first two workouts of the day pay
        $today = date('Y-m-d 00:00:00');
        $paid = (int)val('SELECT COUNT(*) FROM game_hits WHERE user_id = ? AND coins > 0 AND created_at >= ?', [$uid, $today]);
        $coins = 0;
        if ($paid < GAME_COIN_WORKOUTS_PER_DAY) {
            $gear = game_gear($uid);
            $tmc = game_team_of($uid);
            $teamCoin = $tmc ? game_team_bonus((int)$tmc['id'])['coin'] : 0;
            $coins = (int)round((6 + min(14, $points / 100)) * ((float)game_cfg('coin_mult') ?: 1.0) * (1 + ($gear['coin'] + $teamCoin + game_coin_boost_active(game_wallet($uid)) + game_event_bonus()['coin']) / 100));
        }
        $boss = game_boss_public();
        $team = game_team_of($uid);
        $tboss = $team ? game_boss_team((int)$team['id']) : null;
        q('UPDATE game_hits SET damage = ?, coins = ?, boss_id = ?, tboss_id = ? WHERE workout_id = ?',
          [$dmg, $coins, $boss['id'] ?? null, $tboss['id'] ?? null, $workoutId]);
        q('UPDATE game_wallet SET dmg_total = dmg_total + ? WHERE user_id = ?', [$dmg, $uid]);
        if ($coins) {
            game_coins_add($uid, $coins, 'workout', 'Workout #' . $workoutId);
        }
        $name = '';
        if ($boss) {
            game_boss_hit($boss, $uid, $dmg);
            $name = $boss['name'];
        }
        if ($tboss) {
            game_boss_hit($tboss, $uid, $dmg);
        }
        if ($team) {
            game_team_check((int)$team['id']);
        }
        $txt = '⚔️ ' . ($name ? 'You hit ' . $name . ' for ' . number_format($dmg) . ' damage' : 'Boss attack: ' . number_format($dmg) . ' damage')
             . ($coins ? ' · +' . $coins . ' coins 🪙' : '');
        return $txt;
    } catch (Throwable $e) {
        error_log('game_on_workout: ' . $e->getMessage());
        return '';
    }
}

/** A deleted workout takes its damage and coins back (only while the boss is still alive). */
function game_on_delete(int $workoutId): void
{
    try {
        if (setting('game_tables_ok1') !== '1') {
            return;
        }
        $h = row('SELECT * FROM game_hits WHERE workout_id = ?', [$workoutId]);
        if (!$h) {
            return;
        }
        foreach ([$h['boss_id'], $h['tboss_id']] as $bid) {
            if (!$bid) {
                continue;
            }
            $b = row("SELECT id, max_hp FROM bosses WHERE id = ? AND status = 'active'", [$bid]);
            if ($b) {
                q('UPDATE boss_hits SET damage = GREATEST(0, damage - ?), hits = GREATEST(0, hits - 1) WHERE boss_id = ? AND user_id = ?', [$h['damage'], $bid, $h['user_id']]);
                q('UPDATE bosses SET hp = LEAST(max_hp, hp + ?) WHERE id = ?', [$h['damage'], $bid]);
            }
        }
        q('UPDATE game_wallet SET dmg_total = GREATEST(0, dmg_total - ?) WHERE user_id = ?', [$h['damage'], $h['user_id']]);
        if ((int)$h['coins'] > 0) {
            game_coins_add((int)$h['user_id'], -(int)$h['coins'], 'undo', 'Workout deleted');
        }
        q('DELETE FROM game_hits WHERE workout_id = ?', [$workoutId]);
    } catch (Throwable $e) {
        error_log('game_on_delete: ' . $e->getMessage());
    }
}

/* ================= Teams ================= */

/** Lifetime stats for a team, counted from the day each member joined. */
function game_team_stats(int $tid): array
{
    $j = 'FROM workout_sets s JOIN workouts w ON w.id = s.workout_id JOIN team_members m ON m.user_id = w.user_id AND m.team_id = ? WHERE w.performed_at >= m.joined_at AND s.is_warmup = 0';
    $a = row("SELECT COALESCE(SUM(s.weight_kg * s.reps),0) AS kg, COALESCE(SUM(s.distance_m),0) / 1000 AS km, COALESCE(SUM(s.is_pr),0) AS prs $j", [$tid]) ?: [];
    $w = row('SELECT COUNT(*) AS wo, COALESCE(SUM(w.points),0) AS pts FROM workouts w JOIN team_members m ON m.user_id = w.user_id AND m.team_id = ? WHERE w.performed_at >= m.joined_at', [$tid]) ?: [];
    return [
        'kg' => (float)($a['kg'] ?? 0), 'km' => (float)($a['km'] ?? 0), 'prs' => (int)($a['prs'] ?? 0),
        'wo' => (int)($w['wo'] ?? 0), 'pts' => (int)($w['pts'] ?? 0),
        'kills' => (int)val("SELECT COUNT(*) FROM bosses WHERE team_id = ? AND status = 'dead'", [$tid]),
        'dmg' => (int)val('SELECT COALESCE(SUM(h.damage),0) FROM boss_hits h JOIN bosses b ON b.id = h.boss_id WHERE b.team_id = ?', [$tid]),
    ];
}

/** code => [name, icon, kind (dmg|coin|xp), percent, hours, vault cost, description] */
function game_team_buffs_def(): array
{
    return [
        'battle_cry' => ['Battle Cry', '📣', 'dmg', 10, 168, 450, '+10% boss damage for everyone, 7 days'],
        'warband'    => ['Warband', '🔥', 'dmg', 20, 168, 1200, '+20% boss damage for everyone, 7 days'],
        'fortune'    => ['Fortune', '💰', 'coin', 15, 168, 350, '+15% coins from workouts, 7 days'],
        'treasure'   => ['Treasure Hunt', '🏴‍☠️', 'coin', 30, 168, 900, '+30% coins from workouts, 7 days'],
        'rally'      => ['Rally', '📚', 'xp', 25, 168, 250, '+25% team points from quests and raids, 7 days'],
    ];
}

/** What a team gives its members right now: permanent perks from its level plus active vault buffs. */
function game_team_bonus(?int $teamId): array
{
    $out = ['dmg' => 0, 'coin' => 0, 'xp' => 0, 'passive_dmg' => 0, 'passive_coin' => 0, 'active' => []];
    if (!$teamId) {
        return $out;
    }
    $lvl = (int)val('SELECT level FROM teams WHERE id = ?', [$teamId]);
    $out['passive_dmg'] = max(0, $lvl - 1);              // +1% damage per level above 1
    $out['passive_coin'] = intdiv(max(0, $lvl - 1), 2) * 2; // +2% coins every second level
    $out['dmg'] = $out['passive_dmg'];
    $out['coin'] = $out['passive_coin'];
    if (setting('game_tables_ok9') !== '1') {
        return $out;
    }
    $def = game_team_buffs_def();
    foreach (rows('SELECT code, until FROM team_buffs WHERE team_id = ? AND until > ?', [$teamId, now()]) as $b) {
        if (!isset($def[$b['code']])) {
            continue;
        }
        [$n, $ic, $kind, $pct] = $def[$b['code']];
        $out[$kind] += $pct;
        $out['active'][$b['code']] = $b['until'];
    }
    return $out;
}


function game_team_of(int $uid): ?array
{
    game_ensure();
    return row('SELECT t.*, m.role, m.joined_at FROM team_members m JOIN teams t ON t.id = m.team_id WHERE m.user_id = ?', [$uid]);
}

function game_team_xp_need(int $level): int
{
    return 200 * ($level - 1) * ($level - 1);
}

function game_team_max_level(): int
{
    return max(2, min(30, (int)game_cfg('max_team_level')));
}

function game_team_level_for(int $xp): int
{
    $max = game_team_max_level();
    $l = 1;
    while ($l < $max && $xp >= game_team_xp_need($l + 1)) {
        $l++;
    }
    return $l;
}

function game_team_slots(int $level): int
{
    $list = array_values(array_filter(array_map('intval', explode(',', game_cfg('team_slots'))), fn($n) => $n > 0));
    if (!$list) {
        $list = [5];
    }
    return $list[min($level, count($list)) - 1];
}

function game_team_notify_all(int $teamId, string $type, string $text, ?int $exceptUid = null): void
{
    foreach (rows('SELECT user_id FROM team_members WHERE team_id = ?', [$teamId]) as $m) {
        if ($exceptUid === null || (int)$m['user_id'] !== $exceptUid) {
            game_notify((int)$m['user_id'], $type, $text);
        }
    }
}

function game_team_xp_add(int $teamId, int $xp, string $why): void
{
    if ($xp <= 0) {
        return;
    }
    if ($why === 'quest' || $why === 'raid') {
        $xp = (int)round($xp * (1 + game_team_bonus($teamId)['xp'] / 100));
    }
    q('UPDATE teams SET xp = xp + ? WHERE id = ?', [$xp, $teamId]);
    $t = row('SELECT * FROM teams WHERE id = ?', [$teamId]);
    if (!$t) {
        return;
    }
    $new = game_team_level_for((int)$t['xp']);
    if ($new > (int)$t['level']) {
        q('UPDATE teams SET level = ? WHERE id = ?', [$new, $teamId]);
        game_team_notify_all($teamId, 'team', '🏰 ' . $t['name'] . ' reached team level ' . $new . '! Room for ' . game_team_slots($new) . ' members now.');
        game_announce('team', '🏰', $t['name'] . ' reached team level ' . $new . '!', null, $teamId);
    }
}

function game_week_key(): string
{
    return date('Y-m-d', strtotime('monday this week'));
}

/** This week's team quests with progress. */
function game_team_quests(array $team): array
{
    $tid = (int)$team['id'];
    $wk = game_week_key();
    $n = max(1, (int)val('SELECT COUNT(*) FROM team_members WHERE team_id = ?', [$tid]));
    $from = $wk . ' 00:00:00';
    $to = date('Y-m-d H:i:s', strtotime($wk . ' +7 days'));
    $j = 'FROM workouts w JOIN team_members m ON m.user_id = w.user_id AND m.team_id = ? WHERE w.performed_at >= ? AND w.performed_at < ? AND w.performed_at >= m.joined_at';
    $a = row("SELECT COUNT(*) wo, COALESCE(SUM(w.points),0) pts, COUNT(DISTINCT CONCAT(w.user_id, '-', DATE(w.performed_at))) days $j", [$tid, $from, $to]) ?: [];
    $km = (float)val("SELECT COALESCE(SUM(s.distance_m),0) FROM workout_sets s JOIN workouts w ON w.id = s.workout_id JOIN team_members m ON m.user_id = w.user_id AND m.team_id = ? WHERE w.performed_at >= ? AND w.performed_at < ? AND w.performed_at >= m.joined_at", [$tid, $from, $to]) / 1000;
    $pr = (int)val("SELECT COALESCE(SUM(s.is_pr),0) FROM workout_sets s JOIN workouts w ON w.id = s.workout_id JOIN team_members m ON m.user_id = w.user_id AND m.team_id = ? WHERE w.performed_at >= ? AND w.performed_at < ? AND w.performed_at >= m.joined_at", [$tid, $from, $to]);
    $stepsTot = (int)val('SELECT COALESCE(SUM(d.steps),0) FROM daily_steps d JOIN team_members m ON m.user_id = d.user_id AND m.team_id = ? WHERE d.day >= ? AND d.day < ? AND d.day >= DATE(m.joined_at)', [$tid, $wk, date('Y-m-d', strtotime($wk . ' +7 days'))]);
    $defs = [
        'steps' => ['👟', 'Step up', 'Walk ' . number_format(28000 * $n) . ' steps together (log them under Steps)', 28000 * $n, (float)$stepsTot],
        'wo'   => ['🏋️', 'Team workouts', 'Log ' . (2 + 2 * $n) . ' workouts together', 2 + 2 * $n, (float)($a['wo'] ?? 0)],
        'pts'  => ['⭐', 'Points haul', 'Earn ' . number_format(350 * $n) . ' points as a team', 350 * $n, (float)($a['pts'] ?? 0)],
        'km'   => ['🥾', 'Walk or run together', 'Cover ' . (4 + 4 * $n) . ' km combined (walks and runs count)', 4 + 4 * $n, $km],
        'days' => ['📅', 'Show up', 'Reach ' . (3 * $n) . ' member training days', 3 * $n, (float)($a['days'] ?? 0)],
        'pr'   => ['🏆', 'Break records', 'Set ' . (1 + $n) . ' personal records', 1 + $n, (float)$pr],
    ];
    $others = array_diff(array_keys($defs), ['wo']);
    usort($others, fn($x, $y) => crc32($x . $tid . $wk) <=> crc32($y . $tid . $wk));
    $pick = array_merge(['wo'], array_slice($others, 0, 2));
    $done = array_column(rows('SELECT code, done_at FROM team_quest_done WHERE team_id = ? AND week_key = ?', [$tid, $wk]), 'done_at', 'code');
    $out = [];
    foreach ($pick as $code) {
        [$ic, $title, $desc, $target, $prog] = $defs[$code];
        $out[] = ['code' => $code, 'icon' => $ic, 'title' => $title, 'desc' => $desc, 'target' => $target, 'progress' => $prog,
                  'done' => isset($done[$code]) || $prog >= $target, 'paid' => isset($done[$code])];
    }
    return $out;
}

/** Pays any team quest that just completed (once). */
function game_team_check(int $teamId): void
{
    $team = row('SELECT * FROM teams WHERE id = ?', [$teamId]);
    if (!$team) {
        return;
    }
    $wk = game_week_key();
    foreach (game_team_quests($team) as $qst) {
        if (!$qst['done'] || $qst['paid']) {
            continue;
        }
        $ins = q('INSERT IGNORE INTO team_quest_done (team_id, week_key, code, done_at) VALUES (?,?,?,?)', [$teamId, $wk, $qst['code'], now()]);
        if (!$ins->rowCount()) {
            continue;
        }
        $from = $wk . ' 00:00:00';
        $act = rows('SELECT DISTINCT m.user_id FROM workouts w JOIN team_members m ON m.user_id = w.user_id AND m.team_id = ? WHERE w.performed_at >= ? AND w.performed_at >= m.joined_at', [$teamId, $from]);
        $coins = (int)round(12 * ((float)game_cfg('coin_mult') ?: 1.0));
        foreach ($act as $a) {
            game_coins_add((int)$a['user_id'], $coins, 'team_quest', $team['name'] . ': ' . $qst['title']);
        }
        game_team_xp_add($teamId, (int)round(60 * (float)game_cfg('team_xp_mult')), 'quest');
        q('UPDATE teams SET vault = vault + 30 WHERE id = ?', [$teamId]);
        game_team_notify_all($teamId, 'team', '✅ Team quest done: ' . $qst['title'] . '. +60 team points, +30 coins in the vault, +' . $coins . ' coins for everyone who trained.');
    }
}

/** Closes teams that never got a second member, so the list stays tidy. */
/** Points per member for each team with 2+ members during one week (Monday start date), best first. */
function game_contest_scores(string $weekStart): array
{
    $from = $weekStart . ' 00:00:00';
    $to = date('Y-m-d H:i:s', strtotime($weekStart . ' +7 days'));
    $out = [];
    foreach (rows('SELECT t.id, t.name, t.emoji, (SELECT COUNT(*) FROM team_members m WHERE m.team_id = t.id) AS n FROM teams t') as $t) {
        if ((int)$t['n'] < 2) {
            continue;
        }
        $pts = (int)val('SELECT COALESCE(SUM(w.points),0) FROM workouts w JOIN team_members m ON m.user_id = w.user_id AND m.team_id = ? WHERE w.performed_at >= ? AND w.performed_at < ? AND w.performed_at >= m.joined_at', [$t['id'], $from, $to]);
        if ($pts > 0) {
            $t['pts'] = $pts;
            $t['avg'] = (int)round($pts / (int)$t['n']);
            $out[] = $t;
        }
    }
    usort($out, fn($a, $b) => $b['avg'] <=> $a['avg']);
    return $out;
}

/** Pays last week's team contest into the winners' vaults, once. Prizes: 300 / 150 / 75 coins. */
function game_contest_settle(): void
{
    $last = date('Y-m-d', strtotime(game_week_key() . ' -7 days'));
    if (setting('game_contest_done') === $last) {
        return;
    }
    set_setting('game_contest_done', $last);
    foreach (array_slice(game_contest_scores($last), 0, 3) as $i => $t) {
        $prize = [300, 150, 75][$i];
        q('UPDATE teams SET vault = vault + ? WHERE id = ?', [$prize, $t['id']]);
        game_team_notify_all((int)$t['id'], 'team', ['🥇', '🥈', '🥉'][$i] . ' ' . $t['name'] . ' placed #' . ($i + 1) . ' in last week\'s team contest: +' . $prize . ' coins in the vault!');
    }
}

function game_team_cleanup(): void
{
    game_contest_settle();
    game_season_settle();
    $days = (int)game_cfg('team_solo_days');
    if ($days <= 0) {
        return;
    }
    $old = date('Y-m-d H:i:s', time() - $days * 86400);
    foreach (rows('SELECT t.id, t.name, t.owner_id FROM teams t WHERE t.created_at < ? AND (SELECT COUNT(*) FROM team_members m WHERE m.team_id = t.id) <= 1', [$old]) as $t) {
        if ($t['owner_id']) {
            game_notify((int)$t['owner_id'], 'team', 'Your team ' . $t['name'] . ' was closed: no one else joined within ' . $days . ' days.');
        }
        q('DELETE FROM teams WHERE id = ?', [$t['id']]);
    }
}

function game_team_role_ok(?array $mine, int $teamId, bool $leaderOnly = false): bool
{
    if (!$mine || (int)$mine['id'] !== $teamId) {
        return false;
    }
    return $leaderOnly ? $mine['role'] === 'leader' : in_array($mine['role'], ['leader', 'officer'], true);
}

/* ================= Display ================= */

function game_fmt_left(string $until): string
{
    $s = strtotime($until) - time();
    if ($s <= 0) {
        return 'ending now';
    }
    $d = intdiv($s, 86400);
    $h = intdiv($s % 86400, 3600);
    $m = intdiv($s % 3600, 60);
    return $d > 0 ? "{$d}d {$h}h" : ($h > 0 ? "{$h}h {$m}m" : "{$m}m");
}

/** The animated boss card. $mode: full | compact. */
function game_boss_card(?array $b, string $mode = 'full', ?int $myDamage = null): void
{
    if (!$b) {
        $last = row("SELECT * FROM bosses WHERE team_id IS NULL AND status = 'dead' ORDER BY id DESC LIMIT 1");
        ?>
<div class="gboss gb-<?= e($mode) ?> gb-resting">
  <div class="gb-art"><span class="gb-emoji">🏆</span></div>
  <div class="gb-info"><div class="gb-name"><?= $last ? e($last['name']) . ' was defeated!' : 'A new boss is on its way' ?></div>
    <div class="gb-meta">The next one arrives within hours. Keep training.</div></div>
</div>
<?php
        return;
    }
    $r = game_roster_by_key((string)$b['boss_key']);
    $pct = (int)max(0, min(100, round((int)$b['hp'] / max(1, (int)$b['max_hp']) * 100)));
    ?>
<a class="gboss gb-<?= e($mode) ?><?= $pct <= 25 ? ' gb-low' : '' ?>" data-boss="<?= (int)$b['id'] ?>" data-state="<?= e(url('boss/state' . ($b['team_id'] ? '?team=' . (int)$b['team_id'] : ''))) ?>" href="<?= e($b['team_id'] ? url('team/' . (int)$b['team_id']) : url('boss')) ?>">
  <div class="gb-art"><span class="gb-emoji"><?= e($r[2]) ?></span><span class="gb-aura"></span></div>
  <div class="gb-info">
    <div class="gb-name"><?= e($b['name']) ?> <small>Tier <?= (int)$b['tier'] ?></small></div>
    <div class="gb-hp"><i style="width:<?= $pct ?>%"></i><b><span data-hp><?= number_format((int)$b['hp']) ?></span> / <?= number_format((int)$b['max_hp']) ?> HP</b></div>
    <div class="gb-meta">⏳ <span data-left><?= e(game_fmt_left((string)$b['ends_at'])) ?></span> left<?= $myDamage !== null ? ' · your damage <b>' . number_format($myDamage) . '</b>' : '' ?></div>
  </div>
</a>
<?php
}

/** Small card on the feed. */
function game_feed_card(array $u): void
{
    game_ensure();
    $b = game_boss_public();
    $mine = $b ? (int)val('SELECT COALESCE(damage,0) FROM boss_hits WHERE boss_id = ? AND user_id = ?', [$b['id'], $u['id']]) : null;
    $w = game_wallet((int)$u['id']);
    ?>
<section class="card gfeed">
  <?php game_boss_card($b, 'compact', $mine); ?>
  <?php game_event_banner(); ?>
  <?php game_news_card(3); ?>
  <div class="gf-links"><span>🪙 <b><?= number_format((int)$w['coins']) ?></b> coins</span> <a href="<?= e(url('shop')) ?>">Shop</a> <a href="<?= e(url('teams')) ?>">Teams</a> <a href="<?= e(url('boss')) ?>">Boss page</a></div>
</section>
<?php
}

/** Equipped gear icons for a list of users: [uid => 'icons'] */
function game_icons_for(array $uids): array
{
    if (!$uids) {
        return [];
    }
    $cat = game_items();
    $out = [];
    foreach (rows('SELECT user_id, item FROM game_items WHERE equipped = 1 AND user_id IN (' . in_clause($uids) . ')', array_values($uids)) as $r) {
        $out[(int)$r['user_id']] = ($out[(int)$r['user_id']] ?? '') . ($cat[$r['item']][1] ?? '');
    }
    return $out;
}

/* ================= Admin ================= */

function game_admin_post(string $do): bool
{
    if ($do === 'game_save') {
        foreach (array_keys(game_defaults()) as $k) {
            if (isset($_POST[$k])) {
                $v = trim((string)$_POST[$k]);
                if ($k === 'team_slots') {
                    $v = implode(',', array_filter(array_map('intval', explode(',', $v)), fn($n) => $n > 0));
                }
                set_setting('game_' . $k, mb_substr($v, 0, 80));
            }
        }
        flash('Game settings saved.');
        return true;
    }
    if ($do === 'game_spawn') {
        game_ensure();
        $cur = row("SELECT * FROM bosses WHERE team_id IS NULL AND status = 'active' ORDER BY id DESC LIMIT 1");
        if ($cur) {
            q("UPDATE bosses SET status = 'fled', ended_at = ? WHERE id = ?", [now(), $cur['id']]);
        }
        game_boss_spawn(null);
        flash('A new boss has spawned.');
        return true;
    }
    return false;
}

function game_admin_view(): void
{
    game_ensure();
    $b = game_boss_public();
    $slots = game_cfg('team_slots');
    ?>
<div class="card">
  <h3>Game: boss, coins and teams</h3>
  <?php game_boss_card($b, 'full'); ?>
  <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="do" value="game_spawn">
    <button class="btn ghost small" type="submit" onclick="return confirm('Retire the current boss and spawn a new one?')">Spawn a fresh boss now</button></form>
</div>
<div class="card">
  <h4>Rules you can change</h4>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="game_save">
    <label>Max team level <input name="max_team_level" type="number" min="2" max="30" value="<?= e(game_cfg('max_team_level')) ?>"></label>
    <label>Team slots per level (comma list, level 1 first) <input name="team_slots" value="<?= e($slots) ?>"></label>
    <label>Boss health multiplier (1 = normal, 2 = twice as tough) <input name="boss_hp_mult" value="<?= e(game_cfg('boss_hp_mult')) ?>"></label>
    <label>Coin multiplier <input name="coin_mult" value="<?= e(game_cfg('coin_mult')) ?>"></label>
    <label>Team points multiplier (higher = teams level faster) <input name="team_xp_mult" value="<?= e(game_cfg('team_xp_mult')) ?>"></label>
    <label>Coins to start a team (0 = free) <input name="team_create_cost" type="number" min="0" max="5000" value="<?= e(game_cfg('team_create_cost')) ?>"></label>
    <label>Steps needed for 1 point <input name="steps_per_point" type="number" min="50" max="5000" value="<?= e(game_cfg('steps_per_point')) ?>"></label>
    <label>Max steps counted per day for points (0 = steps give no points) <input name="steps_cap_day" type="number" min="0" max="100000" value="<?= e(game_cfg('steps_cap_day')) ?>"></label>
    <label>Close a team with only its leader after this many days (0 = never) <input name="team_solo_days" type="number" min="0" max="365" value="<?= e(game_cfg('team_solo_days')) ?>"></label>
    <label>Hours before the next boss after a kill <input name="respawn_hours" type="number" min="0" max="72" value="<?= e(game_cfg('respawn_hours')) ?>"></label>
    <button class="btn" type="submit">Save</button>
  </form>
  <p class="muted small">Team level needs 200 x (level-1)^2 team points in total: level 5 is 3,200 and level 10 is 16,200. A team that clears its three weekly quests and its raid earns roughly 300 to 400 XP a week.</p>
</div>
<?php
}
