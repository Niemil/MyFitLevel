<?php
// Example only. The web installer (open /install/ in your browser) creates the real config/config.php for you.
// Never commit config/config.php: it holds your database password and your site secret.
return [
    'db' => ['host' => 'localhost', 'name' => 'your_database', 'user' => 'your_user', 'pass' => 'your_password'],
    'base_path' => '',                 // '' on its own domain, '/fitness' in a subfolder
    'timezone' => 'Europe/Stockholm',
    'secret' => 'change-me-to-64-random-hex-characters',   // the installer generates a random one
    'debug' => false,
];
