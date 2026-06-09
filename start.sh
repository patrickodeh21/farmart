#!/bin/sh

# Fix permissions
chmod -R 777 /var/www/html/bootstrap/cache
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/app/purifier/HTML
chmod -R 777 /var/www/html/storage

# Persistent storage symlink
mkdir -p /data/storage
if [ ! -L /var/www/html/storage/app/public ]; then
    rm -rf /var/www/html/storage/app/public
    ln -sf /data/storage /var/www/html/storage/app/public
fi
chmod -R 777 /data/storage

# Restore plugins from volume
mkdir -p /data/plugins
for plugin_dir in /data/plugins/*/; do
    [ -d "$plugin_dir" ] || continue
    plugin_name=$(basename "$plugin_dir")
    if [ ! -d "/var/www/html/platform/plugins/$plugin_name" ]; then
        cp -r "$plugin_dir" "/var/www/html/platform/plugins/$plugin_name"
    fi
done
chmod -R 777 /var/www/html/platform/plugins

# Sync container plugins to volume
for plugin_dir in /var/www/html/platform/plugins/*/; do
    [ -d "$plugin_dir" ] || continue
    plugin_name=$(basename "$plugin_dir")
    if [ ! -d "/data/plugins/$plugin_name" ]; then
        cp -r "$plugin_dir" "/data/plugins/$plugin_name"
    fi
done

php artisan storage:link --force 2>/dev/null || true
php artisan migrate --force 2>/dev/null || true
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

PHP_FPM=$(which php-fpm82 || which php-fpm8 || which php-fpm || echo "")
$PHP_FPM -D
nginx -g "daemon off;"
