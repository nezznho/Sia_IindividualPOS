#!/bin/bash
# Container start-up: prepare storage, cache config, migrate, seed once, then run Apache.
set -e
cd /var/www/html

# storage/ is a persistent volume; make sure its structure exists
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs storage/app/public
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan view:cache || echo "view:cache failed, continuing"

php artisan migrate --force

# Seed default data (admin user, permissions, settings) only on first start
if [ ! -f storage/.seeded ]; then
    php artisan db:seed --force
    touch storage/.seeded
fi

exec "$@"
