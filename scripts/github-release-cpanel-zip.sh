#!/usr/bin/env bash
# Build cPanel ZIPs and upload to GitHub Releases.
#
# Usage:
#   ./scripts/github-release-cpanel-zip.sh
#   ./scripts/github-release-cpanel-zip.sh cpanel-v1.0.0
#   TAG=cpanel-latest ./scripts/github-release-cpanel-zip.sh
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

REPO="${GITHUB_REPO:-habib2500a1/ispbillling}"
TAG="${1:-${TAG:-cpanel-latest}}"
SHA="$(git rev-parse --short HEAD 2>/dev/null || echo local)"
DATE="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

if ! command -v gh >/dev/null 2>&1; then
  echo "GitHub CLI (gh) required: https://cli.github.com/"
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  echo "Run: gh auth login"
  exit 1
fi

bash "$APP_ROOT/scripts/build-cpanel-release-zip.sh"

PUBLIC_ZIP="$APP_ROOT/releases/isp-platform-cpanel-public_html.zip"
FULL_ZIP="$APP_ROOT/releases/isp-platform-cpanel-full.zip"

for f in "$PUBLIC_ZIP" "$FULL_ZIP"; do
  if [[ ! -f "$f" ]]; then
    echo "Missing: $f"
    exit 1
  fi
done

NOTES="cPanel/Webuzo ZIP — built ${DATE} (commit ${SHA}).

1. Download isp-platform-cpanel-public_html.zip
2. Unzip in /home/user/ on cPanel
3. Document root = public_html
4. Open https://your-domain.com/install"

PRERELEASE_FLAG=()
if [[ "$TAG" == "cpanel-latest" ]]; then
  PRERELEASE_FLAG=(--prerelease)
fi

if ! gh release view "$TAG" -R "$REPO" >/dev/null 2>&1; then
  echo "Creating release $TAG ..."
  gh release create "$TAG" -R "$REPO" \
    --title "cPanel install ZIP (${TAG})" \
    --notes "$NOTES" \
    "${PRERELEASE_FLAG[@]}"
fi

echo "Uploading ZIP assets ..."
gh release upload "$TAG" \
  "$PUBLIC_ZIP#isp-platform-cpanel-public_html.zip" \
  "$FULL_ZIP#isp-platform-cpanel-full.zip" \
  -R "$REPO" --clobber

echo ""
echo "Release: https://github.com/${REPO}/releases/tag/${TAG}"
echo "Latest:  https://github.com/${REPO}/releases/latest"
