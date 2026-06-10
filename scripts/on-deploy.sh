#!/usr/bin/env bash
# Run after git pull / NextDeploy redeploy — one command does everything.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

echo "==> on-deploy $(date -u +%Y-%m-%dT%H:%M:%SZ)"

run_artisan() {
  if id www-data >/dev/null 2>&1 && command -v runuser >/dev/null 2>&1; then
    runuser -u www-data -- php artisan "$@"
  elif command -v sudo >/dev/null 2>&1 && id www-data >/dev/null 2>&1; then
    sudo -u www-data php artisan "$@"
  else
    php artisan "$@"
  fi
}

if [[ -f vendor/autoload.php ]]; then
  run_artisan migrate --force --no-interaction 2>/dev/null || true
  run_artisan isp:post-deploy --fast --no-interaction 2>/dev/null || true
  run_artisan config:clear --no-interaction 2>/dev/null || true
  run_artisan route:clear --no-interaction 2>/dev/null || true
  run_artisan config:cache --no-interaction 2>/dev/null || true
  run_artisan route:cache --no-interaction 2>/dev/null || true
  run_artisan event:cache --no-interaction 2>/dev/null || true
  run_artisan filament:optimize --no-interaction 2>/dev/null || true
  bash "$APP_ROOT/scripts/reload-php-fpm.sh" 2>/dev/null || true
fi

bash "$APP_ROOT/scripts/fix-storage-perms.sh" 2>/dev/null || true

# Mobile APK — background (reads APP_URL from .env or deploy/production.url)
if [[ -f scripts/auto-mobile-after-deploy.sh ]]; then
  nohup bash scripts/auto-mobile-after-deploy.sh >> storage/logs/auto-mobile-deploy.log 2>&1 &
fi

echo "==> on-deploy complete"
