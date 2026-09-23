<?php

$root = dirname(__DIR__);
$database = getenv('DB_DATABASE') ?: '/data/database.sqlite';
$directory = dirname($database);
if (! is_dir($directory) && ! mkdir($directory, 0770, true)) {
    throw new RuntimeException('Cannot create database directory.');
}
if (! is_file($database) && ! touch($database)) {
    throw new RuntimeException('Cannot create database file.');
}
if (! getenv('APP_KEY')) {
    $keyFile = $directory.'/app.key';
    if (! is_file($keyFile)) {
        if (file_put_contents($keyFile, 'base64:'.base64_encode(random_bytes(32)), LOCK_EX) === false) {
            throw new RuntimeException('Cannot persist application key.');
        }
        chmod($keyFile, 0600);
    }
    if (file_put_contents($root.'/.env', 'APP_KEY='.trim(file_get_contents($keyFile)).PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Cannot prepare application environment.');
    }
    chmod($root.'/.env', 0600);
}
echo "Database and application key ready.\n";
