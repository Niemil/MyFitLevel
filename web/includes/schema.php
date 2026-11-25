<?php
declare(strict_types=1);

/*
 * Database schema + migrations.
 * To change the database in a later update: add a new entry to migrations() with the next
 * version number and bump SCHEMA_VERSION in bootstrap.php. It is applied automatically on the
 * first page load after the update is uploaded.
 */

function migrations(): array
{
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return [
        1 => [
            "CREATE TABLE IF NOT EXISTS settings (
                k VARCHAR(64) NOT NULL PRIMARY KEY,
                v TEXT NULL
            ) $t",

            "CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(30) NOT NULL,
                email VARCHAR(190) NULL,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(60) NULL,
                bio VARCHAR(300) NULL,
                unit ENUM('kg','lb') NOT NULL DEFAULT 'kg',
                bodyweight_kg DECIMAL(5,1) NULL,
                share_public TINYINT(1) NOT NULL DEFAULT 1,
                is_admin TINYINT(1) NOT NULL DEFAULT 0,
                is_banned TINYINT(1) NOT NULL DEFAULT 0,
                xp INT UNSIGNED NOT NULL DEFAULT 0,
                level INT UNSIGNED NOT NULL DEFAULT 1,
                api_token_hash CHAR(64) NULL,
                created_at DATETIME NOT NULL,
                last_seen DATETIME NULL,
                UNIQUE KEY uq_username (username),
                UNIQUE KEY uq_email (email),
                KEY idx_xp (xp),
                KEY idx_token (api_token_hash)
            ) $t",

            "CREATE TABLE IF NOT EXISTS exercises (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(80) NOT NULL,
                name VARCHAR(100) NOT NULL,
                muscle VARCHAR(20) NOT NULL,
                equipment VARCHAR(30) NOT NULL DEFAULT '',
                kind ENUM('weight','bodyweight','cardio','duration') NOT NULL DEFAULT 'weight',
                coef DECIMAL(6,3) NOT NULL DEFAULT 1.000,
                bw_pct DECIMAL(3,2) NOT NULL DEFAULT 0.00,
                dist_coef DECIMAL(6,2) NOT NULL DEFAULT 0.00,
                active TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE KEY uq_slug (slug),
                KEY idx_muscle (muscle)
            ) $t",

            "CREATE TABLE IF NOT EXISTS workouts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                performed_at DATETIME NOT NULL,
                title VARCHAR(80) NULL,
                notes VARCHAR(1000) NULL,
                points INT UNSIGNED NOT NULL DEFAULT 0,
                volume_kg DECIMAL(10,1) NOT NULL DEFAULT 0,
                set_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_user_time (user_id, performed_at),
                KEY idx_time (performed_at),
                CONSTRAINT fk_w_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS workout_sets (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                workout_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                exercise_id INT UNSIGNED NOT NULL,
                weight_kg DECIMAL(7,2) NULL,
                reps SMALLINT UNSIGNED NULL,
                duration_sec INT UNSIGNED NULL,
                distance_m INT UNSIGNED NULL,
                score DECIMAL(9,2) NOT NULL DEFAULT 0,
                points INT UNSIGNED NOT NULL DEFAULT 0,
                is_pr TINYINT(1) NOT NULL DEFAULT 0,
                KEY idx_workout (workout_id),
                KEY idx_user_ex (user_id, exercise_id),
                CONSTRAINT fk_s_workout FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE CASCADE,
                CONSTRAINT fk_s_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_s_ex FOREIGN KEY (exercise_id) REFERENCES exercises(id)
            ) $t",

            "CREATE TABLE IF NOT EXISTS props (
                workout_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (workout_id, user_id),
                CONSTRAINT fk_p_w FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE CASCADE,
                CONSTRAINT fk_p_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS comments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                workout_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                body VARCHAR(500) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_w (workout_id),
                CONSTRAINT fk_c_w FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE CASCADE,
                CONSTRAINT fk_c_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS follows (
                follower_id INT UNSIGNED NOT NULL,
                followee_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (follower_id, followee_id),
                KEY idx_followee (followee_id),
                CONSTRAINT fk_f_a FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_f_b FOREIGN KEY (followee_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS notifications (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                actor_id INT UNSIGNED NULL,
                type VARCHAR(20) NOT NULL,
                workout_id INT UNSIGNED NULL,
                text VARCHAR(200) NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_user (user_id, is_read, id),
                CONSTRAINT fk_n_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS user_badges (
                user_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                earned_at DATETIME NOT NULL,
                PRIMARY KEY (user_id, code),
                CONSTRAINT fk_b_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS user_quests (
                user_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                reward INT UNSIGNED NOT NULL DEFAULT 0,
                completed_at DATETIME NOT NULL,
                PRIMARY KEY (user_id, code),
                CONSTRAINT fk_q_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS groups_ (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(60) NOT NULL,
                name VARCHAR(60) NOT NULL,
                description VARCHAR(400) NULL,
                owner_id INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_gslug (slug),
                CONSTRAINT fk_g_o FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
            ) $t",

            "CREATE TABLE IF NOT EXISTS group_members (
                group_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                joined_at DATETIME NOT NULL,
                PRIMARY KEY (group_id, user_id),
                KEY idx_gm_user (user_id),
                CONSTRAINT fk_gm_g FOREIGN KEY (group_id) REFERENCES groups_(id) ON DELETE CASCADE,
                CONSTRAINT fk_gm_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS group_posts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                group_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                body VARCHAR(1000) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_gp (group_id, id),
                CONSTRAINT fk_gp_g FOREIGN KEY (group_id) REFERENCES groups_(id) ON DELETE CASCADE,
                CONSTRAINT fk_gp_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",

            "CREATE TABLE IF NOT EXISTS rate_limits (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                k VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_k (k, created_at)
            ) $t",
        ],

        // v1.1: autosaved workout drafts + warm-up sets
        2 => [
            "CREATE TABLE IF NOT EXISTS workout_drafts (
                user_id INT UNSIGNED NOT NULL PRIMARY KEY,
                payload MEDIUMTEXT NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_d_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",
            "ALTER TABLE workout_sets ADD COLUMN is_warmup TINYINT(1) NOT NULL DEFAULT 0",
        ],

        // v1.2: finer muscle regions (body map / heat map) + per-hand / per-side weights
        3 => [
            "ALTER TABLE exercises ADD COLUMN subs VARCHAR(60) NOT NULL DEFAULT ''",
            "ALTER TABLE exercises ADD COLUMN per_side TINYINT(1) NOT NULL DEFAULT 0",
        ],

        // v1.2: favourite exercises
        4 => [
            "CREATE TABLE IF NOT EXISTS favorite_exercises (
                user_id INT UNSIGNED NOT NULL,
                exercise_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (user_id, exercise_id),
                CONSTRAINT fk_fav_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_fav_e FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
            ) $t",
        ],

        // v1.2: remember the unit a workout was logged in + per-exercise weight step (machine stacks)
        5 => [
            "ALTER TABLE workouts ADD COLUMN unit ENUM('kg','lb') NOT NULL DEFAULT 'kg'",
            "CREATE TABLE IF NOT EXISTS user_exercise_prefs (
                user_id INT UNSIGNED NOT NULL,
                exercise_id INT UNSIGNED NOT NULL,
                step DECIMAL(6,2) NOT NULL,
                unit ENUM('kg','lb') NOT NULL DEFAULT 'kg',
                PRIMARY KEY (user_id, exercise_id),
                CONSTRAINT fk_uep_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_uep_e FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
            ) $t",
        ],

        // v1.3: admin-edited exercises are "locked" so built-in updates don't overwrite them
        6 => [
            "ALTER TABLE exercises ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0",
        ],

        // v1.4: posts, who-can-see-what, and how new exercises are pre-filled
        7 => [
            "ALTER TABLE users ADD COLUMN visibility ENUM('public','members','followers') NOT NULL DEFAULT 'public'",
            "UPDATE users SET visibility = IF(share_public = 1, 'public', 'followers')",
            "ALTER TABLE users ADD COLUMN prefill ENUM('first','all','none') NOT NULL DEFAULT 'first'",
            "CREATE TABLE IF NOT EXISTS posts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                body VARCHAR(1000) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_user (user_id, created_at),
                KEY idx_time (created_at),
                CONSTRAINT fk_post_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",
            "CREATE TABLE IF NOT EXISTS post_props (
                post_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (post_id, user_id),
                CONSTRAINT fk_pp_p FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                CONSTRAINT fk_pp_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",
            "CREATE TABLE IF NOT EXISTS post_comments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                post_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                body VARCHAR(500) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_p (post_id),
                CONSTRAINT fk_pc_p FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                CONSTRAINT fk_pc_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",
        ],

        // v1.5: saved routines (reusable workouts)
        8 => [
            "CREATE TABLE IF NOT EXISTS routines (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                name VARCHAR(60) NOT NULL,
                payload TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_user (user_id),
                CONSTRAINT fk_rt_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",
        ],

        // v1.6: gyms (shared + home gyms), equipment, saved gyms, gym on workouts
        9 => [
            "CREATE TABLE IF NOT EXISTS gyms (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                owner_id INT UNSIGNED NOT NULL,
                name VARCHAR(80) NOT NULL,
                kind ENUM('gym','home') NOT NULL DEFAULT 'gym',
                city VARCHAR(80) NOT NULL DEFAULT '',
                address VARCHAR(160) NOT NULL DEFAULT '',
                info TEXT NULL,
                website VARCHAR(200) NOT NULL DEFAULT '',
                lat DECIMAL(9,6) NULL,
                lng DECIMAL(9,6) NULL,
                is_public TINYINT(1) NOT NULL DEFAULT 0,
                advanced TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_owner (owner_id),
                KEY idx_public (is_public, name),
                CONSTRAINT fk_gym_o FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",
            "CREATE TABLE IF NOT EXISTS gym_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                gym_id INT UNSIGNED NOT NULL,
                cat VARCHAR(20) NOT NULL DEFAULT 'other',
                name VARCHAR(80) NOT NULL,
                weights TEXT NULL,
                note VARCHAR(160) NOT NULL DEFAULT '',
                KEY idx_gym (gym_id),
                CONSTRAINT fk_gi_g FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE
            ) $t",
            "CREATE TABLE IF NOT EXISTS gym_saved (
                user_id INT UNSIGNED NOT NULL,
                gym_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (user_id, gym_id),
                CONSTRAINT fk_gs_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_gs_g FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE
            ) $t",
            "ALTER TABLE workouts ADD COLUMN gym_id INT UNSIGNED NULL, ADD KEY idx_gym (gym_id)",
            "ALTER TABLE users ADD COLUMN default_gym_id INT UNSIGNED NULL",
        ],

        // v1.7: photos (post images, profile photo, gallery)
        10 => [
            "CREATE TABLE IF NOT EXISTS images (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token CHAR(24) NOT NULL,
                kind ENUM('post','avatar','gallery') NOT NULL DEFAULT 'post',
                post_id INT UNSIGNED NULL,
                bytes INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_token (token),
                KEY idx_user (user_id, kind),
                KEY idx_post (post_id),
                CONSTRAINT fk_img_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",
            "ALTER TABLE users ADD COLUMN avatar_token CHAR(24) NULL",
        ],

        // v1.8: moderators, temporary suspensions, reports, moderation log
        11 => [
            "ALTER TABLE users ADD COLUMN is_mod TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN banned_until DATETIME NULL, ADD COLUMN ban_reason VARCHAR(200) NULL",
            "CREATE TABLE IF NOT EXISTS reports (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                reporter_id INT UNSIGNED NOT NULL,
                kind ENUM('post','workout','comment','pcomment','user') NOT NULL,
                target_id INT UNSIGNED NOT NULL,
                reason VARCHAR(20) NOT NULL DEFAULT 'other',
                status ENUM('open','done','dismissed') NOT NULL DEFAULT 'open',
                handled_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_status (status, id),
                UNIQUE KEY uq_once (reporter_id, kind, target_id),
                CONSTRAINT fk_rp_u FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
            ) $t",
            "CREATE TABLE IF NOT EXISTS mod_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                actor_id INT UNSIGNED NOT NULL,
                action VARCHAR(40) NOT NULL,
                target_user_id INT UNSIGNED NULL,
                detail VARCHAR(300) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL,
                KEY idx_time (id)
            ) $t",
        ],

        // v1.9: email verification, password reset, onboarding, referrals, reminder mails
        12 => [
            "ALTER TABLE users
                ADD COLUMN email_verified_at DATETIME NULL,
                ADD COLUMN email_token CHAR(32) NULL,
                ADD COLUMN pw_reset_token CHAR(64) NULL,
                ADD COLUMN pw_reset_expires DATETIME NULL,
                ADD COLUMN gender VARCHAR(10) NULL,
                ADD COLUMN goal VARCHAR(20) NULL,
                ADD COLUMN referred_by INT UNSIGNED NULL,
                ADD COLUMN credits INT NOT NULL DEFAULT 0,
                ADD COLUMN email_optout TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN miss_mailed_at DATETIME NULL,
                ADD COLUMN onboard_hidden TINYINT(1) NOT NULL DEFAULT 0",
        ],

        // v2.0: time zone, workout length, per-exercise notes
        13 => [
            "ALTER TABLE users ADD COLUMN timezone VARCHAR(50) NULL",
            "ALTER TABLE workouts ADD COLUMN duration_sec INT UNSIGNED NULL",
            "CREATE TABLE IF NOT EXISTS workout_ex_notes (
                workout_id INT UNSIGNED NOT NULL,
                exercise_id INT UNSIGNED NOT NULL,
                note VARCHAR(500) NOT NULL,
                PRIMARY KEY (workout_id, exercise_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ],

        // v3.4: weekly schedule of routines
        14 => [
            "CREATE TABLE IF NOT EXISTS routine_schedule (
                user_id INT UNSIGNED NOT NULL,
                dow TINYINT UNSIGNED NOT NULL,
                routine_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (user_id, dow),
                CONSTRAINT fk_rs_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_rs_r FOREIGN KEY (routine_id) REFERENCES routines(id) ON DELETE CASCADE
            ) $t",
        ],

        // v3.5: colour skins
        15 => [
            "ALTER TABLE users ADD COLUMN skin VARCHAR(20) NOT NULL DEFAULT 'auto'",
        ],
        // v3.6: quest lines and challenges (also created on demand by ql_ensure_tables)
        16 => [
            "CREATE TABLE IF NOT EXISTS quest_runs (
                user_id INT UNSIGNED NOT NULL, quest VARCHAR(30) NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME NULL,
                PRIMARY KEY (user_id, quest),
                CONSTRAINT fk_qr_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t",
            "CREATE TABLE IF NOT EXISTS quest_steps (
                user_id INT UNSIGNED NOT NULL, quest VARCHAR(30) NOT NULL, step INT UNSIGNED NOT NULL, reward INT UNSIGNED NOT NULL DEFAULT 0, done_at DATETIME NOT NULL,
                PRIMARY KEY (user_id, quest, step),
                CONSTRAINT fk_qs_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t",
            "CREATE TABLE IF NOT EXISTS challenges (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                challenger_id INT UNSIGNED NOT NULL, opponent_id INT UNSIGNED NOT NULL, days TINYINT UNSIGNED NOT NULL DEFAULT 7,
                status VARCHAR(10) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL, starts_at DATETIME NULL, ends_at DATETIME NULL,
                winner_id INT UNSIGNED NULL, KEY idx_c (challenger_id), KEY idx_o (opponent_id), KEY idx_s (status, ends_at),
                CONSTRAINT fk_ch_a FOREIGN KEY (challenger_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_ch_b FOREIGN KEY (opponent_id) REFERENCES users(id) ON DELETE CASCADE) $t",
        ],
    ];
}

function run_migrations(PDO $pdo, int $from): int
{
    $all = migrations();
    ksort($all);
    $ver = $from;
    foreach ($all as $v => $stmts) {
        if ($v <= $from) {
            continue;
        }
        foreach ($stmts as $sql) {
            $pdo->exec($sql);
        }
        $ver = $v;
    }
    $st = $pdo->prepare('REPLACE INTO settings (k, v) VALUES (?, ?)');
    $st->execute(['schema_version', (string)$ver]);
    return $ver;
}
