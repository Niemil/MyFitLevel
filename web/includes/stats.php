<?php
declare(strict_types=1);

/*
 * Privacy-first visitor statistics.
 *
 * Tier 1 (no consent needed, nothing identifying stored): page, referrer host, country,
 *   device/browser/OS, bot flag, and a visitor id that is a daily-rotating salted hash of
 *   IP+browser. The raw IP is never stored and the hash cannot be linked across days.
 * Tier 2 (only after the visitor clicks OK): a random visitor-id cookie (recurring visitors)
 *   and the IP address, kept for STATS_IP_DAYS days.
 * "Do Not Track" / Global Privacy Control are treated as a No: no banner, tier 1 only.
 */
const STATS_IP_DAYS = 30;
const STATS_KEEP_DAYS = 90;

function stats_ensure(): void
{
    if (setting('stats_tables_ok2', '') === '1') {
        return;
    }
    q("CREATE TABLE IF NOT EXISTS stat_visits (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ts DATETIME NOT NULL,
        day DATE NOT NULL,
        vid CHAR(16) NOT NULL,
        path VARCHAR(190) NOT NULL,
        ref_host VARCHAR(100) NOT NULL DEFAULT '',
        ref_type VARCHAR(10) NOT NULL DEFAULT 'direct',
        utm VARCHAR(60) NOT NULL DEFAULT '',
        country CHAR(2) NOT NULL DEFAULT '',
        device VARCHAR(10) NOT NULL DEFAULT '',
        browser VARCHAR(20) NOT NULL DEFAULT '',
        os VARCHAR(20) NOT NULL DEFAULT '',
        lang CHAR(2) NOT NULL DEFAULT '',
        bot VARCHAR(40) NOT NULL DEFAULT '',
        js TINYINT NOT NULL DEFAULT 0,
        human TINYINT NOT NULL DEFAULT 0,
        dwell INT NOT NULL DEFAULT 0,
        consent TINYINT NOT NULL DEFAULT 0,
        member TINYINT NOT NULL DEFAULT 0,
        ip VARCHAR(45) NULL,
        KEY (ts), KEY (day), KEY (vid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    q("CREATE TABLE IF NOT EXISTS stat_consent (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ts DATETIME NOT NULL,
        day DATE NOT NULL,
        event VARCHAR(10) NOT NULL,
        vid CHAR(16) NOT NULL,
        KEY (day)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    q("CREATE TABLE IF NOT EXISTS geo_ranges (
        ip_start VARBINARY(16) NOT NULL PRIMARY KEY,
        ip_end VARBINARY(16) NOT NULL,
        cc CHAR(2) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
    q("CREATE TABLE IF NOT EXISTS stat_signups (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ts DATETIME NOT NULL,
        day DATE NOT NULL,
        ref_type VARCHAR(10) NOT NULL DEFAULT 'direct',
        ref_host VARCHAR(100) NOT NULL DEFAULT '',
        utm VARCHAR(60) NOT NULL DEFAULT '',
        landing VARCHAR(190) NOT NULL DEFAULT '',
        country CHAR(2) NOT NULL DEFAULT '',
        device VARCHAR(10) NOT NULL DEFAULT '',
        pages INT NOT NULL DEFAULT 0,
        mins INT NOT NULL DEFAULT 0,
        matched TINYINT NOT NULL DEFAULT 0,
        KEY (day)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    set_setting('stats_tables_ok2', '1');
}

function stats_salt(): string
{
    $s = setting('stats_salt', '');
    if ($s === '') {
        $s = bin2hex(random_bytes(24));
        set_setting('stats_salt', $s);
    }
    return $s;
}

/** 0 = no choice yet, 1 = accepted, 2 = rejected, 3 = browser privacy signal (DNT/GPC). */
function stats_consent_state(): int
{
    if (($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1') {
        return 3;
    }
    $c = $_COOKIE['fit_consent'] ?? '';
    return $c === 'yes' ? 1 : ($c === 'no' ? 2 : 0);
}

function stats_vid(int $consent, string $ua): string
{
    if ($consent === 1 && preg_match('/^[a-f0-9]{16}$/', (string)($_COOKIE['fit_vid'] ?? ''))) {
        return (string)$_COOKIE['fit_vid'];
    }
    return substr(hash_hmac('sha256', client_ip() . '|' . $ua . '|' . date('Y-m-d'), stats_salt()), 0, 16);
}

function stats_token(int $id): string
{
    return substr(hash_hmac('sha256', 'hit' . $id, stats_salt()), 0, 16);
}

/** Known bots → name; '' when it looks like a person. */
function stats_bot_name(string $ua): string
{
    if ($ua === '') {
        return 'No user-agent';
    }
    static $known = [
        'googlebot' => 'Googlebot', 'google-inspectiontool' => 'Google', 'adsbot-google' => 'Google Ads',
        'bingbot' => 'Bingbot', 'bingpreview' => 'Bing', 'duckduckbot' => 'DuckDuckGo', 'yandex' => 'Yandex',
        'baiduspider' => 'Baidu', 'applebot' => 'Applebot', 'ahrefsbot' => 'Ahrefs', 'semrushbot' => 'Semrush',
        'mj12bot' => 'Majestic', 'dotbot' => 'DotBot', 'petalbot' => 'PetalBot', 'bytespider' => 'Bytespider',
        'gptbot' => 'GPTBot', 'chatgpt' => 'ChatGPT', 'claudebot' => 'ClaudeBot', 'anthropic' => 'Anthropic',
        'perplexity' => 'Perplexity', 'ccbot' => 'CCBot', 'facebookexternalhit' => 'Facebook preview',
        'meta-externalagent' => 'Meta', 'twitterbot' => 'Twitterbot', 'linkedinbot' => 'LinkedIn preview',
        'slackbot' => 'Slack preview', 'discordbot' => 'Discord preview', 'telegrambot' => 'Telegram preview',
        'whatsapp' => 'WhatsApp preview', 'pinterestbot' => 'Pinterest', 'uptimerobot' => 'UptimeRobot',
        'headlesschrome' => 'Headless Chrome', 'phantomjs' => 'PhantomJS', 'lighthouse' => 'Lighthouse',
        'python-requests' => 'python-requests', 'python-urllib' => 'python-urllib', 'curl/' => 'curl', 'wget' => 'wget',
        'go-http-client' => 'Go client', 'okhttp' => 'okhttp', 'java/' => 'Java client', 'libwww' => 'libwww',
        'scrapy' => 'Scrapy', 'httpclient' => 'HTTP client', 'node-fetch' => 'node-fetch', 'axios' => 'axios',
    ];
    $l = strtolower($ua);
    foreach ($known as $needle => $name) {
        if (str_contains($l, $needle)) {
            return $name;
        }
    }
    if (preg_match('/bot|crawl|spider|slurp|scan|fetch|monitor|check/i', $ua)) {
        return 'Other bot';
    }
    return '';
}

function stats_ua(string $ua): array
{
    $os = str_contains($ua, 'Android') ? 'Android'
        : (preg_match('/iPhone|iPad|iPod/', $ua) ? 'iOS'
        : (str_contains($ua, 'Windows') ? 'Windows'
        : (str_contains($ua, 'Mac OS X') ? 'macOS'
        : (str_contains($ua, 'CrOS') ? 'ChromeOS'
        : (str_contains($ua, 'Linux') ? 'Linux' : '')))));
    $br = preg_match('/Edg(e|A|iOS)?\//', $ua) ? 'Edge'
        : (preg_match('/OPR\/|Opera/', $ua) ? 'Opera'
        : (str_contains($ua, 'SamsungBrowser') ? 'Samsung'
        : (preg_match('/Firefox|FxiOS/', $ua) ? 'Firefox'
        : (preg_match('/Chrome|CriOS/', $ua) ? 'Chrome'
        : (str_contains($ua, 'Safari') ? 'Safari' : '')))));
    $dev = preg_match('/iPad|Tablet/', $ua) ? 'tablet' : (preg_match('/Mobi|iPhone|Android/', $ua) ? 'mobile' : 'desktop');
    return [$dev, $br, $os];
}

function stats_ref(string $ref, string $ownHost): array
{
    $h = strtolower((string)parse_url($ref, PHP_URL_HOST));
    $h = preg_replace('/^(www|m|l|lm|mobile)\./', '', $h);
    if ($h === '') {
        return ['', 'direct'];
    }
    $own = preg_replace('/^www\./', '', strtolower($ownHost));
    if ($h === $own) {
        return ['', 'internal'];
    }
    if (preg_match('/(^|\.)(google|bing|duckduckgo|yahoo|yandex|baidu|ecosia|brave|startpage|qwant)\./', $h . '.')) {
        return [$h, 'search'];
    }
    if (preg_match('/(facebook|fb|instagram|twitter|t|x|reddit|youtube|youtu|tiktok|linkedin|pinterest|threads|discord|telegram|whatsapp|snapchat)\.(com|co|be|me|net|org)$/', $h)
        || $h === 'lnkd.in' || $h === 'fb.me') {
        return [$h, 'social'];
    }
    return [substr($h, 0, 100), 'other'];
}

function stats_country(): string
{
    $cf = strtoupper((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    if (preg_match('/^[A-Z]{2}$/', $cf) && $cf !== 'XX' && $cf !== 'T1') {
        return $cf;
    }
    $bin = @inet_pton(client_ip());
    if ($bin === false) {
        return '';
    }
    if (strlen($bin) === 4) {
        $bin = str_repeat("\0", 10) . "\xff\xff" . $bin;
    }
    try {
        $r = row('SELECT ip_end, cc FROM geo_ranges WHERE ip_start <= ? ORDER BY ip_start DESC LIMIT 1', [$bin]);
    } catch (Throwable $e) {
        return '';
    }
    return ($r && strcmp((string)$r['ip_end'], $bin) >= 0) ? (string)$r['cc'] : '';
}

/**
 * Record this page view. Returns [hitId, token] for the JS beacon or null when not tracked.
 * Never throws: statistics must not be able to break a page.
 */
function stats_track(): ?array
{
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || wants_json()) {
            return null;
        }
        $path = '/' . trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        $b = base();
        if ($b !== '' && str_starts_with($path, $b)) {
            $path = '/' . ltrim(substr($path, strlen($b)), '/');
        }
        if (preg_match('#^/(admin|act|api|cron|stats|img|mod|logout|offline|boss/state)(/|$)#', $path)) {
            return null;
        }
        $u = me();
        if ($u && !empty($u['is_admin'])) {
            return null;
        }
        stats_ensure();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
        $consent = stats_consent_state();
        [$dev, $br, $os] = stats_ua($ua);
        [$rh, $rt] = stats_ref((string)($_SERVER['HTTP_REFERER'] ?? ''), (string)($_SERVER['HTTP_HOST'] ?? ''));
        $utm = substr(preg_replace('/[^A-Za-z0-9_.\-]/', '', (string)($_GET['utm_source'] ?? $_GET['ref'] ?? '')), 0, 60);
        if ($utm !== '' && $rt === 'direct') {
            $rt = 'campaign';
        }
        $lang = strtolower(substr((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2));
        $bot = stats_bot_name($ua);
        if ($bot === '' && ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') === '' && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === '' && !str_contains($ua, 'Safari/') ) {
            $bot = 'Suspicious client';
        }
        q('INSERT INTO stat_visits (ts, day, vid, path, ref_host, ref_type, utm, country, device, browser, os, lang, bot, consent, member, ip)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            now(), date('Y-m-d'), stats_vid($consent, $ua), substr($path, 0, 190), $rh, $rt, $utm, stats_country(),
            $dev, $br, $os, preg_match('/^[a-z]{2}$/', $lang) ? $lang : '', $bot, $consent, $u ? 1 : 0,
            $consent === 1 ? client_ip() : null,
        ]);
        $id = (int)db()->lastInsertId();
        if (random_int(1, 60) === 1) {
            stats_purge();
        }
        return [$id, stats_token($id)];
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Called once when someone registers. Stores ONE anonymous row (no user id, no IP, no visitor id):
 * where the visitor first arrived from and which page they landed on. The visit is found through
 * today's visitor hash (same IP + browser) or, if they accepted cookies, their visitor cookie.
 */
function stats_signup_record(): void
{
    try {
        stats_ensure();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
        $consent = stats_consent_state();
        $vid = stats_vid($consent, $ua);
        $v = row("SELECT MIN(ts) t0, COUNT(*) n FROM stat_visits WHERE vid = ? AND bot = ''", [$vid]);
        $first = null;
        if ($v && (int)$v['n'] > 0) {
            $first = row("SELECT * FROM stat_visits WHERE vid = ? AND bot = '' ORDER BY id ASC LIMIT 1", [$vid]);
        }
        $ok = $first !== null;
        $mins = $ok ? max(0, (int)round((time() - strtotime((string)$first['ts'])) / 60)) : 0;
        q('INSERT INTO stat_signups (ts, day, ref_type, ref_host, utm, landing, country, device, pages, mins, matched) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
            now(), date('Y-m-d'),
            $ok ? $first['ref_type'] : 'direct', $ok ? $first['ref_host'] : '', $ok ? $first['utm'] : '',
            $ok ? $first['path'] : '', $ok ? $first['country'] : stats_country(), $ok ? $first['device'] : '',
            $ok ? (int)$v['n'] : 0, min($mins, 99999), $ok ? 1 : 0,
        ]);
    } catch (Throwable $e) {
    }
}

function stats_purge(): void
{
    q('UPDATE stat_visits SET ip = NULL WHERE ip IS NOT NULL AND ts < ?', [date('Y-m-d H:i:s', time() - STATS_IP_DAYS * 86400)]);
    q('DELETE FROM stat_visits WHERE ts < ?', [date('Y-m-d H:i:s', time() - STATS_KEEP_DAYS * 86400)]);
    q('DELETE FROM stat_signups WHERE ts < ?', [date('Y-m-d H:i:s', time() - 400 * 86400)]);
    q('DELETE FROM stat_consent WHERE ts < ?', [date('Y-m-d H:i:s', time() - STATS_KEEP_DAYS * 86400)]);
}

/** Import a DB-IP "IP to Country Lite" CSV (start,end,country). Returns row count. */
function stats_geo_import(string $file): int
{
    stats_ensure();
    @set_time_limit(600);
    $fh = fopen($file, 'rb');
    if (!$fh) {
        return 0;
    }
    $pdo = db();
    q('DELETE FROM geo_ranges');
    $n = 0;
    $batch = [];
    $flush = function () use (&$batch, $pdo) {
        if (!$batch) {
            return;
        }
        $sql = 'INSERT IGNORE INTO geo_ranges (ip_start, ip_end, cc) VALUES ' . implode(',', array_fill(0, count($batch) / 3, '(?,?,?)'));
        $pdo->prepare($sql)->execute($batch);
        $batch = [];
    };
    $pdo->beginTransaction();
    while (($r = fgetcsv($fh)) !== false) {
        if (count($r) < 3 || !preg_match('/^[A-Za-z]{2}$/', trim($r[2]))) {
            continue;
        }
        $a = @inet_pton(trim($r[0]));
        $b = @inet_pton(trim($r[1]));
        if ($a === false || $b === false) {
            continue;
        }
        if (strlen($a) === 4) { $a = str_repeat("\0", 10) . "\xff\xff" . $a; }
        if (strlen($b) === 4) { $b = str_repeat("\0", 10) . "\xff\xff" . $b; }
        array_push($batch, $a, $b, strtoupper(trim($r[2])));
        $n++;
        if (count($batch) >= 1500) {
            $flush();
        }
    }
    $flush();
    $pdo->commit();
    fclose($fh);
    return $n;
}
