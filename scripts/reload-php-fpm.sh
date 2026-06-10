#!/usr/bin/env bash
# After route:cache / config:cache, FPM workers must reload or new Filament routes 404.
set -euo pipefail

reloaded=0

if command -v systemctl >/dev/null 2>&1; then
  if systemctl is-active --quiet php8.3-fpm 2>/dev/null; then
    systemctl reload php8.3-fpm 2>/dev/null || systemctl restart php8.3-fpm 2>/dev/null || true
    reloaded=1
  elif systemctl is-active --quiet php-fpm 2>/dev/null; then
    systemctl reload php-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true
    reloaded=1
  fi
fi

# Docker / supervisord: signal PID 1 (php-fpm master) in this container
if [[ "$reloaded" -eq 0 ]] && [[ -f /proc/1/comm ]] && grep -qE 'php-fpm|supervisord' /proc/1/comm 2>/dev/null; then
  kill -USR2 1 2>/dev/null || true
  reloaded=1
fi

if [[ "$reloaded" -eq 1 ]]; then
  echo "PHP-FPM reloaded (picked up route/config cache)."
else
  echo "PHP-FPM reload skipped (no systemd/docker master detected)."
fi
