#!/usr/bin/env bash
# One server = one .env = one domain. Reads APP_URL from .env, syncs URLs, runs post-deploy.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

if [[ ! -f .env ]]; then
  echo "ERROR: .env not found in $APP_ROOT"
  echo "Set APP_URL=https://your-domain.com in .env first."
  exit 1
fi

APP_URL="$(bash "$APP_ROOT/scripts/read-production-url.sh")"
HOST="${APP_URL#https://}"
HOST="${HOST#http://}"
HOST="${HOST%%/*}"

echo "==> Deploy for domain from .env"
echo "    APP_URL=$APP_URL"
echo "    Host=$HOST"
echo ""

echo "==> Remove old mobile APK (fresh build for this domain)"
rm -f public/downloads/*.apk public/downloads/apk-build-info.json 2>/dev/null || true

echo "==> Clear old Laravel caches"
php artisan optimize:clear --no-interaction 2>/dev/null || true

bash "$APP_ROOT/scripts/sync-instance-url.sh" --path="$APP_ROOT" --remember-old

# shellcheck source=scripts/detect-hosting.sh
source "$APP_ROOT/scripts/detect-hosting.sh"
if [[ "$HOSTING_TYPE" == "vps" ]]; then
  bash "$APP_ROOT/scripts/post-deploy.sh"
else
  bash "$APP_ROOT/scripts/post-deploy-cpanel.sh"
fi

echo ""
echo "==> Build new mobile app for $APP_URL"
if bash "$APP_ROOT/scripts/auto-mobile-after-deploy.sh"; then
  echo "==> Mobile APK ready"
else
  echo "WARN: Mobile build skipped or failed — check storage/logs/auto-mobile-deploy.log"
fi

echo ""
echo "==> Done. App built for $APP_URL"
