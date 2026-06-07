#!/bin/sh
set -e

cd /var/www/html

if [ -f /usr/local/bin/ensure-permissions.sh ]; then
  /usr/local/bin/ensure-permissions.sh || true
fi

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
if [ -f /usr/local/bin/ensure-permissions.sh ]; then
  /usr/local/bin/ensure-permissions.sh || true
fi

if [ -f /usr/local/bin/ensure-db-user.sh ]; then
  /usr/local/bin/ensure-db-user.sh || true &
fi

# Block until fast bootstrap finishes so /admin/login is warm (heavy process sync runs in background).
if [ "$#" -eq 0 ] || [ "$1" = "php-fpm" ]; then
  if [ -f /usr/local/bin/bootstrap-app.sh ]; then
    /usr/local/bin/bootstrap-app.sh || true
  fi
fi

if [ "$#" -eq 0 ]; then
  set -- php-fpm
fi

exec "$@"
