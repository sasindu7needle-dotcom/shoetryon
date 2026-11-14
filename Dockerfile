# syntax=docker/dockerfile:1

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
COPY scripts ./scripts
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.4-apache
# Python 3 with Pillow builds a DeepAR effect from each GLB Tripo makes
# (scripts/build-deepar-effect.py --auto).
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev python3 python3-pil \
    && docker-php-ext-install zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
# The admin's Add new shoe page uploads four photos of up to 20 MB each.
RUN { echo 'upload_max_filesize=20M'; echo 'post_max_size=90M'; echo 'max_execution_time=180'; } > /usr/local/etc/php/conf.d/uploads.ini
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=assets /app/public/vendor/deepar ./public/vendor/deepar
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache database

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
