#!/usr/bin/env bash
# Rebuild Laravel route cache for mobile API (run after deploy or route changes).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WEB_USER="${WEB_USER:-www-data}"

cd "$APP_ROOT"

if command -v sudo >/dev/null 2>&1 && [[ "$(id -u)" -eq 0 ]]; then
  chown -R "${WEB_USER}:${WEB_USER}" bootstrap/cache storage 2>/dev/null || true
  sudo -u "${WEB_USER}" php artisan route:cache
else
  php artisan route:cache
fi

if command -v systemctl >/dev/null 2>&1; then
  systemctl reload php8.3-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || true
fi

echo "Route cache rebuilt. Test:"
echo "  curl -sS ${APP_URL:-https://bill.flixbd.xyz}/api/v1/health"
echo "  curl -sS ${APP_URL:-https://bill.flixbd.xyz}/api/v1/mobile/config"
