#!/bin/sh
set -e

DATABASE_PATH="${DB_DATABASE:-/var/www/html/storage/database/database.sqlite}"

mkdir -p storage/database storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    mkdir -p "$(dirname "$DATABASE_PATH")"
    touch "$DATABASE_PATH"
fi

php artisan migrate --force

exec "$@"
