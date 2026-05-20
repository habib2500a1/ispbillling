#!/usr/bin/env bash
# Build Kotlin ISP Management APK. Requires Android SDK (ANDROID_HOME).
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/../mobile/isp_android" && pwd)"
OUT_DIR="$(cd "$(dirname "$0")/.." && pwd)/public/downloads"
APK_NAME="${1:-isp-management.apk}"

if [[ -z "${ANDROID_HOME:-}" && -d "$HOME/Android/Sdk" ]]; then
  export ANDROID_HOME="$HOME/Android/Sdk"
fi

cd "$APP_DIR"
chmod +x ./gradlew 2>/dev/null || true
./gradlew assembleRelease --no-daemon

SRC="$APP_DIR/app/build/outputs/apk/release/app-release.apk"
mkdir -p "$OUT_DIR"
cp -f "$SRC" "$OUT_DIR/$APK_NAME"

echo "APK: $OUT_DIR/$APK_NAME"
