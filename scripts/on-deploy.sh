#!/usr/bin/env bash
# Run after git pull / NextDeploy redeploy — one command does everything.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

echo "==> on-deploy $(date -u +%Y-%m-%dT%H:%M:%SZ)"

if [[ -f vendor/autoload.php ]]; then
  php artisan migrate --force --no-interaction 2>/dev/null || true
  php artisan isp:post-deploy --fast --no-interaction 2>/dev/null || true
  php artisan config:clear --no-interaction 2>/dev/null || true
  php artisan route:clear --no-interaction 2>/dev/null || true
  php artisan config:cache --no-interaction 2>/dev/null || true
  php artisan route:cache --no-interaction 2>/dev/null || true
  php artisan event:cache --no-interaction 2>/dev/null || true
  php artisan filament:optimize --no-interaction 2>/dev/null || true
fi

# Mobile APK — background (reads APP_URL from .env or deploy/production.url)
if [[ -f scripts/auto-mobile-after-deploy.sh ]]; then
  nohup bash scripts/auto-mobile-after-deploy.sh >> storage/logs/auto-mobile-deploy.log 2>&1 &
fi

echo "==> on-deploy complete"
