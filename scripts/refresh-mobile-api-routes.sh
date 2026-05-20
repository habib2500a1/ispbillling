#!/usr/bin/env bash
# Rebuild Laravel route cache and reload PHP-FPM so new mobile API routes work immediately.
set -euo pipefail
cd "$(dirname "$0")/.."

echo "Clearing and rebuilding route cache..."
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan config:clear

if systemctl is-active --quiet php8.3-fpm 2>/dev/null; then
  sudo systemctl reload php8.3-fpm
  echo "Reloaded php8.3-fpm"
elif systemctl is-active --quiet php8.2-fpm 2>/dev/null; then
  sudo systemctl reload php8.2-fpm
  echo "Reloaded php8.2-fpm"
fi

echo "Done. Test: curl -s -o /dev/null -w '%{http_code}' https://bill.flixbd.xyz/api/v1/staff/approvals/pending"
