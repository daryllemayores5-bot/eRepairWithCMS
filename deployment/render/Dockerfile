FROM composer:2 AS composer
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor gettext-base ca-certificates libpq-dev libicu-dev libzip-dev libonig-dev libcurl4-openssl-dev \
    libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 pdo_pgsql intl zip gd bcmath exif opcache mbstring curl pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/erepair
COPY backend/ ./
RUN mkdir -p bootstrap/cache content resources/blueprints resources/fieldsets storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts \
    && composer check-platform-reqs --no-dev \
    && php artisan package:discover \
    && php artisan vendor:publish --tag=statamic-cp --tag=statamic-frontend --tag=erepair-cp-assets --force \
    && chown -R www-data:www-data storage bootstrap/cache content resources/blueprints resources/fieldsets
COPY deployment/render/nginx.conf.template /etc/nginx/erepair.conf.template
COPY deployment/render/supervisord.conf /etc/supervisor/erepair.conf
COPY deployment/render/entrypoint.sh /usr/local/bin/erepair-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/erepair-entrypoint \
    && chmod 755 /usr/local/bin/erepair-entrypoint \
    && printf 'upload_max_filesize=8M\npost_max_size=30M\nmemory_limit=256M\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/erepair.ini \
    && rm /etc/nginx/sites-enabled/default
EXPOSE 10000
ENTRYPOINT ["erepair-entrypoint"]
