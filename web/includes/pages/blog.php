<?php
declare(strict_types=1);

blog_ensure();
$name = site_name();
$slug = $args[0] ?? '';
$pub = "status = 'published' AND published_at <= ?";

/* RSS */
if ($slug === 'feed.xml') {
    header('Content-Type: application/rss+xml; charset=utf-8');
    header('Cache-Control: public, max-age=1800');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0"><channel>';
    echo '<title>' . htmlspecialchars($name . ' blog', ENT_XML1) . '</title><link>' . htmlspecialchars(site_url('blog'), ENT_XML1) . '</link><description>News and guides from ' . htmlspecialchars($name, ENT_XML1) . '</description>';
    foreach (rows("SELECT slug, title, excerpt, published_at FROM blog_posts WHERE $pub ORDER BY published_at DESC LIMIT 30", [now()]) as $p) {
        echo '<item><title>' . htmlspecialchars($p['title'], ENT_XML1) . '</title><link>' . htmlspecialchars(site_url('blog/' . $p['slug']), ENT_XML1) . '</link><guid>' . htmlspecialchars(site_url('blog/' . $p['slug']), ENT_XML1) . '</guid><pubDate>' . date('r', (int)strtotime($p['published_at'])) . '</pubDate><description>' . htmlspecialchars($p['excerpt'], ENT_XML1) . '</description></item>';
    }
    echo '</channel></rss>';
    exit;
}

/* Listing */
if ($slug === '') {
    $posts = rows("SELECT slug, title, excerpt, tags, published_at, body FROM blog_posts WHERE $pub ORDER BY published_at DESC", [now()]);
    layout_header('Blog: workout tracking, Fitocracy history and news', '', [
        'index' => true, 'path' => 'blog',
        'desc' => 'Guides, news and stories from ' . $name . ', the free workout tracker with points, levels and quests.',
    ]);
    ?>
<div class="prose">
  <h1 class="pagetitle">Blog</h1>
  <p class="muted">Guides, news and the story behind <?= e($name) ?>. <a href="<?= e(url('blog/feed.xml')) ?>">RSS</a></p>
  <?php if (!$posts): ?><div class="card"><p class="muted">The first posts are on their way.</p></div><?php endif; ?>
  <?php foreach ($posts as $p): ?>
    <a class="card blogcard" href="<?= e(url('blog/' . $p['slug'])) ?>">
      <h2><?= e($p['title']) ?></h2>
      <p><?= e($p['excerpt']) ?></p>
      <small class="muted"><?= e(blog_date($p['published_at'])) ?> · <?= max(1, (int)round(blog_words($p['body']) / 200)) ?> min read</small>
    </a>
  <?php endforeach; ?>
</div>
    <?php
    layout_footer();
    return;
}

/* Single post */
$admin = me() && !empty(me()['is_admin']);
$p = row('SELECT * FROM blog_posts WHERE slug = ?', [$slug]);
if (!$p || (!$admin && ($p['status'] !== 'published' || strtotime((string)$p['published_at']) > time()))) {
    http_response_code(404);
    layout_header('Post not found');
    echo '<div class="card center"><h2>Post not found</h2><a class="btn" href="' . e(url('blog')) . '">All posts</a></div>';
    layout_footer();
    return;
}
if ($p['status'] === 'published' && !$admin && stats_bot_name((string)($_SERVER['HTTP_USER_AGENT'] ?? '')) === '') {
    q('UPDATE blog_posts SET views = views + 1 WHERE id = ?', [$p['id']]);
}
$img = $p['cover'] !== '' ? (str_starts_with($p['cover'], 'http') ? $p['cover'] : site_url(ltrim($p['cover'], '/'))) : site_url('assets/og-image.png');
$url = site_url('blog/' . $p['slug']);
$mins = max(1, (int)round(blog_words($p['body']) / 200));
$schema = [[
    '@context' => 'https://schema.org', '@type' => 'Article',
    'headline' => $p['title'], 'description' => $p['excerpt'], 'image' => $img,
    'datePublished' => date('c', (int)strtotime((string)($p['published_at'] ?: $p['created_at']))),
    'dateModified' => date('c', (int)strtotime((string)$p['updated_at'])),
    'author' => ['@type' => 'Organization', 'name' => $name],
    'publisher' => ['@type' => 'Organization', 'name' => $name, 'logo' => ['@type' => 'ImageObject', 'url' => site_url('assets/icon-192.png')]],
    'mainEntityOfPage' => $url,
], [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $name, 'item' => site_url('')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => site_url('blog')],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $p['title'], 'item' => $url],
    ],
]];
layout_header($p['title'], '', ['index' => $p['status'] === 'published', 'path' => 'blog/' . $p['slug'], 'desc' => $p['excerpt'], 'type' => 'article', 'schema' => $schema, 'image' => $img]);
$more = rows("SELECT slug, title FROM blog_posts WHERE $pub AND id <> ? ORDER BY published_at DESC LIMIT 3", [now(), $p['id']]);
?>
<article class="prose blogpost">
  <?php if ($p['status'] !== 'published'): ?><div class="flash err">Draft preview: only admins can see this.</div><?php endif; ?>
  <p class="muted"><a href="<?= e(url('blog')) ?>">Blog</a></p>
  <h1><?= e($p['title']) ?></h1>
  <p class="muted"><?= e(blog_date($p['published_at'] ?: $p['created_at'])) ?> · <?= $mins ?> min read<?= $p['updated_at'] > ($p['published_at'] ?: $p['created_at']) && substr((string)$p['updated_at'], 0, 10) !== substr((string)$p['published_at'], 0, 10) ? ' · updated ' . e(blog_date($p['updated_at'])) : '' ?></p>
  <?= blog_md((string)$p['body']) ?>
  <div class="band" style="margin-top:28px">
    <?= mascot('happy', 80) ?>
    <h3>Try <?= e($name) ?> free</h3>
    <p class="muted">Points for every set, levels, quests and a friendly community. No ads, open source.</p>
    <a class="btn big" href="<?= e(url(me() ? 'feed' : 'register')) ?>"><?= me() ? 'Open the app' : 'Start free' ?></a>
  </div>
  <?php if ($more): ?>
    <h2>More from the blog</h2>
    <ul><?php foreach ($more as $m): ?><li><a href="<?= e(url('blog/' . $m['slug'])) ?>"><?= e($m['title']) ?></a></li><?php endforeach; ?></ul>
  <?php endif; ?>
</article>
<?php
layout_footer();
