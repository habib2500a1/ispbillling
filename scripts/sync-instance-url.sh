#!/usr/bin/env bash
# Sync APP_URL + landing domain for one deploy instance (wrapper around artisan).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
INSTANCE_PATH="$APP_ROOT"
APP_URL=""
LANDING=""
PREVIOUS=""
REMEMBER_OLD=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --path=*) INSTANCE_PATH="${1#*=}" ;;
    --url=*) APP_URL="${1#*=}" ;;
    --landing=*) LANDING="${1#*=}" ;;
    --previous=*) PREVIOUS="${1#*=}" ;;
    --remember-old) REMEMBER_OLD=1 ;;
    *)
      echo "Unknown option: $1" >&2
      exit 1
      ;;
  esac
  shift
done

CMD=(php "$APP_ROOT/artisan" isp:sync-instance-url --path="$INSTANCE_PATH")

if [[ -n "$APP_URL" ]]; then
  CMD+=(--url="$APP_URL")
fi

if [[ -n "$LANDING" ]]; then
  CMD+=(--landing="$LANDING")
fi

if [[ -n "$PREVIOUS" ]]; then
  CMD+=(--previous="$PREVIOUS")
fi

if [[ $REMEMBER_OLD -eq 1 ]]; then
  CMD+=(--remember-old)
fi

"${CMD[@]}"
