#!/usr/bin/env bash
# After GitHub deploy: build mobile APKs for APP_URL and publish to public/downloads/.
# Website download links then use https://APP_URL/downloads/*.apk (not GitHub).
#
# Usage:
#   ./scripts/deploy-mobile-apks.sh
#   ./scripts/deploy-mobile-apks.sh https://billing.yourisp.com
#   MOBILE_BUILD_ON_DEPLOY=1 ./scripts/post-deploy.sh
#
# Requires Flutter on the server (/opt/flutter/bin or PATH).
# Alternative: GitHub Actions workflow .github/workflows/mobile-apks.yml (build + SCP).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

read_app_url() {
  if [[ -n "${1:-}" ]]; then
    echo "${1%/}"
    return
  fi
  if [[ -f .env ]]; then
    local line
    line="$(grep -E '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")"
    if [[ -n "$line" ]]; then
      echo "${line%/}"
      return
    fi
  fi
  echo ""
}

BASE_URL="$(read_app_url "${1:-}")"
if [[ -z "$BASE_URL" ]]; then
  echo "Usage: $0 [https://your-domain.com]"
  echo "  Or set APP_URL in .env"
  exit 1
fi

if ! command -v flutter >/dev/null 2>&1; then
  export PATH="/opt/flutter/bin:$PATH"
fi

if ! command -v flutter >/dev/null 2>&1; then
  echo "Flutter not found. Install Flutter or use GitHub Actions:"
  echo "  .github/workflows/mobile-apks.yml (build + SCP to server)"
  exit 1
fi

mkdir -p public/downloads
chmod 755 public/downloads

export COPY_LOCAL=1
export UPLOAD_GITHUB=0

echo "[mobile] Building Radiant ISP for ${BASE_URL}/api/v1 ..."
"$APP_ROOT/scripts/build-mobile-apk.sh" "$BASE_URL"

echo "[mobile] Building MFS Verify for ${BASE_URL} ..."
"$APP_ROOT/scripts/build-mfs-verify-apk.sh" "$BASE_URL"

API_BASE="${BASE_URL}/api/v1"
RADIANT_VERSION="$(grep -E '^version:' mobile/isp_radiant/pubspec.yaml 2>/dev/null | head -1 | awk '{print $2}' || echo '')"
MFS_VERSION="$(grep -E '^version:' mobile/mfs_verify/pubspec.yaml 2>/dev/null | head -1 | awk '{print $2}' || echo '')"
cat > public/downloads/apk-build-info.json <<EOF
{
  "app_url": "${BASE_URL}",
  "api_base_url": "${API_BASE}",
  "built_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "radiant_version": "${RADIANT_VERSION}",
  "mfs_version": "${MFS_VERSION}"
}
EOF

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data public/downloads/*.apk public/downloads/*.json 2>/dev/null || true
fi
chmod 644 public/downloads/*.apk public/downloads/*.json 2>/dev/null || true

if [[ "${MOBILE_USE_SERVER_DOWNLOADS:-1}" == "1" ]]; then
  "$APP_ROOT/scripts/use-server-mobile-downloads.sh" --write-env || true
fi

if [[ -f vendor/autoload.php ]]; then
  php artisan config:clear --no-interaction 2>/dev/null || true
  php artisan config:cache --no-interaction 2>/dev/null || true
fi

RADIANT_URL="${BASE_URL}/downloads/isp-radiant.apk"
MFS_URL="${BASE_URL}/downloads/isp-mfs-verify.apk"

echo ""
echo "Mobile APKs published on this server."
echo "  Radiant ISP : $RADIANT_URL"
echo "  MFS Verify  : $MFS_URL"
echo ""
echo "Landing page, portal, and admin panel use these links automatically."
