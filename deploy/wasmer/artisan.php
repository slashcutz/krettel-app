<?php

declare(strict_types=1);

$dbFile = getenv('DB_DATABASE') ?: '/app/storage/database.sqlite';
$dbDir = dirname($dbFile);

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

if (!file_exists($dbFile)) {
    touch($dbFile);
}

$GLOBALS['argv'] = array_merge(['artisan'], array_slice($_SERVER['argv'] ?? $argv ?? [], 1));
$_SERVER['argv'] = $GLOBALS['argv'];
$_SERVER['argc'] = count($GLOBALS['argv']);

require '/app/artisan';