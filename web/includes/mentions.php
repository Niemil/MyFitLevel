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
