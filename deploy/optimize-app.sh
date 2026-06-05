#!/bin/sh
# Laravel + Filament production caches (Docker). Safe to run on every app start.

if [ -z "$APP_KEY" ]; then
  exit 0
fi

cd /var/www/html || exit 0

echo "[optimize] Clearing stale caches..."
php artisan config:clear --no-ansi 2>/dev/null || true
php artisan route:clear --no-ansi 2>/dev/null || true
php artisan view:clear --no-ansi 2>/dev/null || true
php artisan cache:clear --no-ansi 2>/dev/null || true
php artisan optimize:clear --no-ansi 2>/dev/null || true
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
