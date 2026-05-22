#!/usr/bin/env bash
# Upload an APK to GitHub Releases (APKs are NOT stored in git).
# Usage:
#   ./scripts/github-release-apk.sh <tag> <apk-file> [asset-name]
#   GITHUB_REPO=owner/repo ./scripts/github-release-apk.sh mfs-verify-v1.0.4 /path/to.apk isp-mfs-verify.apk
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REPO="${GITHUB_REPO:-habib2500a1/ispbillling}"
TAG="${1:?Usage: github-release-apk.sh <tag> <apk-file> [asset-name]}"
APK_FILE="${2:?Missing apk file path}"
ASSET_NAME="${3:-$(basename "$APK_FILE")}"

if ! command -v gh >/dev/null 2>&1; then
  echo "GitHub CLI (gh) required. Install: https://cli.github.com/ then: gh auth login"
  exit 1
fi

if [[ ! -f "$APK_FILE" ]]; then
  echo "APK not found: $APK_FILE"
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  echo "Run: gh auth login"
  exit 1
fi

TITLE="${RELEASE_TITLE:-$TAG}"
NOTES="${RELEASE_NOTES:-Mobile APK — built $(date -u +%Y-%m-%d). Install on staff / payment SIM phones.}"

if ! gh release view "$TAG" -R "$REPO" >/dev/null 2>&1; then
  echo "Creating release $TAG on $REPO ..."
  gh release create "$TAG" -R "$REPO" --title "$TITLE" --notes "$NOTES"
fi

echo "Uploading $ASSET_NAME ..."
gh release upload "$TAG" "$APK_FILE#$ASSET_NAME" -R "$REPO" --clobber

DOWNLOAD_URL="https://github.com/${REPO}/releases/download/${TAG}/${ASSET_NAME}"
echo ""
echo "Published: $DOWNLOAD_URL"
echo ""
echo "Add to .env on server:"
case "$ASSET_NAME" in
  isp-radiant.apk)
    echo "MOBILE_RADIANT_GITHUB_TAG=$TAG"
    echo "MOBILE_APK_URL=$DOWNLOAD_URL"
    ;;
  isp-mfs-verify.apk)
    echo "MOBILE_MFS_GITHUB_TAG=$TAG"
    echo "MOBILE_MFS_VERIFY_APK_URL=$DOWNLOAD_URL"
    ;;
esac
