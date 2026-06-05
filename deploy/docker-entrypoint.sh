#!/bin/sh
set -e

cd /var/www/html

export COMPOSER_ALLOW_SUPERUSER=1
git config --global --add safe.directory /var/www/html 2>/dev/null || true

# Bind-mount hides image vendor/ — install on container start when missing.
if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] Installing Composer dependencies..."
  composer install --no-dev --optimize-autoloader --no-scripts
  chown -R www-data:www-data vendor storage bootstrap/cache 2>/dev/null || true
  echo "[entrypoint] Composer install done."
fi

if [ "$#" -eq 0 ]; then
  set -- php-fpm
fi

exec "$@"
