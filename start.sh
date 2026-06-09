#!/bin/sh

chmod -R 777 /var/www/html/bootstrap/cache

mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/app/purifier/HTML

mkdir -p /data/storage
mkdir -p /data/plugins

if [ ! -L /var/www/html/storage/app/public ]; then
    rm -rf /var/www/html/storage/app/public
    ln -sf /data/storage /var/www/html/storage/app/public
fi

chmod -R 777 /var/www/html/storage
chmod -R 777 /data/storage

for plugin_dir in /data/plugins/*/; do
    [ -d "$plugin_dir" ] || continue
    plugin_name=$(basename "$plugin_dir")
    if [ ! -d "/var/www/html/platform/plugins/$plugin_name" ]; then
        cp -r "$plugin_dir" "/var/www/html/platform/plugins/$plugin_name"
    fi
done
chmod -R 777 /var/www/html/platform/plugins

for plugin_dir in /var/www/html/platform/plugins/*/; do
    [ -d "$plugin_dir" ] || continue
    plugin_name=$(basename "$plugin_dir")
    if [ ! -d "/data/plugins/$plugin_name" ]; then
        cp -r "$plugin_dir" "/data/plugins/$plugin_name"
    fi
done

php artisan migrate --force 2>/dev/null || true
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo "Starting php-fpm..."
PHP_FPM=$(which php-fpm82 || which php-fpm8 || which php-fpm || echo "")
if [ -z "$PHP_FPM" ]; then
    echo "ERROR: php-fpm not found!"
    exit 1
fi
echo "Found php-fpm at: $PHP_FPM"
$PHP_FPM -D

echo "Starting nginx..."
nginx -g "daemon off;"
