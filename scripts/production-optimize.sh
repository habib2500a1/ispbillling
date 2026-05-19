#!/usr/bin/env bash
# Safe production caches — run after deploy (does not touch customer data).
set -euo pipefail
cd "$(dirname "$0")/.."

if id www-data &>/dev/null; then
  run_artisan() { sudo -u www-data php artisan "$@"; }
else
  run_artisan() { php artisan "$@"; }
fi
export APP_ENV="${APP_ENV:-production}"

echo "==> Clearing stale caches..."
run_artisan config:clear
run_artisan route:clear
run_artisan view:clear
run_artisan cache:clear

echo "==> Building production caches..."
run_artisan config:cache
run_artisan route:cache
# Skip view:cache — bakes config into Blade; use live view composer for /pay OTP flag.
run_artisan event:cache 2>/dev/null || true

echo "==> Optimizing Composer autoloader..."
COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o --no-dev 2>/dev/null || COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o

echo "==> Re-cache after package hooks..."
run_artisan config:cache
run_artisan route:cache

echo "==> Permissions (www-data must own storage for Blade compile)..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || sudo chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || sudo chmod -R ug+rwx storage bootstrap/cache

echo "Done."
