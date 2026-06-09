#!/bin/sh

chmod -R 777 /var/www/html/bootstrap/cache

mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/app/purifier/HTML

mkdir -p /data/storage
mkdir -p /data/plugins
mkdir -p /data/cache/data
mkdir -p /data/sessions
mkdir -p /data/views

# Point Laravel framework dirs to persistent volume
rm -rf /var/www/html/storage/framework/cache
ln -sf /data/cache /var/www/html/storage/framework/cache
rm -rf /var/www/html/storage/framework/sessions
ln -sf /data/sessions /var/www/html/storage/framework/sessions
rm -rf /var/www/html/storage/framework/views
ln -sf /data/views /var/www/html/storage/framework/views

if [ ! -L /var/www/html/storage/app/public ]; then
    rm -rf /var/www/html/storage/app/public
    ln -sf /data/storage /var/www/html/storage/app/public
fi

if [ ! -L /var/www/html/public/storage ]; then
    rm -rf /var/www/html/public/storage
    ln -sf /var/www/html/storage/app/public /var/www/html/public/storage
fi

chmod -R 777 /var/www/html/storage
chmod -R 777 /data

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

cat > /etc/nginx/conf.d/default.conf << 'NGINX'
server {
    listen 8080 default_server;

    root /var/www/html/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param SCRIPT_NAME $fastcgi_script_name;
        fastcgi_index index.php;
        include fastcgi_params;
    }

    location ~ /\. {
        log_not_found off;
        deny all;
    }
}
NGINX

php-fpm82 -D
sleep 2
echo "Starting nginx on port 8080..."
nginx -g "daemon off;"
