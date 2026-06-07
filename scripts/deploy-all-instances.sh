#!/usr/bin/env bash
# Deploy every enabled instance from deploy/instances.json
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

CONFIG="${ISP_INSTANCES_CONFIG:-$APP_ROOT/deploy/instances.json}"
BRANCH="${DEPLOY_BRANCH:-main}"
EXTRA=()

if [[ ! -f "$CONFIG" ]]; then
  echo "ERROR: $CONFIG not found."
  echo "Copy deploy/instances.example.json → deploy/instances.json and add your domains."
  exit 1
fi

while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-pull) EXTRA+=(--skip-pull) ;;
    --branch=*) BRANCH="${1#*=}" ;;
    --id=*) EXTRA+=(--id="${1#*=}") ;;
    --config=*) CONFIG="${1#*=}" ;;
    *)
      echo "Unknown option: $1" >&2
      exit 1
      ;;
  esac
  shift
done

php "$APP_ROOT/artisan" isp:deploy-all-instances --config="$CONFIG" --branch="$BRANCH" "${EXTRA[@]}"
