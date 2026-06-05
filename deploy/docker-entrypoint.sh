#!/bin/sh
set -e

cd /var/www/html

export COMPOSER_ALLOW_SUPERUSER=1
git config --global --add safe.directory /var/www/html 2>/dev/null || true

# Bind-mount can leave vendor/ missing or incomplete — reconcile only when needed.
NEED_COMPOSER=0
if [ ! -f vendor/autoload.php ]; then
  NEED_COMPOSER=1
elif [ -f composer.lock ] && [ ! -f vendor/composer/installed.json ]; then
  NEED_COMPOSER=1
elif [ -f composer.lock ] && [ composer.lock -nt vendor/composer/installed.json ]; then
  NEED_COMPOSER=1
fi

if [ "$NEED_COMPOSER" = "1" ]; then
  echo "[entrypoint] Ensuring Composer dependencies..."
  composer install --no-dev --optimize-autoloader --no-scripts
else
  echo "[entrypoint] Vendor OK, skipping composer install"
fi
chown -R www-data:www-data vendor storage bootstrap/cache 2>/dev/null || true

# Create/sync Laravel DB user when it differs from POSTGRES_USER (e.g. isp_app on isp volume).
if [ -f /usr/local/bin/ensure-db-user.sh ]; then
  /usr/local/bin/ensure-db-user.sh || true
fi

if [ "$#" -eq 0 ]; then
  set -- php-fpm
fi

exec "$@"
