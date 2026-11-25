<?php
declare(strict_types=1);

function me(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    ban_sweep();
    $id = (int)($_SESSION['uid'] ?? 0);
    if (!$id && !empty($_COOKIE['fitrem']) && is_string($_COOKIE['fitrem'])) {
        $rid = remember_login();
        if ($rid) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $rid;
            csrf_token();
            $id = $rid;
        }
    }
    if ($id) {
        $u = row('SELECT * FROM users WHERE id = ?', [$id]);
        if ($u && !$u['is_banned']) {
            $user = $u;
            if (!$u['last_seen'] || strtotime($u['last_seen']) < time() - 300) {
                q('UPDATE users SET last_seen = ? WHERE id = ?', [now(), $id]);
            }
        } else {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

function require_login(): array
{
    $u = me();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if (!$u['is_admin']) {
        http_response_code(403);
        exit('Forbidden');
    }
    return $u;
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    csrf_token();
    remember_issue($id);
}

/* ---------- stay signed in (90 days, one token per device, rotated on every use) ---------- */

const REMEMBER_DAYS = 90;

function remember_cookie_opts(int $expires): array
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ['expires' => $expires, 'path' => base() === '' ? '/' : base() . '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax'];
}

function remember_ensure(): void
{
    if (setting('remember_ok') === '1') {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS auth_tokens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, selector CHAR(16) NOT NULL, token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, UNIQUE KEY uq_sel (selector), KEY idx_u (user_id),
        CONSTRAINT fk_at_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    set_setting('remember_ok', '1');
}

function remember_issue(int $uid): void
{
    try {
        remember_ensure();
        $sel = bin2hex(random_bytes(8));
        $tok = bin2hex(random_bytes(24));
        $exp = time() + REMEMBER_DAYS * 86400;
        q('DELETE FROM auth_tokens WHERE expires_at < ?', [date('Y-m-d H:i:s')]);
        q('INSERT INTO auth_tokens (user_id, selector, token_hash, expires_at, created_at) VALUES (?,?,?,?,?)',
          [$uid, $sel, hash('sha256', $tok), date('Y-m-d H:i:s', $exp), date('Y-m-d H:i:s')]);
        if (!headers_sent()) {
            setcookie('fitrem', $sel . ':' . $tok, remember_cookie_opts($exp));
        }
    } catch (Throwable $e) {
        error_log('remember_issue: ' . $e->getMessage());
    }
}

function remember_login(): int
{
    try {
        remember_ensure();
        $parts = explode(':', (string)$_COOKIE['fitrem'], 2);
        if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{16}$/', $parts[0])) {
            return 0;
        }
        $r = row('SELECT * FROM auth_tokens WHERE selector = ?', [$parts[0]]);
        if (!$r || strtotime($r['expires_at']) < time() || !hash_equals($r['token_hash'], hash('sha256', $parts[1]))) {
            if ($r) {
                q('DELETE FROM auth_tokens WHERE id = ?', [$r['id']]);
            }
            if (!headers_sent()) {
                setcookie('fitrem', '', remember_cookie_opts(time() - 3600));
            }
            return 0;
        }
        q('DELETE FROM auth_tokens WHERE id = ?', [$r['id']]);
        $uid = (int)$r['user_id'];
        remember_issue($uid);
        return $uid;
    } catch (Throwable $e) {
        error_log('remember_login: ' . $e->getMessage());
        return 0;
    }
}

function remember_forget(bool $all = false, int $uid = 0): void
{
    try {
        if (!empty($_COOKIE['fitrem']) && is_string($_COOKIE['fitrem'])) {
            $sel = explode(':', $_COOKIE['fitrem'], 2)[0];
            q('DELETE FROM auth_tokens WHERE selector = ?', [$sel]);
        }
        if ($all && $uid) {
            q('DELETE FROM auth_tokens WHERE user_id = ?', [$uid]);
        }
        if (!headers_sent()) {
            setcookie('fitrem', '', remember_cookie_opts(time() - 3600));
        }
    } catch (Throwable $e) {
    }
}

function logout_user(): void
{
    remember_forget();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], true);
    }
    session_destroy();
}

function display_name(array $u): string
{
    return ($u['display_name'] ?? '') !== '' && $u['display_name'] !== null ? $u['display_name'] : $u['username'];
}

function valid_username(string $n): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_]{3,20}$/', $n);
}

function unread_count(int $uid): int
{
    return (int)val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid]);
}

/** API key auth: Authorization: Bearer <token> (or X-Api-Key). */
function api_keys_ensure(): void
{
    if (setting('api_keys_ok2') === '1') {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, name VARCHAR(40) NOT NULL,
        token_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL, last_used DATETIME NULL, UNIQUE KEY uq_ak (token_hash), KEY idx_ak (user_id),
        CONSTRAINT fk_ak_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_keys' AND COLUMN_NAME = 'token_enc'")) {
        db()->exec('ALTER TABLE api_keys ADD COLUMN token_enc VARCHAR(255) NULL');
    }
    set_setting('api_keys_ok1', '1');
    set_setting('api_keys_ok2', '1');
}

/** Keys are kept encrypted (with this site's secret) so the owner can look at and copy them again. */
function api_key_seal(string $tok): string
{
    $iv = random_bytes(16);
    return base64_encode($iv . openssl_encrypt($tok, 'aes-256-cbc', hash('sha256', site_secret(), true), OPENSSL_RAW_DATA, $iv));
}

function api_key_reveal(array $k): string
{
    $raw = base64_decode((string)($k['token_enc'] ?? ''), true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }
    $t = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', hash('sha256', site_secret(), true), OPENSSL_RAW_DATA, substr($raw, 0, 16));
    return $t === false ? '' : $t;
}

/** Creates a named key for a member and returns the plain token (only ever shown once). */
function api_key_create(int $uid, string $name): string
{
    api_keys_ensure();
    $tok = 'fit_' . bin2hex(random_bytes(24));
    q('INSERT INTO api_keys (user_id, name, token_hash, token_enc, created_at) VALUES (?,?,?,?,?)', [$uid, mb_substr($name, 0, 40) ?: 'Key', hash('sha256', $tok), api_key_seal($tok), now()]);
    return $tok;
}

function api_user(bool $allowQuery = false): ?array
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $tok = '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $h, $m)) {
        $tok = $m[1];
    } elseif (!empty($_SERVER['HTTP_X_API_KEY'])) {
        $tok = (string)$_SERVER['HTTP_X_API_KEY'];
    }
    if ($tok === '' && $allowQuery && !empty($_GET['key']) && is_string($_GET['key'])) {
        $tok = $_GET['key'];   // read-only widgets (Rainmeter etc.) cannot always send headers
    }
    if ($tok === '' || strlen($tok) < 20) {
        return null;
    }
    $u = row('SELECT * FROM users WHERE api_token_hash = ? AND is_banned = 0', [hash('sha256', $tok)]);
    if (!$u && setting('api_keys_ok2') === '1') {
        $k = row('SELECT k.id AS kid, k.last_used, u.* FROM api_keys k JOIN users u ON u.id = k.user_id WHERE k.token_hash = ? AND u.is_banned = 0', [hash('sha256', $tok)]);
        if ($k) {
            if (!$k['last_used'] || strtotime((string)$k['last_used']) < time() - 600) {
                q('UPDATE api_keys SET last_used = ? WHERE id = ?', [now(), $k['kid']]);
            }
            unset($k['kid'], $k['last_used']);
            $u = $k;
        }
    }
    return $u ?: null;
}
