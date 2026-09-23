<?php

// Idempotent local setup: never replace an existing key or database.
chdir(dirname(__DIR__));
if (! is_file('.env')) {
    copy('.env.example', '.env');
}
$env = file_get_contents('.env');
if (preg_match('/^APP_KEY=\s*$/m', $env)) {
    $env = preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL, $env);
    file_put_contents('.env', $env);
}
if (! is_file('database/database.sqlite')) {
    touch('database/database.sqlite');
}
echo 'Local environment and SQLite file ready.'.PHP_EOL;
