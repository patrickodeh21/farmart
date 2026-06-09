FROM dinhquochan/laravel:php8.2

WORKDIR /var/www/html

RUN apk add --no-cache php82-calendar && \
    echo "extension=calendar.so" > /etc/php82/conf.d/00_calendar.ini

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN npm install && npm run production 2>/dev/null || true

# Copy platform assets directly to public
RUN find platform -type d -name "public" | while read dir; do \
      pkg=$(echo $dir | sed 's|platform/||' | sed 's|/public||' | tr '/' '-'); \
      dest="public/vendor/core/$pkg"; \
      mkdir -p "$dest"; \
      cp -r "$dir/." "$dest/"; \
    done

RUN mkdir -p storage/framework/cache/data \
             storage/logs \
             storage/app/purifier/HTML \
             bootstrap/cache \
             public/vendor/core/plugins \
             public/vendor/core/packages \
             public/vendor/core/core && \
    chmod -R 777 storage bootstrap/cache public/vendor public/themes

EXPOSE 80

# Show nginx and php-fpm config for debugging
RUN cat /etc/nginx/nginx.conf || true
RUN cat /etc/nginx/conf.d/default.conf || true  
RUN cat /etc/php82/php-fpm.d/www.conf | grep "^listen" || true
