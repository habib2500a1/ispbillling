#!/usr/bin/env bash
# Publish current public/downloads APKs to GitHub release mobile-production.
# Used by CI after build, or manually from a server that already built APKs.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

REPO="${GITHUB_REPO:-habib2500a1/ispbillling}"
TAG="${MOBILE_DEPLOY_RELEASE_TAG:-mobile-production}"

APP_URL="$("$APP_ROOT/scripts/read-production-url.sh")"

for f in public/downloads/isp-radiant.apk public/downloads/isp-mfs-verify.apk public/downloads/apk-build-info.json; do
  if [[ ! -f "$f" ]]; then
    echo "Missing $f — run deploy-mobile-apks.sh first"
    exit 1
  fi
done

if ! command -v gh >/dev/null 2>&1; then
  echo "gh CLI required"
  exit 1
fi

NOTES="Auto deploy mobile APKs for ${APP_URL}. Updated $(date -u +%Y-%m-%dT%H:%M:%SZ)."

if ! gh release view "$TAG" -R "$REPO" >/dev/null 2>&1; then
  gh release create "$TAG" -R "$REPO" --title "Mobile production (${APP_URL})" --notes "$NOTES"
else
  gh release edit "$TAG" -R "$REPO" --notes "$NOTES" 2>/dev/null || true
fi

gh release upload "$TAG" -R "$REPO" --clobber \
  public/downloads/isp-radiant.apk \
  public/downloads/isp-mfs-verify.apk \
  public/downloads/apk-build-info.json

echo "Published: https://github.com/${REPO}/releases/tag/${TAG}"
