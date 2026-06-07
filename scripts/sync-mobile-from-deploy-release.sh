#!/usr/bin/env bash
# Download mobile APKs from GitHub release tag mobile-production (built on git push).
# Verifies apk-build-info.json matches server APP_URL before installing.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

APP_URL="$("$APP_ROOT/scripts/read-production-url.sh")"
REPO="${MOBILE_GITHUB_REPO:-habib2500a1/ispbillling}"
TAG="${MOBILE_DEPLOY_RELEASE_TAG:-mobile-production}"
BASE="https://github.com/${REPO}/releases/download/${TAG}"

mkdir -p public/downloads
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

echo "[mobile-sync] Checking ${BASE}/apk-build-info.json"

if ! curl -fsSL "${BASE}/apk-build-info.json" -o "$tmpdir/apk-build-info.json"; then
  echo "[mobile-sync] Release ${TAG} not found or apk-build-info.json missing"
  exit 1
fi

built_url="$(grep -o '"app_url"[[:space:]]*:[[:space:]]*"[^"]*"' "$tmpdir/apk-build-info.json" | head -1 | sed 's/.*"\([^"]*\)"$/\1/' || true)"
built_url="${built_url%/}"

if [[ -z "$built_url" ]] || [[ "$built_url" != "$APP_URL" ]]; then
  echo "[mobile-sync] Domain mismatch: release=${built_url:-?} server=${APP_URL}"
  exit 1
fi

echo "[mobile-sync] Domain OK (${APP_URL}) — downloading APKs"

curl -fsSL "${BASE}/isp-radiant.apk" -o public/downloads/isp-radiant.apk
curl -fsSL "${BASE}/isp-mfs-verify.apk" -o public/downloads/isp-mfs-verify.apk
cp "$tmpdir/apk-build-info.json" public/downloads/apk-build-info.json

if [[ -f public/downloads/mfs-verify-version.json ]]; then
  : # keep if exists
fi

chmod 644 public/downloads/*.apk public/downloads/*.json 2>/dev/null || true
chown www-data:www-data public/downloads/*.apk public/downloads/*.json 2>/dev/null || true

echo "[mobile-sync] Done — ${APP_URL}/downloads/isp-radiant.apk"
