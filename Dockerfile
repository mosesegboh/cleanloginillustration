# syntax=docker/dockerfile:1

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

FROM node:22.13-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

FROM php:8.4-cli-alpine AS runtime

WORKDIR /var/www/html

RUN apk add --no-cache oniguruma-dev sqlite-dev \
    && docker-php-ext-install mbstring pdo_sqlite

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/hfm-entrypoint

RUN chmod +x /usr/local/bin/hfm-entrypoint \
    && cp .env.example .env \
    && sed -i 's|^APP_KEY=.*|APP_KEY=base64:LwdlgVJJHy6fFO+7klw6z0EAFltg72sX1MFntnX8dO4=|' .env \
    && sed -i 's|^# DB_DATABASE=.*|DB_DATABASE=/var/www/html/storage/database/database.sqlite|' .env \
    && mkdir -p storage/database storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["hfm-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
