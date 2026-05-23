#!/usr/bin/env bash
# Run after deploy if Artisan was executed as root (prevents view cache 500 errors).
set -euo pipefail
APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
chown -R www-data:www-data "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache"
chmod -R ug+rwx "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache"
find "$APP_ROOT/storage" -type d -exec chmod g+s {} \;
if command -v setfacl >/dev/null 2>&1; then
  setfacl -R -m u:www-data:rwx -m d:u:www-data:rwx "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" 2>/dev/null || true
fi
# Drop stale compiled views (view:cache as root breaks FPM writes).
find "$APP_ROOT/storage/framework/views" -mindepth 1 -name '*.php' -delete 2>/dev/null || true
sudo -u www-data php "$APP_ROOT/artisan" view:clear
sudo -u www-data php "$APP_ROOT/artisan" config:clear
echo "Storage permissions fixed for www-data."
