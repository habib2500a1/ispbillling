#!/bin/sh
# Docker app container: migrate + super-admin bootstrap from .env.

if [ -z "$APP_KEY" ]; then
  echo "[bootstrap] Skipping: APP_KEY not set in Environment"
  exit 0
fi

attempt=0
while [ $attempt -lt 45 ]; do
  if php artisan migrate:status --no-ansi >/dev/null 2>&1; then
    break
  fi
  attempt=$((attempt + 1))
  sleep 2
done

if ! php artisan migrate:status --no-ansi >/dev/null 2>&1; then
  echo "[bootstrap] WARNING: database not reachable"
  exit 0
fi

echo "[bootstrap] Running migrations..."
php artisan migrate --force --no-interaction || {
  echo "[bootstrap] WARNING: migrate failed"
  exit 0
}

echo "[bootstrap] Ensuring super-admin from ISP_ADMIN_* env..."
php artisan isp:bootstrap-admin --no-interaction || true

if [ ! -e public/storage ]; then
  php artisan storage:link --no-interaction 2>/dev/null || true
fi
