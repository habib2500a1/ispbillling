#!/usr/bin/env bash
# Run after deploy or any `php artisan` as root — keeps web (www-data) able to compile views.
set -euo pipefail
cd "$(dirname "$0")/.."

WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

chown -R "${WEB_USER}:${WEB_GROUP}" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod g+s {} \;
find storage/framework/views -type f -user root -exec chown "${WEB_USER}:${WEB_GROUP}" {} \; 2>/dev/null || true
find storage/framework/views -type f -name '*.php' -exec chmod 664 {} \; 2>/dev/null || true

if command -v sudo >/dev/null 2>&1 && id -u "${WEB_USER}" >/dev/null 2>&1; then
    sudo -u "${WEB_USER}" php artisan view:clear 2>/dev/null || true
fi

echo "Storage permissions fixed for ${WEB_USER}."
