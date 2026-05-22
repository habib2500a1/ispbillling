#!/usr/bin/env bash
# Build + upload both mobile APKs to GitHub Releases, then remove local copies.
# Requires: flutter, gh auth login
# Usage: ./scripts/release-all-mobile-apks.sh [https://bill.flixbd.xyz]
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${1:-https://bill.flixbd.xyz}"

export UPLOAD_GITHUB=1
export COPY_LOCAL=0

"$APP_ROOT/scripts/build-mobile-apk.sh" "$BASE_URL"
"$APP_ROOT/scripts/build-mfs-verify-apk.sh" "$BASE_URL"
"$APP_ROOT/scripts/clean-local-apks.sh"

echo ""
echo "Set on production .env (tags from pubspec above):"
echo "MOBILE_USE_GITHUB_RELEASES=true"
echo "MOBILE_GITHUB_REPO=${GITHUB_REPO:-habib2500a1/ispbillling}"
