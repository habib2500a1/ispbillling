#!/bin/sh
set -e

cd /var/www/html

export COMPOSER_ALLOW_SUPERUSER=1
git config --global --add safe.directory /var/www/html 2>/dev/null || true

# Bind-mount can leave vendor/ missing or incomplete — always reconcile deps.
echo "[entrypoint] Ensuring Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-scripts
chown -R www-data:www-data vendor storage bootstrap/cache 2>/dev/null || true

if [ "$#" -eq 0 ]; then
  set -- php-fpm
fi

exec "$@"
