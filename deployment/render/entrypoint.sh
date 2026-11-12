#!/bin/sh
set -eu
export PORT="${PORT:-10000}"
case "$PORT" in *[!0-9]*|'') echo 'Invalid PORT'; exit 1;; esac
test "$PORT" -ge 1024 && test "$PORT" -le 65535
test -n "${APP_KEY:-}" || { echo 'Configure a stable Laravel APP_KEY.'; exit 1; }
test "${DB_CONNECTION:-}" = pgsql || { echo 'PostgreSQL is required.'; exit 1; }
test "${EREPAIR_DURABLE_FILES:-}" = true || { echo 'Enable durable file storage for this host.'; exit 1; }
envsubst '${PORT}' < /etc/nginx/erepair.conf.template > /etc/nginx/conf.d/erepair.conf
# Bootstrap commands must run before the database cache table exists.
CACHE_STORE=array php artisan config:clear
CACHE_STORE=array php artisan erepair:production-check --skip-database --json
CACHE_STORE=array php artisan migrate --force --no-interaction
php artisan db:seed --class=CatalogSeeder --force --no-interaction
php artisan erepair:initialize-hosted-files
php artisan erepair:bootstrap-hosted-admin
php artisan config:cache
php artisan erepair:production-check --json
# PHP runs as www-data. Only managed data and compiled caches are writable.
chown -R www-data:www-data storage bootstrap/cache content resources/blueprints resources/fieldsets
nginx -t
php-fpm --test
# Keep PHP, the scheduler and Nginx in the foreground under one signal-aware
# supervisor. No web/scheduled process starts before production preflight passes.
exec /usr/bin/supervisord --configuration=/etc/supervisor/erepair.conf
