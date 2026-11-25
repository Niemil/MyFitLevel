<?php
declare(strict_types=1);

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$urls = [['', 1.0], ['gyms', 0.8], ['levels', 0.6],  ['fitocracy-alternative', 0.9], ['points', 0.7], ['exercises', 0.8], ['register', 0.6], ['privacy', 0.2], ['about', 0.5], ['api-docs', 0.3], ['blog', 0.7]];
blog_ensure();
$blogMod = [];
foreach (rows("SELECT slug, updated_at FROM blog_posts WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC", [now()]) as $x) {
    $urls[] = ['blog/' . $x['slug'], 0.8];
    $blogMod['blog/' . $x['slug']] = date('c', (int)strtotime($x['updated_at']));
}
foreach (rows('SELECT slug FROM exercises WHERE active = 1 ORDER BY slug') as $x) {
    $urls[] = ['exercises/' . $x['slug'], 0.5];
}
foreach (rows("SELECT id FROM gyms WHERE is_public = 1 AND kind = 'gym' ORDER BY id") as $x) {
    $urls[] = ['gym/' . $x['id'], 0.6];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$p, $prio]) {
    echo '  <url><loc>' . htmlspecialchars(site_url($p), ENT_XML1) . '</loc>' . (isset($blogMod[$p]) ? '<lastmod>' . $blogMod[$p] . '</lastmod>' : '') . '<priority>' . number_format($prio, 1) . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
