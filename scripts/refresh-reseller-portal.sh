#!/usr/bin/env bash
# Run after deploy or Blade/CSS changes on production (fixes stale PHP OPcache).
set -euo pipefail
cd /var/www/isp-platform

php artisan view:clear
php artisan optimize:clear
php artisan view:cache

if systemctl is-active --quiet php8.3-fpm 2>/dev/null; then
  sudo systemctl reload php8.3-fpm || sudo systemctl restart php8.3-fpm
fi

echo "Reseller portal refreshed. Hard-refresh browser (Ctrl+Shift+R)."
echo "Build tag in footer should show: 2026.06.04-pro-qa2"
