#!/usr/bin/env bash
# Run after deploy or any `php artisan` as root — prevents 500 "Permission denied" on views.
set -euo pipefail
APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WEB_USER="${WEB_USER:-www-data}"

chown -R "${WEB_USER}:${WEB_USER}" "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"
chmod -R ug+rwx "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"
find "${APP_ROOT}/storage/framework/views" -type f ! -user "${WEB_USER}" -delete 2>/dev/null || true
find "${APP_ROOT}/storage/framework/views" -type f ! -user "${WEB_USER}" -exec chown "${WEB_USER}:${WEB_USER}" {} + 2>/dev/null || true

sudo -u "${WEB_USER}" php "${APP_ROOT}/artisan" view:clear --quiet
sudo -u "${WEB_USER}" php "${APP_ROOT}/artisan" config:cache --quiet

echo "Storage permissions fixed for ${WEB_USER}."
