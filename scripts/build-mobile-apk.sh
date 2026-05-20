#!/usr/bin/env bash
# Build RADIANT ISP Android APK. Usage: ./scripts/build-mobile-apk.sh [https://your-domain.com]
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${1:-https://bill.flixbd.xyz}"
API_URL="${BASE_URL%/}/api/v1"
APP_DIR="$APP_ROOT/mobile/isp_radiant"

if ! command -v flutter >/dev/null 2>&1; then
  export PATH="/opt/flutter/bin:$PATH"
fi

if ! command -v flutter >/dev/null 2>&1; then
  echo "Flutter not found. Install from https://flutter.dev"
  exit 1
fi

cd "$APP_DIR"
flutter pub get
flutter build apk --release --dart-define="API_BASE_URL=$API_URL"

OUT="$APP_DIR/build/app/outputs/flutter-apk/app-release.apk"
DEST="$APP_ROOT/public/downloads/isp-radiant.apk"
mkdir -p "$(dirname "$DEST")"
cp -f "$OUT" "$DEST"

echo ""
echo "APK built: $OUT"
echo "Copied to: $DEST"
echo "Download URL: ${BASE_URL%/}/downloads/isp-radiant.apk"
