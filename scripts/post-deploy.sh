#!/usr/bin/env bash
# Run after each production deploy so PHP opcache picks up code changes.
set -euo pipefail

cd /var/www/isp-platform

sudo -u www-data php artisan isp:generate-webhook-secrets --write --only-missing --no-interaction 2>/dev/null || true
bash ./scripts/ensure-redis-horizon.sh 2>/dev/null || true

sudo -u www-data php artisan isp:post-deploy --fast --no-interaction
sudo -u www-data php artisan isp:post-deploy --processes-only --no-interaction &
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan event:cache
sudo -u www-data php artisan filament:optimize

# Zero-touch: mobile APK sync/build + website download links (reads .env on server)
nohup bash ./scripts/auto-mobile-after-deploy.sh >> storage/logs/auto-mobile-deploy.log 2>&1 &

if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload php8.3-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || true
fi

echo "Post-deploy complete: migrations, cache cleared, PHP-FPM reloaded."
