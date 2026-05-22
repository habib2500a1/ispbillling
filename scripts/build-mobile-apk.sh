#!/usr/bin/env bash
# Build RADIANT ISP Android APK. Usage: ./scripts/build-mobile-apk.sh [https://your-domain.com]
# Upload: UPLOAD_GITHUB=1 ./scripts/build-mobile-apk.sh  (needs gh auth login)
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${1:-https://bill.flixbd.xyz}"
API_URL="${BASE_URL%/}/api/v1"
APP_DIR="$APP_ROOT/mobile/isp_radiant"
UPLOAD_GITHUB="${UPLOAD_GITHUB:-0}"
COPY_LOCAL="${COPY_LOCAL:-0}"

if ! command -v flutter >/dev/null 2>&1; then
  export PATH="/opt/flutter/bin:$PATH"
fi

if ! command -v flutter >/dev/null 2>&1; then
  echo "Flutter not found. Install from https://flutter.dev"
  exit 1
fi

cd "$APP_DIR"
flutter pub get
"$APP_ROOT/scripts/patch-telephony-android.sh"

VERSION_LINE="$(grep -E '^version:' pubspec.yaml | head -1 | awk '{print $2}')"
VERSION="${VERSION_LINE%%+*}"
BUILD="${VERSION_LINE#*+}"
BUILD="${BUILD:-1}"
TAG="isp-radiant-v${VERSION}"

flutter build apk --release --dart-define="API_BASE_URL=$API_URL"

OUT="$APP_DIR/build/app/outputs/flutter-apk/app-release.apk"
ASSET="isp-radiant.apk"

if [[ "$COPY_LOCAL" == "1" ]]; then
  DEST="$APP_ROOT/public/downloads/$ASSET"
  mkdir -p "$(dirname "$DEST")"
  cp -f "$OUT" "$DEST"
  echo "Local copy: $DEST"
fi

if [[ "$UPLOAD_GITHUB" == "1" ]]; then
  RELEASE_TITLE="Radiant ISP ${VERSION}+${BUILD}" \
  RELEASE_NOTES="Staff + customer app. API: ${API_URL}" \
  "$APP_ROOT/scripts/github-release-apk.sh" "$TAG" "$OUT" "$ASSET"
else
  echo ""
  echo "APK built: $OUT"
  echo "Version: ${VERSION}+${BUILD}"
  echo "Upload to GitHub:"
  echo "  UPLOAD_GITHUB=1 $0 $BASE_URL"
  echo "  or: ./scripts/github-release-apk.sh $TAG $OUT $ASSET"
fi

echo "Download (after upload): https://github.com/${GITHUB_REPO:-habib2500a1/ispbillling}/releases/download/${TAG}/${ASSET}"
