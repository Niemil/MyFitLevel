<?php
declare(strict_types=1);

/*
 * Web installer. Open  https://your-site/<folder>/install/  once after uploading.
 * It creates the database tables, your admin account and the config file (outside public/),
 * then locks itself. After that you can delete this install folder (there is a button).
 */

$root = is_file(__DIR__ . '/../app_root.php') ? require __DIR__ . '/../app_root.php' : dirname(__DIR__, 2);
define('APP_ROOT', $root);
const APP_VERSION = '1.0.0';

require APP_ROOT . '/includes/core.php';
require APP_ROOT . '/includes/schema.php';
require APP_ROOT . '/includes/muscles.php';
require APP_ROOT . '/includes/seed.php';

header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
start_session();

$lockFile = APP_ROOT . '/config/installed.lock';
$cfgFile = APP_ROOT . '/config/config.php';
$installed = is_file($lockFile);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$detectedBase = rtrim((string)preg_replace('#/install(/index\.php)?$#', '', rtrim($path, '/')), '/');
$detectedBase = rtrim((string)preg_replace('#/public$#', '', $detectedBase), '/');   // reached via the folder's root .htaccess

$errors = [];
$done = false;
$manualConfig = null;
$f = [
    'db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'site_name' => APP_NAME_DEFAULT, 'timezone' => 'Europe/Stockholm', 'base_path' => $detectedBase,
    'admin_user' => '', 'admin_email' => '', 'unit' => 'kg',
];

$checks = [
    ['PHP 8.0 or newer (you have ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.0.0', '>=')],
    ['PDO MySQL extension', extension_loaded('pdo_mysql')],
    ['mbstring extension', extension_loaded('mbstring')],
    ['config/ folder is writable', is_dir(APP_ROOT . '/config') && is_writable(APP_ROOT . '/config')],
];
$checksOk = !in_array(false, array_column($checks, 1), true);

if (is_post()) {
    csrf_check();
    $do = (string)($_POST['do'] ?? 'install');

    if ($do === 'remove' && $installed) {
        @unlink(__FILE__);
        @rmdir(__DIR__);
        header('Location: ' . ($detectedBase === '' ? '/' : $detectedBase . '/'));
        exit;
    }

    if (!$installed) {
        foreach (array_keys($f) as $k) {
            $f[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $f['unit'] = $f['unit'] === 'lb' ? 'lb' : 'kg';
        $adminPass = (string)($_POST['admin_pass'] ?? '');
        $f['base_path'] = $f['base_path'] === '' || $f['base_path'] === '/' ? '' : '/' . trim($f['base_path'], '/');

        if (!$checksOk) $errors[] = 'Fix the red checks above first.';
        if ($f['db_name'] === '' || $f['db_user'] === '') $errors[] = 'Database name and user are required.';
        if (!preg_match('#^(/[A-Za-z0-9._~-]+)*$#', $f['base_path'])) $errors[] = 'Base path may only contain letters, numbers and . _ ~ -';
        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $f['admin_user'])) $errors[] = 'Admin username: 3–20 letters, numbers or underscore.';
        if ($f['admin_email'] !== '' && !filter_var($f['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Admin email does not look right.';
        if (strlen($adminPass) < 10) $errors[] = 'Admin password must be at least 10 characters.';
        if (!in_array($f['timezone'], timezone_identifiers_list(), true)) $errors[] = 'Unknown timezone (example: Europe/Stockholm).';
        if ($f['site_name'] === '') $f['site_name'] = APP_NAME_DEFAULT;

        if (!$errors) {
            try {
                $pdo = new PDO(
                    'mysql:host=' . $f['db_host'] . ';dbname=' . $f['db_name'] . ';charset=utf8mb4',
                    $f['db_user'], $f['db_pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
            } catch (Throwable $e) {
                $errors[] = 'Could not connect to the database: ' . $e->getMessage();
            }
        }

        if (!$errors) {
            try {
                run_migrations($pdo, 0);
                seed_exercises($pdo);

                $exists = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $exists->execute([$f['admin_user']]);
                if ($exists->fetch()) {
                    throw new RuntimeException('That admin username already exists in this database.');
                }
                $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, unit, is_admin, created_at) VALUES (?,?,?,?,1,?)');
                $ins->execute([$f['admin_user'], $f['admin_email'] ?: null, password_hash($adminPass, PASSWORD_DEFAULT), $f['unit'], date('Y-m-d H:i:s')]);

                $set = $pdo->prepare('REPLACE INTO settings (k, v) VALUES (?, ?)');
                $set->execute(['site_name', $f['site_name']]);
                $set->execute(['registration', '1']);
                $set->execute(['seeded_version', APP_VERSION]);

                $config = [
                    'db' => ['host' => $f['db_host'], 'name' => $f['db_name'], 'user' => $f['db_user'], 'pass' => $f['db_pass']],
                    'base_path' => $f['base_path'],
                    'timezone' => $f['timezone'],
                    'secret' => bin2hex(random_bytes(32)),
                    'debug' => false,
                ];
                $php = "<?php\n// Generated by the installer. Keep this file outside the public web folder.\nreturn " . var_export($config, true) . ";\n";
                if (@file_put_contents($cfgFile, $php, LOCK_EX) === false || @file_put_contents($lockFile, date('c')) === false) {
                    $manualConfig = $php;
                } else {
                    @chmod($cfgFile, 0640);
                    $done = true;
                    $installed = true;
                }
            } catch (Throwable $e) {
                $errors[] = 'Install failed: ' . $e->getMessage();
            }
        }
    }
}

$siteUrlBase = $f['base_path'] === '' ? '/' : $f['base_path'] . '/';
?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install</title>
<link rel="stylesheet" href="../assets/app.css">
<meta name="robots" content="noindex">
</head><body class="out">
<main class="wrap" style="max-width:640px">
<h1 class="pagetitle">⚡ MyFitLevel installer</h1>

<?php if ($manualConfig !== null): ?>
  <div class="card">
    <div class="flash err">The database is ready, but the installer could not write into the config/ folder.</div>
    <p>Create the file <code>config/config.php</code> with exactly this content, then create an empty file <code>config/installed.lock</code>:</p>
    <textarea rows="16" readonly onclick="this.select()"><?= e($manualConfig) ?></textarea>
  </div>

<?php elseif ($done): ?>
  <div class="card">
    <div class="flash win">✅ Installed! Your database and admin account are ready.</div>
    <p><a class="btn" href="<?= e($siteUrlBase) ?>login">Open the site and log in</a></p>
    <hr>
    <p class="muted">For safety, remove the installer now. (It is already locked and cannot be run again.)</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="remove"><button class="btn ghost danger" type="submit">Delete install folder</button></form>
  </div>

<?php elseif ($installed): ?>
  <div class="card">
    <div class="flash ok">This site is already installed and the installer is locked.</div>
    <p><a class="btn" href="<?= e(($detectedBase === '' ? '' : $detectedBase) . '/') ?>">Go to the site</a></p>
    <hr>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="remove"><button class="btn ghost danger" type="submit">Delete install folder</button></form>
  </div>

<?php else: ?>
  <div class="card">
    <h3>1. Server check</h3>
    <ul class="checks">
      <?php foreach ($checks as [$label, $ok]): ?><li class="<?= $ok ? 'good' : 'badc' ?>"><?= e($label) ?></li><?php endforeach; ?>
    </ul>
    <p class="muted small">Create an empty MySQL database and user first (in cPanel: MySQL Databases), then fill in the details below.</p>
  </div>

  <?php foreach ($errors as $err): ?><div class="flash err"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="do" value="install">
    <div class="card">
      <h3>2. Database</h3>
      <div class="row2">
        <label>Host <input name="db_host" value="<?= e($f['db_host']) ?>" required></label>
        <label>Database name <input name="db_name" value="<?= e($f['db_name']) ?>" required></label>
        <label>Database user <input name="db_user" value="<?= e($f['db_user']) ?>" required></label>
        <label>Database password <input type="password" name="db_pass" value="" autocomplete="new-password"></label>
      </div>
    </div>
    <div class="card">
      <h3>3. Site</h3>
      <div class="row2">
        <label>Site name <input name="site_name" value="<?= e($f['site_name']) ?>" maxlength="40"></label>
        <label>Timezone <input name="timezone" value="<?= e($f['timezone']) ?>"></label>
      </div>
      <label>URL path of the site <small class="muted">(detected: leave as is. Empty = domain root, e.g. /fitness if it lives in a subfolder)</small>
        <input name="base_path" value="<?= e($f['base_path']) ?>" placeholder="/fitness"></label>
    </div>
    <div class="card">
      <h3>4. Your admin account</h3>
      <div class="row2">
        <label>Username <input name="admin_user" value="<?= e($f['admin_user']) ?>" maxlength="20" required></label>
        <label>Email <small class="muted">(optional)</small> <input type="email" name="admin_email" value="<?= e($f['admin_email']) ?>"></label>
      </div>
      <label>Your weight unit <small class="muted">(each member picks their own later)</small>
        <select name="unit"><option value="kg" <?= $f['unit'] === 'kg' ? 'selected' : '' ?>>Kilograms (kg)</option><option value="lb" <?= $f['unit'] === 'lb' ? 'selected' : '' ?>>Pounds (lb)</option></select></label>
      <label>Password <small class="muted">(10+ characters)</small> <input type="password" name="admin_pass" minlength="10" autocomplete="new-password" required></label>
    </div>
    <button class="btn big wide" type="submit">Install</button>
  </form>
<?php endif; ?>
</main>
</body></html>
