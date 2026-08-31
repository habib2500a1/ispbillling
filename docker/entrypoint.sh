#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
  else
    touch .env
  fi
fi

# Empty APP_KEY from docker env_file blocks Laravel reading the key from .env file.
if [ -z "${APP_KEY}" ]; then
  unset APP_KEY
fi

# Prefer compose/env-injected values when running in Docker
if [ -n "${DB_HOST}" ]; then
  sed -i "s/^DB_HOST=.*/DB_HOST=${DB_HOST}/" .env || true
fi
if [ -n "${DB_DATABASE}" ]; then
  sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE}/" .env || true
fi
if [ -n "${DB_USERNAME}" ]; then
  sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME}/" .env || true
fi
if [ -n "${DB_PASSWORD}" ]; then
  sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" .env || true
fi
if [ -n "${APP_URL}" ]; then
  sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env || true
fi
if [ -n "${APP_ENV}" ]; then
  sed -i "s/^APP_ENV=.*/APP_ENV=${APP_ENV}/" .env || true
fi
if [ -n "${APP_DEBUG}" ]; then
  sed -i "s/^APP_DEBUG=.*/APP_DEBUG=${APP_DEBUG}/" .env || true
fi
if [ -n "${SESSION_DOMAIN}" ]; then
  sed -i "s/^SESSION_DOMAIN=.*/SESSION_DOMAIN=${SESSION_DOMAIN}/" .env || true
fi

# Wait for MySQL
if [ -n "${DB_HOST}" ]; then
  echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
  i=0
  until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'), getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: ''); exit(0); } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
      echo "Database is unavailable after 60s — continuing anyway."
      break
    fi
    sleep 1
  done
fi

if ! grep -qE '^APP_KEY=' .env 2>/dev/null; then
  echo 'APP_KEY=' >> .env
fi
if ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force --no-interaction || true
fi

# Export APP_KEY for php-fpm / queue workers (avoid empty docker env override)
if grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
  export APP_KEY="$(grep '^APP_KEY=' .env | cut -d= -f2- | tr -d '"')"
fi

php artisan storage:link --force --no-interaction 2>/dev/null || true
php artisan cpagol:post-deploy 2>/dev/null || php artisan migrate --force --no-interaction 2>/dev/null || true

# Ensure nginx serves Caddy upstream port (NextDeploy uses 8020).
if ! grep -q 'listen 8020' /etc/nginx/sites-available/default 2>/dev/null; then
  echo "WARN: nginx missing listen 8020 — Caddy proxy may fail after redeploy."
fi

chown -R www-data:www-data storage bootstrap/cache public/storage 2>/dev/null || true

exec "$@"
