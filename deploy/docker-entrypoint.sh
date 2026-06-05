#!/bin/sh
set -e

cd /var/www/html

# Git workspace bind-mount hides image vendor/ — install deps on first start.
if [ ! -f vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  composer install --no-dev --optimize-autoloader --no-scripts
  chown -R www-data:www-data vendor storage bootstrap/cache 2>/dev/null || true
fi

exec "$@"
