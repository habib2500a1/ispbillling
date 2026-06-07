#!/usr/bin/env bash
# Post-deploy for cPanel / Webuzo / shared hosting (no sudo, no systemd).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

# shellcheck source=scripts/detect-hosting.sh
source "$APP_ROOT/scripts/detect-hosting.sh"

PHP_BIN="${PHP_BIN:-php}"
if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  for candidate in /usr/local/bin/ea-php83 /usr/local/bin/ea-php82 /usr/bin/php; do
    if [[ -x "$candidate" ]]; then
      PHP_BIN="$candidate"
      break
    fi
  done
fi

run_artisan() {
  "$PHP_BIN" artisan "$@"
}

export APP_ENV="${APP_ENV:-production}"

echo "==> cPanel/Webuzo post-deploy ($APP_ROOT)"

if [[ -f .env ]]; then
  if APP_URL="$(bash "$APP_ROOT/scripts/read-production-url.sh" 2>/dev/null)"; then
    echo "==> Domain from .env: $APP_URL"
    run_artisan isp:sync-instance-url --remember-old --no-interaction
  fi
fi

if [[ -f composer.json ]] && command -v composer >/dev/null 2>&1; then
  echo "==> Composer install..."
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null \
    || COMPOSER_ALLOW_SUPERUSER=1 php composer.phar install --no-dev --optimize-autoloader --no-interaction 2>/dev/null \
    || true
fi

run_artisan isp:generate-webhook-secrets --write --only-missing --no-interaction 2>/dev/null || true
run_artisan migrate --force --no-interaction
run_artisan isp:post-deploy --fast --no-interaction
run_artisan isp:post-deploy --processes-only --no-interaction 2>/dev/null || true

run_artisan config:clear
run_artisan route:clear
run_artisan view:clear
run_artisan cache:clear
run_artisan optimize:clear 2>/dev/null || true

run_artisan config:cache
run_artisan route:cache
run_artisan event:cache 2>/dev/null || true
run_artisan filament:optimize 2>/dev/null || true

WEB_USER="${WEB_USER:-$(whoami)}"
WEB_GROUP="${WEB_GROUP:-$WEB_USER}"
export WEB_USER WEB_GROUP
bash "$APP_ROOT/scripts/fix-storage-perms.sh"

if [[ ! -L public/storage ]]; then
  run_artisan storage:link --no-interaction 2>/dev/null || true
fi

# Mobile APK sync (background, optional)
if [[ -f scripts/auto-mobile-after-deploy.sh ]]; then
  nohup bash "$APP_ROOT/scripts/auto-mobile-after-deploy.sh" >> "$APP_ROOT/storage/logs/auto-mobile-deploy.log" 2>&1 &
fi

echo "Post-deploy complete (cPanel/Webuzo)."
