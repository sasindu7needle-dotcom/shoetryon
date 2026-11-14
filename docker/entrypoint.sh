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
# The admin's new shoes (photo, Tripo GLB, DeepAR effect) are written under public/ by Apache's user.
mkdir -p public/models/generated public/effects/generated
chown -R www-data:www-data storage bootstrap/cache database public/models/generated public/effects/generated

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
# Fills the shoe library from config/shoes.php when it is empty; an existing library is left alone.
php artisan db:seed --class=ShoeCatalogSeeder --force
# Adds shoes the n8n workflow committed to resources/shoes/*.json, and unhides the ones approved since.
php artisan shopar:sync-shoes
# Builds the DeepAR effect of shoes made from photos that don't have one yet.
php artisan shopar:build-effect || echo "Some shoe effects could not be built; see the admin's shoe pages." >&2
mkdir -p public/effects/generated
chown -R www-data:www-data public/effects/generated database

if [ -n "$ADMIN_USERNAME" ] && [ -n "$ADMIN_PASSWORD" ]; then
    php artisan shopar:admin "$ADMIN_USERNAME" --password="$ADMIN_PASSWORD"
fi

exec "$@"
