<?php
declare(strict_types=1);

// Optional override: if you move the "includes" folder elsewhere, create app_root.php that returns its parent path.
$root = is_file(__DIR__ . '/app_root.php') ? require __DIR__ . '/app_root.php' : dirname(__DIR__);
define('APP_ROOT', $root);

require APP_ROOT . '/includes/bootstrap.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rawurldecode($uri);
$b = base();
if ($b !== '' && str_starts_with($uri, $b)) {
    $uri = substr($uri, strlen($b));
}
$path = trim($uri, '/');
if (str_starts_with($path, 'public/')) {
    $path = substr($path, 7);
}
if ($path === 'index.php') {
    $path = '';
}

$routes = [
    '#^$#'                         => 'home',
    '#^login$#'                    => 'login',
    '#^register$#'                 => 'register',
    '#^logout$#'                   => 'logout',
    '#^feed$#'                     => 'feed',
    '#^log$#'                      => 'log',
    '#^log/draft$#'                => 'draft',
    '#^log/fav$#'                  => 'fav',
    '#^log/pref$#'                 => 'pref',
    '#^log/routine$#'              => 'routine',
    '#^guide/(\d+)$#'              => 'guide',
    '#^workout/(\d+)$#'            => 'workout',
    '#^img/([a-f0-9]{24})(?:/(t))?$#' => 'img',
    '#^verify/([a-f0-9]{32})$#'    => 'verify',
    '#^forgot$#'                   => 'forgot',
    '#^reset/([a-f0-9]{40})$#'     => 'reset',
    '#^unsub/(\d+)/([a-f0-9]{16})$#' => 'unsub',
    '#^cron/([a-f0-9]{24})$#'      => 'cron',
    '#^mod$#'                      => 'mod',
    '#^welcome$#'                  => 'welcome',
    '#^gyms$#'                     => 'gyms',
    '#^routines$#'                 => 'routines',
    '#^routines/(\d+)$#'           => 'routines',
    '#^gym/new$#'                  => 'gymedit',
    '#^gym/(\d+)/edit$#'           => 'gymedit',
    '#^gym/(\d+)$#'                => 'gym',
    '#^post/(\d+)$#'               => 'post',
    '#^fitocracy-archive$#'        => 'fitoarchive',
    '#^u/([A-Za-z0-9_]{3,20})$#'   => 'profile',
    '#^me$#'                       => 'me',
    '#^members$#'                  => 'members',
    '#^groups$#'                   => 'groups',
    '#^g/([a-z0-9-]{1,60})$#'      => 'group',
    '#^quests$#'                   => 'quests',
    '#^boss(?:/state)?$#'          => 'boss',
    '#^shop$#'                     => 'shop',
    '#^android$#'                  => 'android',
    '#^messages$#'                 => 'messages',
    '#^messages/([A-Za-z0-9_]{3,20})$#' => 'messages',
    '#^steps$#'                    => 'steps',
    '#^history$#'                  => 'history',
    '#^teams$#'                    => 'teams',
    '#^team/(\d+)$#'               => 'teams',
    '#^challenges$#'               => 'challenges',
    '#^lists(?:/(\d+))?$#'         => 'lists',
    '#^feedback$#'                 => 'feedback',
    '#^about$#'                    => 'about',
    '#^api-docs$#'                 => 'apidocs',
    '#^widgets$#'                  => 'widgets',
    '#^connect$#'                  => 'connect',
    '#^goals$#'                    => 'goals',
    '#^events$#'                   => 'events',
    '#^badges$#'                   => 'badges',
    '#^leaderboard$#'              => 'leaderboard',
    '#^settings$#'                 => 'settings',
    '#^settings/export$#'          => 'export',
    '#^notifications$#'            => 'notifications',
    '#^admin$#'                    => 'admin',
    '#^act/([a-z_]+)$#'            => 'act',
    '#^api/v1(?:/(.*))?$#'         => 'api',
    '#^manifest\.webmanifest$#'    => 'manifest',
    '#^offline$#'                  => 'offline',
    '#^points$#'                   => 'points',
    '#^levels$#'                   => 'levels',
    '#^updates$#'                  => 'updates',
    '#^privacy$#'                  => 'privacy',
    '#^stats/b$#'                  => 'statsbeacon',
    '#^fitocracy-alternative$#'    => 'alternative',
    '#^exercises$#'                => 'exercises',
    '#^exercises/([a-z0-9-]{1,80})$#' => 'exercise',
    '#^robots\.txt$#'              => 'robots',
    '#^sitemap\.xml$#'             => 'sitemap',
];

foreach ($routes as $re => $page) {
    if (preg_match($re, $path, $m)) {
        array_shift($m);
        $args = $m;
        require APP_ROOT . '/includes/pages/' . $page . '.php';
        exit;
    }
}

http_response_code(404);
layout_header('Not found');
echo '<div class="card center">' . mascot('oops', 120) . '<h2>Page not found</h2><p class="muted">That page doesn\'t exist.</p><a class="btn" href="' . e(url(me() ? 'feed' : '')) . '">Go home</a></div>';
layout_footer();
