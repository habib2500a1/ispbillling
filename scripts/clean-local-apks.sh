#!/usr/bin/env bash
# Remove local APK binaries from public/downloads (use GitHub Releases instead).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIR="$APP_ROOT/public/downloads"

shopt -s nullglob
files=("$DIR"/*.apk)
if [[ ${#files[@]} -eq 0 ]]; then
  echo "No APK files in $DIR"
  exit 0
fi

echo "Removing ${#files[@]} APK file(s) from public/downloads ..."
du -ch "${files[@]}" | tail -1
rm -f "${files[@]}"
echo "Done. APKs should be served from GitHub Releases."
