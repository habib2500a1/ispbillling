#!/bin/sh
# Run inside NextDeploy Terminal (app container) when site shows 500 after login/deploy.
# Usage: sh deploy/fix-500.sh

set -e
cd /var/www/html || exit 1

echo "=== ISP Platform 500 fix ==="

# Docker compose: never cache localhost for redis when DB is postgres service
case "${REDIS_HOST:-}" in
  ""|127.0.0.1|localhost)
    if [ "${DB_HOST:-}" = "postgres" ]; then
      export REDIS_HOST=redis
      echo "[fix] REDIS_HOST=redis (was localhost)"
    fi
    ;;
esac

echo "[fix] APP_URL=${APP_URL:-unset} DB_HOST=${DB_HOST:-unset} REDIS_HOST=${REDIS_HOST:-unset}"

echo "[fix] Removing stuck deploy flags..."
rm -f storage/framework/deploy-bootstrapping 2>/dev/null || true

echo "[fix] Clearing caches (required when CACHE_STORE=failover was set)..."
php artisan optimize:clear --no-ansi 2>/dev/null || true

echo "[fix] Auto-recover site..."
php artisan isp:recover-site --no-ansi 2>/dev/null || {
  php artisan config:clear --no-ansi 2>/dev/null || true
  php artisan config:cache --no-ansi 2>/dev/null || true
  php artisan isp:mark-deploy-ready --no-ansi 2>/dev/null || touch storage/framework/deploy-ready
}

echo "[fix] Redis ping..."
if php artisan tinker --execute="echo Illuminate\Support\Facades\Redis::ping();" 2>/dev/null | grep -q PONG; then
  echo "[fix] Redis: OK (PONG)"
else
  echo "[fix] Redis: FAIL — set REDIS_HOST=redis in Environment, not 127.0.0.1"
fi

echo "[fix] Database..."
if php artisan migrate:status --no-ansi >/dev/null 2>&1; then
  echo "[fix] Database: OK"
else
  echo "[fix] Database: FAIL — check DB_HOST=postgres, DB_PASSWORD=POSTGRES_PASSWORD"
fi

echo "[fix] APP_KEY..."
if php artisan tinker --execute="echo config('app.key') ? 'OK' : 'MISSING';" 2>/dev/null | grep -q OK; then
  echo "[fix] APP_KEY: OK"
else
  echo "[fix] APP_KEY: MISSING — php artisan key:generate --show → paste in Environment"
fi

echo "[fix] CACHE_STORE (must be redis, NOT failover on Laravel 11)..."
grep -E '^CACHE_STORE=' .env 2>/dev/null || echo "[fix] CACHE_STORE not in .env file"

echo "[fix] Permissions..."
if [ -f /usr/local/bin/ensure-permissions.sh ]; then
  /usr/local/bin/ensure-permissions.sh || true
fi

echo "[fix] Done. Hard-refresh browser: ${APP_URL:-}/admin/login"
echo "[fix] If still broken: set CACHE_STORE=redis in panel Environment, redeploy, then run this script again."
