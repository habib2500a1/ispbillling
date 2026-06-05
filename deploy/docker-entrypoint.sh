#!/bin/sh
set -e

cd /var/www/html

export COMPOSER_ALLOW_SUPERUSER=1
git config --global --add safe.directory /var/www/html 2>/dev/null || true

# Bind-mount can leave vendor/ missing or incomplete — always reconcile deps.
echo "[entrypoint] Ensuring Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-scripts
chown -R www-data:www-data vendor storage bootstrap/cache 2>/dev/null || true

# Create/sync Laravel DB user when it differs from POSTGRES_USER (e.g. isp_app on isp volume).
if [ -f /usr/local/bin/ensure-db-user.sh ]; then
  /usr/local/bin/ensure-db-user.sh
fi

if [ "$#" -eq 0 ]; then
  set -- php-fpm
fi

exec "$@"
