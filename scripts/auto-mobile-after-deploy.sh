#!/usr/bin/env bash
# Zero-touch mobile APK — reads APP_URL from server .env only. No GitHub secrets needed.
# Rebuilds automatically when domain changes or APK is missing.
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
  echo "[auto-mobile] WARN: APP_URL missing in .env — skip"
  exit 0
fi

needs_rebuild() {
  local apk="$APP_ROOT/public/downloads/isp-radiant.apk"
  local info="$APP_ROOT/public/downloads/apk-build-info.json"

  if [[ ! -f "$apk" ]] || [[ ! -s "$apk" ]]; then
    return 0
  fi

  if [[ ! -f "$info" ]]; then
    return 0
  fi

  local built
  built="$(grep -o '"app_url"[[:space:]]*:[[:space:]]*"[^"]*"' "$info" 2>/dev/null | head -1 | sed 's/.*"\([^"]*\)"$/\1/' || true)"
  built="${built%/}"

  if [[ -z "$built" ]] || [[ "$built" != "$APP_URL" ]]; then
    echo "[auto-mobile] Domain changed or unknown build: built=${built:-none} current=${APP_URL}"
    return 0
  fi

  return 1
}

try_build() {
  if command -v flutter >/dev/null 2>&1 || [[ -x /opt/flutter/bin/flutter ]]; then
    export PATH="/opt/flutter/bin:$PATH"
    echo "[auto-mobile] Flutter — building for ${APP_URL}"
    "$APP_ROOT/scripts/deploy-mobile-apks.sh" "$APP_URL" && return 0
  fi

  if command -v docker >/dev/null 2>&1; then
    echo "[auto-mobile] Docker — building for ${APP_URL}"
    "$APP_ROOT/scripts/build-mobile-docker.sh" "$APP_URL" && return 0
  fi

  return 1
}

"$APP_ROOT/scripts/use-server-mobile-downloads.sh" --write-env 2>/dev/null || true

if needs_rebuild; then
  if try_build; then
    echo "[auto-mobile] Build complete for ${APP_URL}"
  else
    echo "[auto-mobile] WARN: Could not build APK (install Flutter or Docker)."
    echo "[auto-mobile] WARN: Not syncing old GitHub APK — wrong domain would break the app."
    echo "[auto-mobile] Users can set domain in app: Login → Server settings → ${APP_URL}"
  fi
else
  echo "[auto-mobile] APK already built for ${APP_URL} — skip rebuild"
fi

if [[ -f vendor/autoload.php ]]; then
  php artisan config:clear --no-interaction 2>/dev/null || true
  php artisan config:cache --no-interaction 2>/dev/null || true
fi

echo "[auto-mobile] Download links:"
echo "  ${APP_URL}/downloads/isp-radiant.apk"
echo "  ${APP_URL}/downloads/isp-mfs-verify.apk"
echo "[auto-mobile] done"
