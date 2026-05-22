#!/usr/bin/env bash
# Build MFS SMS Verify APK (payment SIM — device key, no admin login).
# Usage: ./scripts/build-mfs-verify-apk.sh [https://your-domain.com]
# Upload: UPLOAD_GITHUB=1 ./scripts/build-mfs-verify-apk.sh
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${1:-https://bill.flixbd.xyz}"
APP_DIR="$APP_ROOT/mobile/mfs_verify"
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
TAG="mfs-verify-v${VERSION}"

flutter build apk --release

OUT="$APP_DIR/build/app/outputs/flutter-apk/app-release.apk"
ASSET="isp-mfs-verify.apk"

if [[ "$COPY_LOCAL" == "1" ]]; then
  DEST="$APP_ROOT/public/downloads/$ASSET"
  mkdir -p "$(dirname "$DEST")"
  cp -f "$OUT" "$DEST"
  MANIFEST="$APP_ROOT/public/downloads/mfs-verify-version.json"
  cat > "$MANIFEST" <<EOF
{
    "app": "mfs_verify",
    "name": "RCL SMS",
    "version": "${VERSION}",
    "build": ${BUILD},
    "github_tag": "${TAG}",
    "published_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
}
EOF
  echo "Local copy: $DEST"
fi

if [[ "$UPLOAD_GITHUB" == "1" ]]; then
  RELEASE_TITLE="MFS SMS Verify ${VERSION}+${BUILD}" \
  RELEASE_NOTES="Payment SIM — bKash/Nagad SMS auto-forward. API base: ${BASE_URL%/}/api/v1" \
  "$APP_ROOT/scripts/github-release-apk.sh" "$TAG" "$OUT" "$ASSET"
  php -r "
    require '$APP_ROOT/vendor/autoload.php';
    \$app = require '$APP_ROOT/bootstrap/app.php';
    \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    App\Support\MobileApkRelease::writeMfsVerifyManifest(['version' => '$VERSION', 'build' => (int)$BUILD]);
  " 2>/dev/null || true
else
  echo ""
  echo "APK built: $OUT"
  echo "Version: ${VERSION}+${BUILD}"
  echo "Upload to GitHub:"
  echo "  UPLOAD_GITHUB=1 $0 $BASE_URL"
  echo "  or: ./scripts/github-release-apk.sh $TAG $OUT $ASSET"
fi

UPDATE_URL="https://github.com/${GITHUB_REPO:-habib2500a1/ispbillling}/releases/download/${TAG}/${ASSET}?v=${VERSION}_${BUILD}"
echo "Download (after upload): $UPDATE_URL"
