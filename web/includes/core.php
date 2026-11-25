<?php
declare(strict_types=1);

/* Core helpers shared by the app and the installer. No database needed here. */

const APP_NAME_DEFAULT = 'MyFitLevel';
const LOAD_EXP         = 1.3;   // points grow a little faster than linearly with estimated 1RM (strong lifters level faster)
const POINTS_SCALE     = 0.2085; // points = coef x e1RM(kg)^1.3 x scale; barbell bench 100 lb x 1 = 31 pts;  // global scale: points = coef x e1RM x sqrt(reps) x scale (barbell squat 60 kg x 5 = 61 pts)
const PR_BONUS         = 40;    // bonus XP for a personal record
const WARMUP_FACTOR    = 0.5;   // warm-up sets earn this share of a normal set's points (and never count as PRs)
const LB_PER_KG        = 2.2046226218;

$GLOBALS['CFG'] = [];

function cfg(string $key, $default = null)
{
    return $GLOBALS['CFG'][$key] ?? $default;
}

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base(): string
{
    return rtrim((string)cfg('base_path', ''), '/');
}

function url(string $path = ''): string
{
    return base() . '/' . ltrim($path, '/');
}

function site_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

function asset(string $path): string
{
    $f = APP_ROOT . '/public/assets/' . $path;
    $v = is_file($f) ? filemtime($f) : APP_VERSION;
    return url('assets/' . $path) . '?v=' . $v;
}

function redirect(string $to): never
{
    if (!preg_match('#^https?://#', $to)) {
        $to = url($to);
    }
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function wants_json(): bool
{
    return ($_POST['ajax'] ?? '') === '1'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function json_out($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function safe_back(string $fallback = ''): string
{
    $b = (string)($_POST['back'] ?? '');
    if ($b !== '' && $b[0] === '/' && !str_starts_with($b, '//') && !str_contains($b, "\n")) {
        return $b;
    }
    return url($fallback);
}

/* ---------- Session / CSRF / flash ---------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('fitsess');
    session_set_cookie_params([
        'lifetime' => 2592000,
        'path'     => base() === '' ? '/' : base() . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '2592000');
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        if (wants_json()) {
            json_out(['ok' => false, 'error' => 'Session expired, reload the page.'], 403);
        }
        http_response_code(403);
        exit('Security check failed. Go back, reload the page and try again.');
    }
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_pull(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Formatting ---------- */

function fmt_num(float $n, int $dec = 1): string
{
    $s = number_format($n, $dec, '.', '');
    if (str_contains($s, '.')) {
        $s = rtrim(rtrim($s, '0'), '.');
    }
    return $s;
}

function fmt_weight(?float $kg, string $unit): string
{
    if ($kg === null) {
        return '';
    }
    $v = $unit === 'lb' ? $kg * LB_PER_KG : $kg;
    return fmt_num($v, 1) . ' ' . $unit;
}

function fmt_big(float $n): string
{
    if ($n >= 1000000) {
        return fmt_num($n / 1000000, 2) . 'M';
    }
    if ($n >= 10000) {
        return fmt_num($n / 1000, 1) . 'k';
    }
    return number_format($n, 0, '.', ' ');
}

function fmt_duration(int $sec): string
{
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    $s = $sec % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
}

function rel_time(string $dt): string
{
    $t = strtotime($dt);
    if (!$t) {
        return $dt;
    }
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return intdiv($d, 60) . ' min ago';
    if ($d < 86400) return intdiv($d, 3600) . ' h ago';
    if ($d < 86400 * 7) return intdiv($d, 86400) . ' d ago';
    return date('j M Y', $t);
}

function clean_text(string $s, int $max): string
{
    $s = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '');
    return mb_substr($s, 0, $max);
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

function avatar_color(string $name): string
{
    $h = crc32($name) % 360;
    return "hsl($h 55% 42%)";
}

function avatar(string $name, int $size = 40): string
{
    $ch = mb_strtoupper(mb_substr($name, 0, 1));
    return '<span class="avatar" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . (int)($size * 0.45)
        . 'px;background:' . avatar_color($name) . '">' . e($ch) . '</span>';
}

function pluralize(int $n, string $one, ?string $many = null): string
{
    return $n . ' ' . ($n === 1 ? $one : ($many ?? $one . 's'));
}
