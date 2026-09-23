#!/bin/sh
set -eu
cd /app
php deploy/container-prepare.php
php artisan config:clear
php artisan migrate --seed --force
php artisan config:cache
chown -R www-data:www-data storage bootstrap/cache /data
exec apache2-foreground
