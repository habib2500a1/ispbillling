#!/usr/bin/env bash
# Zero-touch mobile setup after deploy — reads everything from server .env.
# Called automatically by post-deploy.sh and bootstrap-app.sh (background).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

LOCK="$APP_ROOT/storage/framework/auto-mobile-deploy.lock"
mkdir -p storage/logs storage/framework public/downloads

if [[ -f "$LOCK" ]]; then
  pid="$(cat "$LOCK" 2>/dev/null || echo "")"
  if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
    echo "[auto-mobile] Already running (pid $pid)"
    exit 0
  fi
fi
echo $$ > "$LOCK"
trap 'rm -f "$LOCK"' EXIT

echo "[auto-mobile] $(date -u +%Y-%m-%dT%H:%M:%SZ) start"

APP_URL=""
if [[ -f .env ]]; then
  APP_URL="$(grep -E '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")"
fi
APP_URL="${APP_URL%/}"

if [[ -z "$APP_URL" ]]; then
  echo "[auto-mobile] WARN: APP_URL missing in .env"
  exit 0
fi

if command -v flutter >/dev/null 2>&1 || [[ -x /opt/flutter/bin/flutter ]]; then
  export PATH="/opt/flutter/bin:$PATH"
  echo "[auto-mobile] Flutter found — building APKs for $APP_URL"
  if "$APP_ROOT/scripts/deploy-mobile-apks.sh" "$APP_URL"; then
    echo "[auto-mobile] Build complete"
  else
    echo "[auto-mobile] Build failed — syncing from GitHub Releases"
    "$APP_ROOT/scripts/sync-mobile-apks-from-github.sh" || true
  fi
else
  echo "[auto-mobile] No Flutter — syncing APKs from GitHub Releases"
  "$APP_ROOT/scripts/sync-mobile-apks-from-github.sh" || true
fi

"$APP_ROOT/scripts/use-server-mobile-downloads.sh" --write-env || true

if [[ -f vendor/autoload.php ]]; then
  php artisan config:clear --no-interaction 2>/dev/null || true
  php artisan config:cache --no-interaction 2>/dev/null || true
fi

echo "[auto-mobile] Download links:"
echo "  ${APP_URL}/downloads/isp-radiant.apk"
echo "  ${APP_URL}/downloads/isp-mfs-verify.apk"
echo "[auto-mobile] done"
