#!/usr/bin/env bash
# Generate Android launcher icons from assets/images/radiant_app_icon.png
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$APP_ROOT/mobile/isp_radiant/assets/images/radiant_app_icon.png"
RES="$APP_ROOT/mobile/isp_radiant/android/app/src/main/res"

if ! command -v convert >/dev/null 2>&1; then
  echo "ImageMagick convert is required"
  exit 1
fi

if [[ ! -f "$SRC" ]]; then
  echo "Missing $SRC — add Radiant logo first"
  exit 1
fi

declare -A SIZES=(
  [mipmap-mdpi]=48
  [mipmap-hdpi]=72
  [mipmap-xhdpi]=96
  [mipmap-xxhdpi]=144
  [mipmap-xxxhdpi]=192
)

for folder in "${!SIZES[@]}"; do
  size="${SIZES[$folder]}"
  out="$RES/$folder/ic_launcher.png"
  mkdir -p "$RES/$folder"
  convert "$SRC" -resize "${size}x${size}" "$out"
  cp "$out" "$RES/$folder/ic_launcher_round.png"
done

mkdir -p "$RES/mipmap-anydpi-v26"
cat > "$RES/mipmap-anydpi-v26/ic_launcher.xml" <<'EOF'
<?xml version="1.0" encoding="utf-8"?>
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@color/ic_launcher_background"/>
    <foreground android:drawable="@mipmap/ic_launcher_foreground"/>
</adaptive-icon>
EOF
cp "$RES/mipmap-anydpi-v26/ic_launcher.xml" "$RES/mipmap-anydpi-v26/ic_launcher_round.xml"

mkdir -p "$RES/values"
if ! grep -q 'ic_launcher_background' "$RES/values/colors.xml" 2>/dev/null; then
  if [[ -f "$RES/values/colors.xml" ]]; then
    sed -i 's|</resources>|    <color name="ic_launcher_background">#FFFFFF</color>\n</resources>|' "$RES/values/colors.xml"
  else
    cat > "$RES/values/colors.xml" <<'EOF'
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <color name="ic_launcher_background">#FFFFFF</color>
</resources>
EOF
  fi
fi

for folder in mipmap-mdpi mipmap-hdpi mipmap-xhdpi mipmap-xxhdpi mipmap-xxxhdpi; do
  size="${SIZES[$folder]}"
  convert "$SRC" -resize "${size}x${size}" "$RES/$folder/ic_launcher_foreground.png"
done

echo "Generated Radiant launcher icons in $RES"
