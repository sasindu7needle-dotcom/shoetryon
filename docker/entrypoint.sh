#!/bin/sh
set -e

case "$APP_KEY" in
    base64:*) ;;
    *) echo "APP_KEY is missing or invalid. Set it to the full output of: php artisan key:generate --show (starts with base64:)" >&2; exit 1 ;;
esac

# Render supplies the port to listen on.
PORT="${PORT:-10000}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
touch database/database.sqlite
chown -R www-data:www-data storage bootstrap/cache database

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

exec "$@"
