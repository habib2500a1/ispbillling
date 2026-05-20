#!/usr/bin/env bash
# Run after deploy or any `php artisan` as root — keeps web (www-data) able to write caches.
set -euo pipefail
APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WEB_USER="${WEB_USER:-www-data}"

chown -R "${WEB_USER}:${WEB_USER}" "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"
chmod -R ug+rwx "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"
find "${APP_ROOT}/storage" -type d -exec chmod g+s {} \;

if id "${WEB_USER}" &>/dev/null; then
  sudo -u "${WEB_USER}" php "${APP_ROOT}/artisan" view:clear --quiet 2>/dev/null || true
fi

echo "Storage permissions fixed for ${WEB_USER}."
