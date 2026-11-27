<?php
declare(strict_types=1);

define('APP_VERSION', '3.61.0');
const SCHEMA_VERSION = 16;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require __DIR__ . '/core.php';

$cfgFile = APP_ROOT . '/config/config.php';
if (!is_file($cfgFile) || !is_file(APP_ROOT . '/config/installed.lock')) {
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    header('Location: ' . $dir . '/install/');
    exit('Not installed yet. Open /install/');
}
$GLOBALS['CFG'] = require $cfgFile;
date_default_timezone_set((string)cfg('timezone', 'Europe/Stockholm'));

if (cfg('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

set_exception_handler(function (Throwable $e) {
    $msg = '[fitness] ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . ' (' . ($_SERVER['REQUEST_URI'] ?? '') . ')';
    error_log($msg);
    // keep a small private log so the admin can read it (Admin > Site)
    $lf = APP_ROOT . '/config/errors.log';
    if (is_dir(dirname($lf)) && (!is_file($lf) || filesize($lf) < 200000)) {
        @file_put_contents($lf, date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (cfg('debug')) {
        echo '<pre>' . htmlspecialchars((string)$e) . '</pre>';
    } else {
        echo 'Something went wrong on our side. Please try again in a moment.';
    }
    exit;
});

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; manifest-src 'self'; worker-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

require __DIR__ . '/db.php';
require __DIR__ . '/applog.php';
AppLog::init();   // error handlers + logs/ outside public/
require __DIR__ . '/schema.php';
require __DIR__ . '/muscles.php';
require __DIR__ . '/seed.php';
require __DIR__ . '/xp.php';
require __DIR__ . '/recalc.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/views/partials.php';
require __DIR__ . '/social.php';
require __DIR__ . '/gyms.php';
require __DIR__ . '/images.php';
require __DIR__ . '/moderation.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/onboarding.php';
require __DIR__ . '/charts.php';
require __DIR__ . '/routines.php';
require __DIR__ . '/skins.php';
require __DIR__ . '/questlines.php';
require __DIR__ . '/strength.php';
require __DIR__ . '/blog.php';
require __DIR__ . '/challenges.php';
require __DIR__ . '/tiers.php';
require __DIR__ . '/webhook.php';
require __DIR__ . '/bonuses.php';
require __DIR__ . '/programs.php';
require __DIR__ . '/stats.php';
require __DIR__ . '/fito.php';
require __DIR__ . '/fito_coach.php';
require __DIR__ . '/staff.php';
require __DIR__ . '/fito_art.php';
require __DIR__ . '/fito_gear.php';
require __DIR__ . '/fito_reference.php';
require __DIR__ . '/game.php';

// Apply pending database migrations (so updates are "just upload the files").
$have = (int)setting('schema_version', 0);
if ($have < SCHEMA_VERSION) {
    run_migrations(db(), $have);
    seed_exercises(db());
}
ql_ensure_tables();
events_ensure();
// Self-heal: make sure the skin column exists even if an older schema.php was uploaded.
if (setting('skin_col_ok') !== '1') {
    try {
        $has = (int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'skin'");
        if (!$has) {
            db()->exec("ALTER TABLE users ADD COLUMN skin VARCHAR(20) NOT NULL DEFAULT 'auto'");
        }
        set_setting('skin_col_ok', '1');
    } catch (Throwable $e) {
        error_log('skin column: ' . $e->getMessage());
    }
}
// New built-in exercises shipped in an update (checked when the app version changes).
if (setting('seeded_version') !== APP_VERSION) {
    seed_exercises(db());
    set_setting('seeded_version', APP_VERSION);
    rules_mark();
}
rules_boot();   // finishes any pending recalculation in small slices

start_session();
