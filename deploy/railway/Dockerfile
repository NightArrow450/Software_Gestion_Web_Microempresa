FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc vite.config.js ./
RUN npm ci --no-audit --no-fund --ignore-scripts
COPY resources resources
COPY app app
COPY public public
RUN npm run build && test -f public/build/manifest.json

FROM composer:2 AS composer

FROM php:8.4-apache-bookworm
WORKDIR /var/www/html
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip \
    && docker-php-ext-install -j2 bcmath intl opcache pdo_mysql zip \
    && a2enmod rewrite setenvif \
    && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && rm -rf /var/lib/apt/lists/*
COPY deploy/railway/apache.conf /etc/apache2/sites-available/000-default.conf
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --no-progress --no-plugins --no-scripts --optimize-autoloader \
    && composer check-platform-reqs --no-dev
COPY artisan ./
COPY app app
COPY bootstrap bootstrap
COPY config config
COPY database database
COPY lang lang
COPY public public
COPY resources resources
COPY routes routes
COPY deploy/railway/predeploy.php deploy/railway/predeploy.php
COPY --from=assets /app/public/build public/build
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public \
    && chown -R www-data:www-data bootstrap/cache storage \
    && apache2ctl configtest
EXPOSE 8080
