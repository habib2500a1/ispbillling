#!/usr/bin/env bash
# Point mobile download config at public/downloads/ on this server (not GitHub Releases).
#
# Usage:
#   ./scripts/use-server-mobile-downloads.sh           # print recommended .env lines
#   ./scripts/use-server-mobile-downloads.sh --write-env
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${ENV_FILE:-$APP_ROOT/.env}"
WRITE=0

if [[ "${1:-}" == "--write-env" ]]; then
  WRITE=1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "No .env at $ENV_FILE"
  exit 1
fi

set_or_replace() {
  local key="$1"
  local value="$2"
  if grep -qE "^${key}=" "$ENV_FILE"; then
    sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
  else
    echo "${key}=${value}" >> "$ENV_FILE"
  fi
}

comment_out() {
  local key="$1"
  sed -i "s|^${key}=|# ${key}=|" "$ENV_FILE"
}

if [[ "$WRITE" == "1" ]]; then
  set_or_replace MOBILE_USE_GITHUB_RELEASES false
  comment_out MOBILE_APK_URL
  comment_out MOBILE_MFS_VERIFY_APK_URL
  echo "Updated $ENV_FILE — server download mode (public/downloads/*.apk)."
else
  cat <<EOF
Add or update in .env (or run with --write-env):

MOBILE_USE_GITHUB_RELEASES=false
# MOBILE_APK_URL=          # leave unset — local public/downloads/isp-radiant.apk wins
# MOBILE_MFS_VERIFY_APK_URL=

When APK files exist in public/downloads/, website links are:
  \${APP_URL}/downloads/isp-radiant.apk
  \${APP_URL}/downloads/isp-mfs-verify.apk
EOF
fi
