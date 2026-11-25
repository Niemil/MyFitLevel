<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg('db');
        $dsn = 'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}

function row(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $p = []): array
{
    return q($sql, $p)->fetchAll();
}

function val(string $sql, array $p = [])
{
    $r = q($sql, $p)->fetchColumn();
    return $r === false ? null : $r;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function in_clause(array $ids): string
{
    return implode(',', array_fill(0, count($ids), '?'));
}

function setting(string $k, $default = null, bool $reload = false)
{
    static $cache = null;
    if ($reload) {
        $cache = null;
    }
    if ($cache === null) {
        $cache = [];
        foreach (rows('SELECT k, v FROM settings') as $r) {
            $cache[$r['k']] = $r['v'];
        }
    }
    return $cache[$k] ?? $default;
}

function set_setting(string $k, string $v): void
{
    q('REPLACE INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
    setting('', null, true);   // drop the read cache so later reads see the new value
}

/* Simple DB-backed rate limiter: returns true when the action is allowed. */
function rate_ok(string $key, int $max, int $windowSec): bool
{
    $since = date('Y-m-d H:i:s', time() - $windowSec);
    if (random_int(1, 50) === 1) {
        q('DELETE FROM rate_limits WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
    $n = (int)val('SELECT COUNT(*) FROM rate_limits WHERE k = ? AND created_at >= ?', [$key, $since]);
    return $n < $max;
}

function rate_hit(string $key): void
{
    q('INSERT INTO rate_limits (k, created_at) VALUES (?, ?)', [$key, now()]);
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0'), 0, 45);
}
