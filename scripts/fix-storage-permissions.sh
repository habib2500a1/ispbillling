#!/usr/bin/env bash
# Run after deploy or any "sudo php artisan" — keeps storage writable for PHP-FPM (www-data).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

cd "$APP_ROOT"

if [[ "$(id -u)" -eq 0 ]]; then
  chown -R "${WEB_USER}:${WEB_GROUP}" storage bootstrap/cache
  chmod -R ug+rwx storage bootstrap/cache
  echo "Ownership set to ${WEB_USER}:${WEB_GROUP}"
else
  echo "Run as root: sudo $0"
  exit 1
fi

find "$APP_ROOT/storage/framework/views" -user root -delete 2>/dev/null || true

if command -v sudo >/dev/null 2>&1; then
  sudo -u "${WEB_USER}" php artisan view:clear --quiet 2>/dev/null || true
  sudo -u "${WEB_USER}" php artisan optimize:clear --quiet 2>/dev/null || true
  sudo -u "${WEB_USER}" php artisan isp:repair-dashboard-prefs --quiet 2>/dev/null || true
  sudo -u "${WEB_USER}" php artisan isp:ensure-storage --quiet 2>/dev/null || true
  sudo -u "${WEB_USER}" php artisan route:cache --quiet 2>/dev/null || true
fi

if command -v systemctl >/dev/null 2>&1; then
  systemctl reload php8.3-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || true
fi

echo "Storage permissions OK."
