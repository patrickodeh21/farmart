#!/bin/sh

# Fix permissions
chmod -R 777 /var/www/html/storage
chmod -R 777 /var/www/html/bootstrap/cache

# Create required directories
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/app/purifier/HTML
mkdir -p /data/plugins
mkdir -p /data/storage

chmod -R 777 /var/www/html/storage

# Restore plugins from volume into container
for plugin_dir in /data/plugins/*/; do
    [ -d "$plugin_dir" ] || continue
    plugin_name=$(basename "$plugin_dir")
    if [ ! -d "/var/www/html/platform/plugins/$plugin_name" ]; then
        cp -r "$plugin_dir" "/var/www/html/platform/plugins/$plugin_name"
    fi
done
chmod -R 777 /var/www/html/platform/plugins

# Sync new container plugins to volume
for plugin_dir in /var/www/html/platform/plugins/*/; do
    [ -d "$plugin_dir" ] || continue
    plugin_name=$(basename "$plugin_dir")
    if [ ! -d "/data/plugins/$plugin_name" ]; then
        cp -r "$plugin_dir" "/data/plugins/$plugin_name"
    fi
done

# Storage persistence via volume
# Mount volume at /data, symlink uploads into place
if [ ! -L /var/www/html/storage/app/public ]; then
    rm -rf /var/www/html/storage/app/public
    ln -sf /data/storage /var/www/html/storage/app/public
fi

php artisan storage:link --force 2>/dev/null || true
php artisan migrate --force 2>/dev/null || true
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

PHP_FPM=$(which php-fpm82 || which php-fpm8 || which php-fpm || echo "")
$PHP_FPM -D
nginx -g "daemon off;"
