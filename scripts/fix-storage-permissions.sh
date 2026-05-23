#!/usr/bin/env bash
# Run after deploy if Artisan was executed as root (prevents view cache 500 errors).
set -euo pipefail
APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
chown -R www-data:www-data "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache"
chmod -R ug+rwx "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache"
find "$APP_ROOT/storage" -type d -exec chmod g+s {} \;
sudo -u www-data php "$APP_ROOT/artisan" view:clear
echo "Storage permissions fixed for www-data."
