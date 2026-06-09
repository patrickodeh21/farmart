#!/bin/sh
set -e

php artisan storage:link --force 2>/dev/null || true
php artisan migrate --force 2>/dev/null || true
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

php-fpm -D
nginx -g "daemon off;"
