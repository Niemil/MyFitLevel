<?php
declare(strict_types=1);

/** Mentions of Fitocracy-style topics found elsewhere on the web (filled by a script on the owner's own computer). */
function mentions_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    q("CREATE TABLE IF NOT EXISTS mentions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        h CHAR(32) NOT NULL,
        found_at DATETIME NOT NULL,
        posted_at DATETIME NULL,
        source VARCHAR(40) NOT NULL DEFAULT '',
        place VARCHAR(80) NOT NULL DEFAULT '',
        author VARCHAR(60) NOT NULL DEFAULT '',
        title VARCHAR(300) NOT NULL DEFAULT '',
        snippet VARCHAR(600) NOT NULL DEFAULT '',
        url VARCHAR(500) NOT NULL,
        score TINYINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(10) NOT NULL DEFAULT 'new',
        UNIQUE KEY (h), KEY (status, score), KEY (found_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function mentions_key(): string
{
    $k = (string)setting('mentions_key', '');
    if ($k === '') {
        $k = bin2hex(random_bytes(20));
        set_setting('mentions_key', $k);
    }
    return $k;
}

/** Talks IMAP over a plain socket (no PHP imap extension needed). Returns the raw reply up to the tagged status line. */
function mentions_imap_cmd($fp, string $tag, string $cmd): string
{
    fwrite($fp, "$tag $cmd\r\n");
    $buf = '';
    while (($line = fgets($fp, 65536)) !== false) {
        $buf .= $line;
        if (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
            $n = (int)$m[1];
            $got = '';
            while (strlen($got) < $n && !feof($fp)) {
                $d = fread($fp, $n - strlen($got));
                if ($d === '' || $d === false) {
                    break;
                }
                $got .= $d;
            }
            $buf .= $got;
            continue;
        }
        if (strncmp($line, "$tag ", strlen($tag) + 1) === 0) {
            break;
        }
    }
    return $buf;
}

/** Splits a raw email into [content-type, decoded text] parts. */
function mentions_mime(string $raw, int $depth = 0): array
{
    [$h, $b] = array_pad(preg_split('/\r?\n\r?\n/', $raw, 2), 2, '');
    $h = (string)preg_replace('/\r?\n[ \t]+/', ' ', (string)$h);
    $ct = 'text/plain';
    $rest = '';
    if (preg_match('/^Content-Type:\s*([^;\s]+)(.*)$/mi', $h, $m)) {
        $ct = strtolower($m[1]);
        $rest = $m[2];
    }
    if (str_starts_with($ct, 'multipart/') && $depth < 5 && preg_match('/boundary="?([^";\s]+)"?/i', $rest, $bm)) {
        $out = [];
        foreach (explode('--' . $bm[1], (string)$b) as $i => $part) {
            if ($i === 0 || str_starts_with(ltrim($part), '--')) {
                continue;
            }
            $out = array_merge($out, mentions_mime(ltrim($part, "\r\n"), $depth + 1));
        }
        return $out;
    }
    $cte = preg_match('/^Content-Transfer-Encoding:\s*(\S+)/mi', $h, $m2) ? strtolower($m2[1]) : '';
    $txt = $cte === 'base64' ? base64_decode((string)$b) : ($cte === 'quoted-printable' ? quoted_printable_decode((string)$b) : (string)$b);
    return [[$ct, (string)$txt]];
}

function mentions_unwrap(string $u): string
{
    $u = html_entity_decode(trim($u), ENT_QUOTES);
    $p = parse_url($u);
    if ($p && str_contains((string)($p['host'] ?? ''), 'google.') && ($p['path'] ?? '') === '/url') {
        parse_str((string)($p['query'] ?? ''), $q);
        if (!empty($q['url'])) {
            return (string)$q['url'];
        }
    }
    return $u;
}

/** Pulls [title, url, snippet] items out of an alert email. */
function mentions_extract(array $parts): array
{
    $html = '';
    $plain = '';
    foreach ($parts as [$ct, $t]) {
        if ($ct === 'text/html' && $html === '') { $html = $t; }
        if ($ct === 'text/plain' && $plain === '') { $plain = $t; }
    }
    $skipT = '/unsubscribe|view all|flag as|see more|edit this|manage|privacy|terms|google alerts|f5bot|^\s*$/i';
    $skipH = '#(^|\.)(f5bot\.com|support\.google\.com|accounts\.google\.com)$#i';
    $items = [];
    if ($html !== '' && preg_match_all('#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $html, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $url = mentions_unwrap($m[1]);
            $title = trim((string)preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[2]), ENT_QUOTES)));
            $host = (string)parse_url($url, PHP_URL_HOST);
            if (!preg_match('#^https?://#i', $url) || $host === '' || preg_match($skipT, $title) || preg_match($skipH, $host) || (preg_match('#(^|\.)google\.com$#i', $host))) {
                continue;
            }
            $items[$url] = [$title, $url, ''];
        }
    }
    if (!$items && $plain !== '') {
        $prev = '';
        foreach (preg_split('/\r?\n/', $plain) as $line) {
            $line = trim($line);
            if (preg_match('#^<?(https?://\S+?)>?$#', $line, $m)) {
                $url = mentions_unwrap($m[1]);
                $host = (string)parse_url($url, PHP_URL_HOST);
                if ($host !== '' && !preg_match($skipH, $host) && !preg_match('#(^|\.)google\.com$#i', $host)) {
                    $items[$url] = [$prev !== '' ? $prev : $url, $url, ''];
                }
            } elseif ($line !== '') {
                $prev = $line;
            }
        }
    }
    return array_values($items);
}

/** Reads unread Google Alerts / F5Bot emails from the site's mailbox and files the posts. Returns a status text. */
function mentions_check_mail(): string
{
    mentions_ensure();
    $c = mail_conf();
    $host = (string)setting('mentions_imap_host', '') ?: (string)$c['host'];
    $port = (int)setting('mentions_imap_port', '993') ?: 993;
    if ($c['pass'] === '' || $c['user'] === '') {
        return 'Add the email login in Admin, Email first.';
    }
    $local = in_array($host, ['127.0.0.1', 'localhost'], true);
    $ctx = stream_context_create(['ssl' => ['verify_peer' => (bool)$c['verify'], 'verify_peer_name' => (bool)$c['verify'], 'allow_self_signed' => !$c['verify']]]);
    $fp = @stream_socket_client(($local ? 'tcp' : 'ssl') . "://$host:$port", $en, $es, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return "Could not connect to $host:$port ($es)";
    }
    stream_set_timeout($fp, 20);
    @set_time_limit(60);
    fgets($fp, 4096);
    $q = static fn(string $v): string => '"' . addcslashes($v, "\\\"") . '"';
    $r = mentions_imap_cmd($fp, 'a1', 'LOGIN ' . $q((string)$c['user']) . ' ' . $q((string)$c['pass']));
    if (!preg_match('/^a1 OK/m', $r)) {
        fclose($fp);
        return 'Mailbox login failed. Check the host in the box below and the email login.';
    }
    mentions_imap_cmd($fp, 'a2', 'SELECT INBOX');
    $r = mentions_imap_cmd($fp, 'a3', 'SEARCH UNSEEN');
    $ids = preg_match('/^\* SEARCH ([\d ]+)/m', $r, $m) ? array_slice(array_map('intval', preg_split('/\s+/', trim($m[1]))), -40) : [];
    $new = 0;
    $mails = 0;
    $notes = json_decode((string)setting('mentions_notes', '[]'), true) ?: [];
    foreach ($ids as $n) {
        $r = mentions_imap_cmd($fp, 'f' . $n, "FETCH $n BODY.PEEK[]<0.300000>");
        if (!preg_match('/\{(\d+)\}\r?\n/', $r, $lm, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        $raw = substr($r, $lm[0][1] + strlen($lm[0][0]), (int)$lm[1][0]);
        $hdr = (string)preg_split('/\r?\n\r?\n/', $raw, 2)[0];
        $hdr = (string)preg_replace('/\r?\n[ \t]+/', ' ', $hdr);
        $from = preg_match('/^From:\s*(.*)$/mi', $hdr, $fm) ? $fm[1] : '';
        $subj = preg_match('/^Subject:\s*(.*)$/mi', $hdr, $sm) ? trim((string)@iconv_mime_decode($sm[1], 0, 'UTF-8')) : '';
        if (!preg_match('/googlealerts|f5bot/i', $from)) {
            continue;   // not ours: leave it unread
        }
        $src = stripos($from, 'f5bot') !== false ? 'F5Bot' : 'Google Alerts';
        $parts = mentions_mime($raw);
        $items = mentions_extract($parts);
        if (preg_match('/confirm|verif|activate|welcome/i', $subj)) {
            $links = [];
            foreach ($parts as [$ct, $t]) {
                if (preg_match_all('#https?://[^\s"\'<>)]+#', html_entity_decode($t), $um)) {
                    $links = array_merge($links, $um[0]);
                }
            }
            $links = array_values(array_filter(array_unique($links), fn($u) => preg_match('/confirm|verif|activate|alerts|f5bot/i', $u)));
            array_unshift($notes, ['t' => date('Y-m-d H:i'), 's' => mb_substr($subj, 0, 120), 'u' => array_slice($links, 0, 3)]);
            $items = [];
        }
        foreach ($items as [$title, $url, $snip]) {
            if (stripos($title . $snip . $url, 'myfitlevel') !== false) {
                continue;
            }
            $sc = max(30, mentions_score($title, $snip, 0));
            $st = q('INSERT IGNORE INTO mentions (h, found_at, posted_at, source, place, author, title, snippet, url, score) VALUES (?,?,?,?,?,?,?,?,?,?)', [
                md5($url), now(), null, $src, mb_substr($subj, 0, 80), '', mb_substr($title, 0, 300), mb_substr($snip, 0, 600), mb_substr($url, 0, 500), $sc,
            ]);
            $new += $st->rowCount();
        }
        $mails++;
        mentions_imap_cmd($fp, 's' . $n, "STORE $n +FLAGS (\\Seen)");
    }
    mentions_imap_cmd($fp, 'z', 'LOGOUT');
    fclose($fp);
    set_setting('mentions_notes', json_encode(array_slice($notes, 0, 6)));
    q('DELETE FROM mentions WHERE found_at < ? AND status <> ?', [date('Y-m-d H:i:s', time() - 120 * 86400), 'replied']);
    $info = $mails . ' alert mail(s) read, ' . $new . ' new post(s)';
    set_setting('mentions_last_run', (string)time());
    set_setting('mentions_last_info', $info);
    return $info;
}

/** Called from the cron job. */
function mentions_cron(): string
{
    try {
        return 'mentions: ' . mentions_check_mail();
    } catch (Throwable $e) {
        return 'mentions: failed';
    }
}

/** 0-100 for how likely a post is someone looking for a Fitocracy-like site. 0 = not relevant. */
function mentions_score(string $title, string $text, float $ageDays): int
{
    $t = mb_strtolower($title . ' ' . $text);
    if (str_contains($t, 'myfitlevel')) {
        return 0;
    }
    $s = str_contains($t, 'fitocracy') ? 20 : 0;
    if (preg_match('/alternativ|replac|substitut|similar|like fitocracy|instead of|looking for|recommend|anyone know|suggest/', $t)) { $s += 30; }
    if (preg_match('/shut|dead|gone|closed|closing|died|miss(ed)?\b|no longer|sunset/', $t)) { $s += 20; }
    if (str_contains($title, '?')) { $s += 10; }
    if ($ageDays <= 3) { $s += 10; }
    return min(100, $s);
}
