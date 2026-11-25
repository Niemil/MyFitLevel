<?php
declare(strict_types=1);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$name = site_name();
$scope = url('');
echo json_encode([
    'name' => $name,
    'short_name' => mb_substr($name, 0, 12),
    'description' => site_tagline() . '. Track workouts, earn points, level up with friends.',
    'start_url' => url('feed'),
    'scope' => $scope,
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#22303e',
    'theme_color' => '#22303e',
    'icons' => [
        ['src' => url('assets/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('assets/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('assets/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
