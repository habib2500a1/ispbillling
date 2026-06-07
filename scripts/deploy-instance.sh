#!/usr/bin/env bash
# Deploy one ISP instance: git pull, sync domain URL, post-deploy, reload PHP.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
INSTANCE_ID=""
INSTANCE_PATH=""
APP_URL=""
LANDING=""
PREVIOUS=""
BRANCH="main"
SKIP_PULL=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --id=*) INSTANCE_ID="${1#*=}" ;;
    --path=*) INSTANCE_PATH="${1#*=}" ;;
    --url=*) APP_URL="${1#*=}" ;;
    --landing=*) LANDING="${1#*=}" ;;
    --previous=*) PREVIOUS="${1#*=}" ;;
    --branch=*) BRANCH="${1#*=}" ;;
    --skip-pull) SKIP_PULL=1 ;;
    *)
      echo "Unknown option: $1" >&2
      exit 1
      ;;
  esac
  shift
done

if [[ -z "$INSTANCE_PATH" || -z "$APP_URL" ]]; then
  echo "Usage: deploy-instance.sh --path=/var/www/instances/example.com --url=https://example.com [--landing=example.com]" >&2
  exit 1
fi

if [[ ! -d "$INSTANCE_PATH" ]]; then
  echo "ERROR: instance path not found: $INSTANCE_PATH" >&2
  exit 1
fi

LABEL="${INSTANCE_ID:-$(basename "$INSTANCE_PATH")}"
echo "---- [$LABEL] deploy start $(date -u +%Y-%m-%dT%H:%M:%SZ) ----"

cd "$INSTANCE_PATH"

if [[ $SKIP_PULL -eq 0 ]]; then
  if [[ -d .git ]]; then
    echo "==> git pull origin ${BRANCH}"
    git fetch origin "$BRANCH"
    git pull --ff-only origin "$BRANCH"
  else
    echo "WARN: no .git in $INSTANCE_PATH — skipping pull"
  fi
fi

SYNC_ARGS=(--path="$INSTANCE_PATH" --url="$APP_URL" --remember-old)
if [[ -n "$LANDING" ]]; then
  SYNC_ARGS+=(--landing="$LANDING")
fi
if [[ -n "$PREVIOUS" ]]; then
  SYNC_ARGS+=(--previous="$PREVIOUS")
fi

bash "$APP_ROOT/scripts/sync-instance-url.sh" "${SYNC_ARGS[@]}"

if [[ -f "$APP_ROOT/scripts/post-deploy.sh" && "$INSTANCE_PATH" == "$APP_ROOT" ]]; then
  bash "$APP_ROOT/scripts/post-deploy.sh"
else
  echo "==> post-deploy (instance copy)"
  sudo -u www-data php artisan isp:generate-webhook-secrets --write --only-missing --no-interaction 2>/dev/null || true
  sudo -u www-data php artisan isp:post-deploy --fast --no-interaction
  sudo -u www-data php artisan isp:post-deploy --processes-only --no-interaction &
  sudo -u www-data php artisan config:clear
  sudo -u www-data php artisan route:clear
  sudo -u www-data php artisan config:cache
  sudo -u www-data php artisan route:cache
  sudo -u www-data php artisan event:cache
  sudo -u www-data php artisan filament:optimize
  if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload php8.3-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || true
  fi
fi

echo "---- [$LABEL] deploy complete ----"
