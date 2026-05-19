#!/usr/bin/env bash
# Run after each production deploy so PHP opcache picks up code changes.
set -euo pipefail

cd /var/www/isp-platform

sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize:clear

if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload php8.3-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || true
fi

echo "Post-deploy complete: migrations, cache cleared, PHP-FPM reloaded."
