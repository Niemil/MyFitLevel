<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo "User-agent: *\n";
echo "Disallow: " . url('api/') . "\n";
echo "Disallow: " . url('settings') . "\n";
echo "Disallow: " . url('admin') . "\n";
echo "Disallow: " . url('install/') . "\n";
echo "Disallow: " . url('act/') . "\n";
echo "Disallow: " . url('log') . "\n";
echo "Allow: " . url('') . "\n\n";
echo "Sitemap: " . site_url('sitemap.xml') . "\n";
