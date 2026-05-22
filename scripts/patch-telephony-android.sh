#!/usr/bin/env bash
# telephony 0.2.0 lacks android.namespace (required by AGP 8+). Run after flutter pub get.
set -euo pipefail

CACHE="${PUB_CACHE:-$HOME/.pub-cache}"
GRADLE="$CACHE/hosted/pub.dev/telephony-0.2.0/android/build.gradle"

if [[ ! -f "$GRADLE" ]]; then
  echo "telephony not in pub cache — run flutter pub get in a Flutter project first."
  exit 0
fi

if grep -q 'namespace' "$GRADLE"; then
  exit 0
fi

sed -i '/^android {/a\    namespace "com.shounakmulay.telephony"' "$GRADLE"
sed -i 's/compileSdkVersion 31/compileSdkVersion 34/' "$GRADLE"
sed -i 's/jvmTarget = "1.8"/jvmTarget = "11"/' "$GRADLE"
if ! grep -q 'compileOptions' "$GRADLE"; then
  sed -i '/kotlinOptions {/i\    compileOptions {\n        sourceCompatibility JavaVersion.VERSION_11\n        targetCompatibility JavaVersion.VERSION_11\n    }' "$GRADLE"
fi
echo "Patched telephony android namespace: $GRADLE"
