<?php
declare(strict_types=1);

/* Blog: tables are created lazily, launch posts are seeded once as DRAFTS for the admin to review and publish. */

function blog_ensure(): void
{
    if (setting('blog_tables_ok', '') === '1') {
        blog_reseed();
        return;
    }
    q("CREATE TABLE IF NOT EXISTS blog_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(120) NOT NULL UNIQUE,
        title VARCHAR(160) NOT NULL,
        excerpt VARCHAR(300) NOT NULL DEFAULT '',
        body MEDIUMTEXT NOT NULL,
        cover VARCHAR(255) NOT NULL DEFAULT '',
        tags VARCHAR(160) NOT NULL DEFAULT '',
        status VARCHAR(10) NOT NULL DEFAULT 'draft',
        author VARCHAR(40) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        published_at DATETIME NULL,
        updated_at DATETIME NOT NULL,
        views INT NOT NULL DEFAULT 0,
        KEY (status, published_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if ((int)val('SELECT COUNT(*) FROM blog_posts') === 0 && is_file(__DIR__ . '/blog_seed.php')) {
        foreach ((require __DIR__ . '/blog_seed.php') as $p) {
            q('INSERT INTO blog_posts (slug, title, excerpt, body, tags, status, author, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
                [$p['slug'], $p['title'], $p['excerpt'], $p['body'], $p['tags'], 'draft', site_name(), now(), now()]);
        }
    }
    set_setting('blog_tables_ok', '1');
}

function blog_inline(string $s): string
{
    $s = e($s);
    $s = preg_replace('/!\[([^\]]*)\]\((\/?[^\s)]+)\)/', '<img src="$2" alt="$1" loading="lazy">', $s) ?? $s;
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^\s)]+)\)/', function ($m) {
        $href = html_entity_decode($m[2], ENT_QUOTES);
        if (preg_match('#^https?://#i', $href)) {
            $ext = !str_starts_with($href, site_url());
            return '<a href="' . e($href) . '"' . ($ext ? ' rel="noopener" target="_blank"' : '') . '>' . $m[1] . '</a>';
        }
        return '<a href="' . e(url(ltrim($href, '/'))) . '">' . $m[1] . '</a>';
    }, $s) ?? $s;
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?!\w)/s', '<em>$1</em>', $s) ?? $s;
    return $s;
}

/** Small safe markdown: ## ### headings, paragraphs, - lists, 1. lists, > quotes, --- rule, **bold**, *italic*, [x](url), ![alt](img). */
function blog_md(string $md): string
{
    $out = '';
    $lines = preg_split('/\R/', trim($md)) ?: [];
    $para = [];
    $list = null;
    $flushP = function () use (&$para, &$out) {
        if ($para) {
            $out .= '<p>' . blog_inline(implode(' ', $para)) . "</p>\n";
            $para = [];
        }
    };
    $flushL = function () use (&$list, &$out) {
        if ($list) {
            $out .= '<' . $list[0] . ">\n" . implode("\n", array_map(fn($i) => '<li>' . blog_inline($i) . '</li>', $list[1])) . "\n</" . $list[0] . ">\n";
            $list = null;
        }
    };
    foreach ($lines as $ln) {
        $t = trim($ln);
        if ($t === '') { $flushP(); $flushL(); continue; }
        if (preg_match('/^(#{2,3})\s+(.+)$/', $t, $m)) {
            $flushP(); $flushL();
            $lv = strlen($m[1]);
            $out .= "<h$lv id=\"" . e(slugify($m[2])) . "\">" . blog_inline($m[2]) . "</h$lv>\n";
        } elseif ($t === '---') {
            $flushP(); $flushL(); $out .= "<hr>\n";
        } elseif (preg_match('/^>\s?(.*)$/', $t, $m)) {
            $flushP(); $flushL(); $out .= '<blockquote>' . blog_inline($m[1]) . "</blockquote>\n";
        } elseif (preg_match('/^[-*]\s+(.+)$/', $t, $m)) {
            $flushP();
            if (!$list || $list[0] !== 'ul') { $flushL(); $list = ['ul', []]; }
            $list[1][] = $m[1];
        } elseif (preg_match('/^\d+[.)]\s+(.+)$/', $t, $m)) {
            $flushP();
            if (!$list || $list[0] !== 'ol') { $flushL(); $list = ['ol', []]; }
            $list[1][] = $m[1];
        } elseif (preg_match('/^!\[[^\]]*\]\([^)]+\)$/', $t)) {
            $flushP(); $flushL(); $out .= '<p class="bimg">' . blog_inline($t) . "</p>\n";
        } else {
            $flushL();
            $para[] = $t;
        }
    }
    $flushP(); $flushL();
    return $out;
}

function blog_words(string $md): int
{
    return str_word_count(strip_tags(blog_md($md)));
}

function blog_date(?string $dt): string
{
    return $dt ? date('F j, Y', (int)strtotime($dt)) : '';
}

function blog_latest(int $n = 3): array
{
    blog_ensure();
    return rows("SELECT slug, title, excerpt, published_at FROM blog_posts WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC LIMIT " . (int)$n, [now()]);
}

/** A launch post that has a newer revision is replaced only if it is still exactly the old seeded text (never overwrites the admin's own edits). */
function blog_reseed(): void
{
    $done = (int)setting('blog_seed_rev', '1');
    if ($done >= 3 || !is_file(__DIR__ . '/blog_seed.php')) {
        return;
    }
    foreach ((require __DIR__ . '/blog_seed.php') as $p) {
        if (empty($p['prev']) || (int)($p['rev'] ?? 0) <= $done) {
            continue;
        }
        $cur = row('SELECT id, body FROM blog_posts WHERE slug = ?', [$p['slug']]);
        if ($cur && md5(trim(str_replace("\r\n", "\n", (string)$cur['body']))) === $p['prev']) {
            q('UPDATE blog_posts SET title = ?, excerpt = ?, body = ?, tags = ?, updated_at = ? WHERE id = ?', [$p['title'], $p['excerpt'], $p['body'], $p['tags'], now(), $cur['id']]);
        }
    }
    set_setting('blog_seed_rev', '3');
}
