#!/bin/sh
# Laravel + Filament production caches (Docker). Safe to run on every app start.

if [ -z "$APP_KEY" ]; then
  exit 0
fi

cd /var/www/html || exit 0

# Docker compose: panel .env often keeps 127.0.0.1 — config:cache then breaks session (500 after login).
case "${REDIS_HOST:-}" in
  ""|127.0.0.1|localhost)
    if [ "${DB_HOST:-}" = "postgres" ]; then
      export REDIS_HOST=redis
      echo "[optimize] REDIS_HOST=redis (Docker service; was localhost)"
    fi
    ;;
esac

echo "[optimize] Clearing stale framework caches (keeping Redis app data)..."
php artisan config:clear --no-ansi 2>/dev/null || true
php artisan route:clear --no-ansi 2>/dev/null || true
php artisan view:clear --no-ansi 2>/dev/null || true
php artisan filament:optimize-clear --no-ansi 2>/dev/null || true

echo "[optimize] Building production caches..."
php artisan config:cache --no-ansi 2>/dev/null || true
php artisan route:cache --no-ansi 2>/dev/null || true
php artisan event:cache --no-ansi 2>/dev/null || true
php artisan filament:optimize --no-ansi 2>/dev/null || true

echo "[optimize] Composer autoload..."
export COMPOSER_ALLOW_SUPERUSER=1
composer dump-autoload -o --no-dev --no-scripts 2>/dev/null || true

php artisan config:cache --no-ansi 2>/dev/null || true
php artisan route:cache --no-ansi 2>/dev/null || true
php artisan filament:assets --no-ansi 2>/dev/null || true
php artisan isp:verify-stylesheets --no-ansi 2>/dev/null || true

echo "[optimize] Done."
