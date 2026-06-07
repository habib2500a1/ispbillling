#!/usr/bin/env bash
# Build mobile APKs using Docker (no local Flutter needed). Reads APP_URL from .env.
#
# Usage:
#   bash scripts/build-mobile-docker.sh
#   bash scripts/build-mobile-docker.sh https://anetbd.com
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

read_app_url() {
  if [[ -n "${1:-}" ]]; then
    echo "${1%/}"
    return
  fi
  if [[ -f .env ]]; then
    grep -E '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'"
  fi
}

APP_URL="$(read_app_url "${1:-}")"
APP_URL="${APP_URL%/}"

if [[ -z "$APP_URL" ]]; then
  echo "APP_URL missing in .env"
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker not found"
  exit 1
fi

IMAGE="${MOBILE_BUILDER_IMAGE:-ghcr.io/cirruslabs/flutter:stable}"

echo "[mobile-docker] Building for ${APP_URL} using ${IMAGE}"

docker run --rm \
  -v "${APP_ROOT}:/app" \
  -w /app \
  -e APP_URL="${APP_URL}" \
  -e COPY_LOCAL=1 \
  -e UPLOAD_GITHUB=0 \
  -e MOBILE_USE_SERVER_DOWNLOADS=1 \
  "${IMAGE}" \
  bash -lc "
    set -euo pipefail
    flutter --version
    chmod +x scripts/*.sh
    ./scripts/deploy-mobile-apks.sh \"\${APP_URL}\"
  "

echo "[mobile-docker] Done — APKs in public/downloads/"
