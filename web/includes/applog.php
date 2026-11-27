<?php
declare(strict_types=1);

/**
 * Site log. Errors, warnings and security events go to the table app_log (kept 30 days) and PHP's own
 * error log goes to logs/ outside public/. Logging must never break a page: everything is wrapped in try/catch.
 * Never log passwords, API keys or search words.
 */
final class AppLog
{
    private static int $count = 0;   // rows written in this request (max 5)
    private static bool $ready = false;
    private static bool $busy = false;

    public static function init(): void
    {
        try {
            ini_set('display_errors', cfg('debug') ? '1' : '0');
            ini_set('log_errors', '1');
            $dir = APP_ROOT . '/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            if (is_dir($dir)) {
                if (!is_file($dir . '/.htaccess')) {
                    @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
                }
                if (!is_file($dir . '/index.html')) {
                    @file_put_contents($dir . '/index.html', '');
                }
                ini_set('error_log', $dir . '/php-error.log');
            }
            $prev = set_exception_handler(static function (Throwable $e) use (&$prev): void {
                self::error('exception', get_class($e) . ': ' . $e->getMessage(), basename($e->getFile()) . ':' . $e->getLine());
                if (is_callable($prev)) {
                    $prev($e);   // the old handler shows the friendly error page
                }
                exit;
            });
            set_error_handler(static function (int $no, string $msg, string $file = '', int $line = 0): bool {
                if (!(error_reporting() & $no)) {
                    return false;   // silenced with @
                }
                if (in_array($no, [E_WARNING, E_USER_WARNING, E_NOTICE, E_USER_NOTICE, E_CORE_WARNING, E_COMPILE_WARNING], true)) {
                    self::warn('php', $msg, basename($file) . ':' . $line);
                }
                return false;   // PHP still writes it to logs/php-error.log
            });
            register_shutdown_function(static function (): void {
                $e = error_get_last();
                if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                    self::error('fatal', (string)$e['message'], basename((string)$e['file']) . ':' . (int)$e['line']);
                }
            });
        } catch (Throwable $t) {
        }
    }

    public static function info(string $kind, string $msg, string $place = ''): void { self::write('info', $kind, $msg, $place); }
    public static function warn(string $kind, string $msg, string $place = ''): void { self::write('warn', $kind, $msg, $place); }
    public static function error(string $kind, string $msg, string $place = ''): void { self::write('error', $kind, $msg, $place); }
    public static function security(string $kind, string $msg, string $place = ''): void { self::write('security', $kind, $msg, $place); }

    /** Removes anything that looks like a key, token or password from a message. */
    public static function clean(string $s): string
    {
        $s = preg_replace('/fit_[A-Za-z0-9]{16,}/', '[key]', $s) ?? $s;
        $s = preg_replace('/Bearer\s+\S+/i', 'Bearer [key]', $s) ?? $s;
        $s = preg_replace('/(password|passwd|pass|token|secret|api[_-]?key)\s*[=:]\s*\S+/i', '$1=[hidden]', $s) ?? $s;
        $s = preg_replace('/[\x00-\x1f]+/', ' ', $s) ?? $s;
        return mb_substr(trim($s), 0, 600);
    }

    /** 1.2.3.4 -> 1.2.3.0; IPv6 keeps the first 4 groups. */
    public static function short_ip(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $p = explode('.', $ip);
            $p[3] = '0';
            return implode('.', $p);
        }
        $bin = @inet_pton($ip);
        if ($bin !== false && strlen($bin) === 16) {
            $g = str_split(bin2hex($bin), 4);
            return implode(':', array_slice($g, 0, 4)) . '::';
        }
        return '';
    }

    private static function ensure(): void
    {
        if (self::$ready) {
            return;
        }
        if (setting('applog_v') !== '1') {
            q('CREATE TABLE IF NOT EXISTS app_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                level ENUM(\'info\',\'warn\',\'error\',\'security\') NOT NULL DEFAULT \'info\',
                kind VARCHAR(30) NOT NULL DEFAULT \'\',
                message VARCHAR(600) NOT NULL DEFAULT \'\',
                place VARCHAR(120) NOT NULL DEFAULT \'\',
                url VARCHAR(200) NOT NULL DEFAULT \'\',
                user_id INT UNSIGNED NULL,
                ip VARCHAR(45) NOT NULL DEFAULT \'\',
                ua VARCHAR(100) NOT NULL DEFAULT \'\',
                h CHAR(32) NOT NULL DEFAULT \'\',
                n INT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                KEY idx_time (created_at),
                KEY idx_level (level, created_at),
                KEY idx_h (h, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            set_setting('applog_v', '1');
        }
        self::$ready = true;
    }

    private static function write(string $level, string $kind, string $msg, string $place): void
    {
        if (self::$busy || self::$count >= 5) {
            return;
        }
        self::$busy = true;   // a failure while logging must not log again
        try {
            if (!function_exists('q')) {
                return;
            }
            self::$count++;
            self::ensure();
            $msg = self::clean($msg);
            $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);   // path only, never the query
            $path = mb_substr($path, 0, 200);
            $uid = null;
            if (!empty($_SESSION['uid'])) {
                $uid = (int)$_SESSION['uid'];
            }
            $ip = self::short_ip((string)($_SERVER['REMOTE_ADDR'] ?? ''));
            $ua = mb_substr(preg_replace('/[\x00-\x1f]+/', ' ', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')) ?? '', 0, 100);
            $h = md5($level . '|' . $kind . '|' . $msg . '|' . $place . '|' . $path);
            $now = date('Y-m-d H:i:s');
            $same = row('SELECT id FROM app_log WHERE h = ? AND created_at >= ? ORDER BY id DESC LIMIT 1', [$h, date('Y-m-d H:i:s', time() - 60)]);
            if ($same) {
                q('UPDATE app_log SET n = n + 1 WHERE id = ?', [(int)$same['id']]);
            } else {
                q('INSERT INTO app_log (level, kind, message, place, url, user_id, ip, ua, h, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$level, mb_substr($kind, 0, 30), $msg, mb_substr($place, 0, 120), $path, $uid, $ip, $ua, $h, $now]);
            }
            if (random_int(1, 200) === 1) {
                q('DELETE FROM app_log WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);   // keep 30 days
            }
        } catch (Throwable $t) {
            // never let logging break a page
        } finally {
            self::$busy = false;
        }
    }
}
