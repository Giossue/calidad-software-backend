#!/bin/sh
set -eu

cd /var/www/html

for variable_name in APP_KEY APP_URL FRONTEND_URL CORS_ALLOWED_ORIGINS DB_HOST DB_DATABASE DB_USERNAME; do
    if [ -z "$(printenv "$variable_name" 2>/dev/null || true)" ]; then
        echo "Missing required environment variable: $variable_name" >&2
        exit 1
    fi
done

if [ "${APP_ENV:-production}" = "production" ] && [ "${APP_DEBUG:-false}" != "false" ]; then
    echo "APP_DEBUG must be false in production." >&2
    exit 1
fi

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction

exec "$@"
