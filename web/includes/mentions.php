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

const MENTIONS_DEFAULT_QUERIES = "fitocracy alternative\nfitocracy replacement\nfitocracy shut down\nlike fitocracy\nfitocracy\ngamified workout tracker\nworkout tracker with levels and points\nRPG fitness app";

function mentions_queries(): array
{
    $raw = (string)setting('mentions_queries', MENTIONS_DEFAULT_QUERIES);
    $out = [];
    foreach (preg_split('/\R/', $raw) as $l) {
        $l = mb_substr(trim($l), 0, 80);
        if ($l !== '' && !in_array($l, $out, true)) {
            $out[] = $l;
        }
    }
    return array_slice($out, 0, 12);
}

function mentions_http(string $url): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => true, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MyFitLevel-mention-search/1.0; +https://myfitlevel.com)', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $r = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ($r !== false && $code >= 200 && $code < 300) ? (string)$r : null;
}

/** 0-100 for how likely a post is someone looking for a Fitocracy-like site. 0 = not relevant. */
function mentions_score(string $title, string $text, float $ageDays): int
{
    $t = mb_strtolower($title . ' ' . $text);
    if (str_contains($t, 'myfitlevel')) {
        return 0;
    }
    if (str_contains($t, 'fitocracy')) {
        $s = 20;
    } elseif (preg_match('/gamif|\brpg\b|level(s|ing)? up|\bxp\b|points/', $t) && preg_match('/workout|fitness|gym|lifting/', $t)
        && preg_match('/tracker|app\b|website|site\b|community/', $t) && preg_match('/looking for|recommend|alternative|anyone know|suggest|\?/', $t)) {
        $s = 25;
    } else {
        return 0;
    }
    if (preg_match('/alternativ|replac|substitut|similar|like fitocracy|instead of|looking for|recommend|anyone know|suggest/', $t)) { $s += 30; }
    if (preg_match('/shut|dead|gone|closed|closing|died|miss(ed)?\b|no longer|sunset/', $t)) { $s += 20; }
    if (str_contains($title, '?')) { $s += 10; }
    if ($ageDays <= 3) { $s += 10; }
    return min(100, $s);
}

/** Searches Hacker News, Stack Exchange (fitness), Lemmy and Reddit for the saved search words. Returns a short status text. */
function mentions_search_run(): string
{
    mentions_ensure();
    @set_time_limit(120);
    ignore_user_abort(true);
    $since = time() - 14 * 86400;
    $found = [];
    $errors = [];
    $redditOk = true;
    foreach (mentions_queries() as $q) {
        $qe = rawurlencode($q);
        $j = mentions_http("https://hn.algolia.com/api/v1/search_by_date?query=$qe&tags=(story,comment)&numericFilters=created_at_i%3E$since&hitsPerPage=20");
        foreach ((json_decode((string)$j, true)['hits'] ?? []) as $h) {
            $txt = trim(strip_tags((string)($h['comment_text'] ?? $h['story_text'] ?? '')));
            $found[] = ['Hacker News', '', (string)($h['author'] ?? ''), (string)($h['title'] ?? $h['story_title'] ?? ''), $txt, 'https://news.ycombinator.com/item?id=' . ($h['objectID'] ?? ''), (int)($h['created_at_i'] ?? time())];
        }
        $j = mentions_http("https://api.stackexchange.com/2.3/search/advanced?order=desc&sort=creation&q=$qe&site=fitness&pagesize=15&fromdate=$since");
        foreach ((json_decode((string)$j, true)['items'] ?? []) as $h) {
            $found[] = ['Stack Exchange', 'fitness', (string)($h['owner']['display_name'] ?? ''), html_entity_decode((string)($h['title'] ?? '')), '', (string)($h['link'] ?? ''), (int)($h['creation_date'] ?? time())];
        }
        $j = mentions_http("https://lemmy.world/api/v3/search?q=$qe&type_=Posts&sort=New&limit=20");
        foreach ((json_decode((string)$j, true)['posts'] ?? []) as $h) {
            $found[] = ['Lemmy', (string)($h['community']['name'] ?? ''), (string)($h['creator']['name'] ?? ''), (string)($h['post']['name'] ?? ''), (string)($h['post']['body'] ?? ''),
                (string)($h['post']['ap_id'] ?? ''), (int)strtotime((string)($h['post']['published'] ?? 'now'))];
        }
        if ($redditOk) {
            $j = mentions_http("https://www.reddit.com/search.json?q=$qe&sort=new&t=month&limit=25");
            if ($j === null) {
                $redditOk = false;
                $errors[] = 'Reddit blocks the server';
            } else {
                foreach ((json_decode($j, true)['data']['children'] ?? []) as $c) {
                    $p = $c['data'] ?? [];
                    $found[] = ['Reddit', 'r/' . ($p['subreddit'] ?? ''), (string)($p['author'] ?? ''), (string)($p['title'] ?? ''), (string)($p['selftext'] ?? ''),
                        'https://www.reddit.com' . ($p['permalink'] ?? ''), (int)($p['created_utc'] ?? time())];
                }
            }
        }
        usleep(300000);
    }
    $rel = 0;
    $new = 0;
    $seen = [];
    foreach ($found as [$src, $place, $author, $title, $text, $url, $ts]) {
        if (!preg_match('#^https?://#i', $url) || isset($seen[$url])) {
            continue;
        }
        $seen[$url] = 1;
        $sc = mentions_score($title, $text, (time() - $ts) / 86400);
        if ($sc <= 0) {
            continue;
        }
        $rel++;
        $st = q('INSERT IGNORE INTO mentions (h, found_at, posted_at, source, place, author, title, snippet, url, score) VALUES (?,?,?,?,?,?,?,?,?,?)', [
            md5($url), now(), date('Y-m-d H:i:s', $ts), $src, mb_substr($place, 0, 80), mb_substr($author, 0, 60), mb_substr($title, 0, 300), mb_substr(trim($text), 0, 600), mb_substr($url, 0, 500), $sc,
        ]);
        $new += $st->rowCount();
    }
    q('DELETE FROM mentions WHERE found_at < ? AND status <> ?', [date('Y-m-d H:i:s', time() - 120 * 86400), 'replied']);
    $info = $rel . ' relevant, ' . $new . ' new' . ($errors ? ' (' . implode('; ', array_unique($errors)) . ')' : '');
    set_setting('mentions_last_run', (string)time());
    set_setting('mentions_last_info', $info);
    return $info;
}

/** Called from the cron job: searches at most about once a day. */
function mentions_cron(): string
{
    if (time() - (int)setting('mentions_last_run', '0') < 20 * 3600) {
        return 'mentions: not due';
    }
    return 'mentions: ' . mentions_search_run();
}
