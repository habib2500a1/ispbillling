#!/usr/bin/env bash
# Write public/downloads/apk-build-info.json after a mobile APK build.
# Usage: ./scripts/write-apk-build-info.sh https://your-domain.com
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${1:?Usage: write-apk-build-info.sh https://your-domain.com}"
BASE_URL="${BASE_URL%/}"
API_BASE="${BASE_URL}/api/v1"

RADIANT_VERSION="$(grep -E '^version:' "$APP_ROOT/mobile/isp_radiant/pubspec.yaml" 2>/dev/null | head -1 | awk '{print $2}' || echo '')"
MFS_VERSION="$(grep -E '^version:' "$APP_ROOT/mobile/mfs_verify/pubspec.yaml" 2>/dev/null | head -1 | awk '{print $2}' || echo '')"

mkdir -p "$APP_ROOT/public/downloads"
cat > "$APP_ROOT/public/downloads/apk-build-info.json" <<EOF
{
  "app_url": "${BASE_URL}",
  "api_base_url": "${API_BASE}",
  "built_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "radiant_version": "${RADIANT_VERSION}",
  "mfs_version": "${MFS_VERSION}"
}
EOF

echo "Wrote $APP_ROOT/public/downloads/apk-build-info.json for ${BASE_URL}"
