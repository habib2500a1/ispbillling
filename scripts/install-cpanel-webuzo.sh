#!/usr/bin/env bash
# One-click ISP Platform install for cPanel / Webuzo shared hosting.
#
# Usage (after git clone):
#   bash scripts/install-cpanel-webuzo.sh
#
# Non-interactive (copy-paste all values):
#   APP_URL=https://billing.example.com \
#   DB_DATABASE=user_isp DB_USERNAME=user_isp DB_PASSWORD=secret \
#   ISP_ADMIN_EMAIL=admin@example.com ISP_ADMIN_PASSWORD=secret \
#   bash scripts/install-cpanel-webuzo.sh --yes
#
# From GitHub one-liner (clone + install):
#   curl -fsSL https://raw.githubusercontent.com/habib2500a1/ispbillling/main/install.sh | bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

# shellcheck source=scripts/detect-hosting.sh
source "$APP_ROOT/scripts/detect-hosting.sh"

AUTO_YES=false
SKIP_COMPOSER=false
for arg in "$@"; do
  case "$arg" in
    --yes|-y) AUTO_YES=true ;;
    --skip-composer) SKIP_COMPOSER=true ;;
    --help|-h)
      echo "Usage: bash scripts/install-cpanel-webuzo.sh [--yes]"
      echo "Env: APP_URL, DB_* , ISP_ADMIN_EMAIL, ISP_ADMIN_PASSWORD, PHP_BIN"
      exit 0
      ;;
  esac
done

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}==>${NC} $*"; }
warn()  { echo -e "${YELLOW}!!>${NC} $*"; }
fail()  { echo -e "${RED}ERR>${NC} $*" >&2; exit 1; }

prompt() {
  local var="$1" label="$2" default="${3:-}"
  if [[ "$AUTO_YES" == true ]] && [[ -n "${!var:-}" ]]; then
    return 0
  fi
  if [[ -n "$default" ]]; then
    read -r -p "$label [$default]: " val
    val="${val:-$default}"
  else
    read -r -p "$label: " val
  fi
  printf -v "$var" '%s' "$val"
}

# --- PHP binary ---
PHP_BIN="${PHP_BIN:-}"
if [[ -z "$PHP_BIN" ]]; then
  for candidate in php /usr/local/bin/ea-php83 /usr/local/bin/ea-php82 /usr/bin/php; do
    if command -v "$candidate" >/dev/null 2>&1; then
      ver="$("$candidate" -r 'echo PHP_VERSION;' 2>/dev/null || echo 0)"
      major="${ver%%.*}"
      minor="${ver#*.}"; minor="${minor%%.*}"
      if [[ "$major" -ge 8 && "$minor" -ge 2 ]] 2>/dev/null || [[ "$major" -ge 8 ]]; then
        PHP_BIN="$candidate"
        break
      fi
    fi
  done
fi
[[ -n "$PHP_BIN" ]] || fail "PHP 8.2+ not found. cPanel → Select PHP Version → 8.2 or 8.3"
export PHP_BIN

info "ISP Platform — cPanel/Webuzo one-click install"
info "App root: $APP_ROOT"
info "PHP: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"
info "User: $(whoami) | Hosting: $HOSTING_TYPE"

# --- PHP extensions ---
REQUIRED_EXTS=(pdo mbstring openssl tokenizer xml ctype json bcmath fileinfo)
MISSING=()
for ext in "${REQUIRED_EXTS[@]}"; do
  if ! "$PHP_BIN" -m 2>/dev/null | grep -qi "^${ext}$"; then
    MISSING+=("$ext")
  fi
done
if [[ ${#MISSING[@]} -gt 0 ]]; then
  warn "Missing PHP extensions: ${MISSING[*]}"
  warn "cPanel → Select PHP Version → Extensions → enable them, then re-run this script."
fi

# --- .env ---
if [[ ! -f .env ]]; then
  if [[ -f deploy/.env.cpanel.example ]]; then
    cp deploy/.env.cpanel.example .env
    info "Created .env from deploy/.env.cpanel.example"
  else
    cp .env.example .env
    info "Created .env from .env.example"
  fi
else
  warn ".env already exists — keeping it (only missing keys will be updated)"
fi

# --- Collect config ---
DEFAULT_URL="https://$(hostname -f 2>/dev/null || echo yourdomain.com)"
prompt APP_URL "APP_URL (https://your-billing-domain.com)" "${APP_URL:-$DEFAULT_URL}"
prompt DB_DATABASE "MySQL database name" "${DB_DATABASE:-}"
prompt DB_USERNAME "MySQL username" "${DB_USERNAME:-}"
prompt DB_PASSWORD "MySQL password" "${DB_PASSWORD:-}"
prompt ISP_ADMIN_EMAIL "Admin email (first login)" "${ISP_ADMIN_EMAIL:-admin@example.com}"
prompt ISP_ADMIN_PASSWORD "Admin password" "${ISP_ADMIN_PASSWORD:-}"

[[ -n "$APP_URL" ]] || fail "APP_URL is required"
[[ -n "$DB_DATABASE" ]] || fail "DB_DATABASE is required"
[[ -n "$DB_USERNAME" ]] || fail "DB_USERNAME is required"
[[ -n "$DB_PASSWORD" ]] || fail "DB_PASSWORD is required"

# Extract domain for session
DOMAIN="${APP_URL#https://}"
DOMAIN="${DOMAIN#http://}"
DOMAIN="${DOMAIN%%/*}"
SESSION_DOMAIN=".${DOMAIN#*.}"

set_env() {
  local key="$1" val="$2"
  if grep -q "^${key}=" .env 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${val}|" .env
  else
    echo "${key}=${val}" >> .env
  fi
}

set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "$APP_URL"
set_env ISP_BUNDLE_CSS true
set_env ISP_LANDING_DOMAIN "$DOMAIN"
set_env ISP_DEFAULT_TENANT_ID 1
set_env INVENTORY_SHOP_TENANT_ID 1
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_DATABASE"
set_env DB_USERNAME "$DB_USERNAME"
set_env DB_PASSWORD "$DB_PASSWORD"
set_env QUEUE_CONNECTION database
set_env CACHE_STORE database
set_env SESSION_DRIVER database
set_env SESSION_DOMAIN "$SESSION_DOMAIN"
set_env ISP_ADMIN_EMAIL "$ISP_ADMIN_EMAIL"
set_env ISP_ADMIN_PASSWORD "$ISP_ADMIN_PASSWORD"

info ".env configured"

# --- Composer ---
if [[ "$SKIP_COMPOSER" != true ]]; then
  info "Installing Composer dependencies..."
  if command -v composer >/dev/null 2>&1; then
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
  elif [[ -f composer.phar ]]; then
    COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" composer.phar install --no-dev --optimize-autoloader --no-interaction
  else
    info "Downloading composer.phar..."
    curl -sS https://getcomposer.org/installer | "$PHP_BIN"
    COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" composer.phar install --no-dev --optimize-autoloader --no-interaction
  fi
else
  warn "Skipped composer (--skip-composer)"
fi

# --- Laravel setup ---
info "Generating APP_KEY..."
"$PHP_BIN" artisan key:generate --force --no-interaction

info "Running migrations..."
"$PHP_BIN" artisan migrate --force --no-interaction

info "Post-deploy (admin, permissions, cache)..."
bash "$APP_ROOT/scripts/post-deploy-cpanel.sh"

info "Storage link..."
"$PHP_BIN" artisan storage:link --no-interaction 2>/dev/null || true

# --- Git auto-deploy hook ---
if [[ -d .git ]]; then
  DEPLOY_SCRIPT=post-deploy-cpanel.sh
  HOOK="$APP_ROOT/.git/hooks/post-merge"
  cat > "$HOOK" <<EOF
#!/bin/bash
cd "$APP_ROOT" || exit 0
nohup bash "$APP_ROOT/scripts/$DEPLOY_SCRIPT" >> "$APP_ROOT/storage/logs/git-post-merge-deploy.log" 2>&1 &
EOF
  chmod +x "$HOOK"
  info "Git post-merge hook installed (auto deploy after git pull)"
fi

# --- Cron instructions ---
CRON_PHP="$PHP_BIN"
echo ""
echo "=============================================="
echo -e "${GREEN}INSTALL COMPLETE${NC}"
echo "=============================================="
echo ""
echo "Admin panel: ${APP_URL}/admin"
echo "Login:       ${ISP_ADMIN_EMAIL}"
echo ""
echo "IMPORTANT — cPanel → Cron Jobs → add these 2 lines:"
echo ""
echo "# Laravel scheduler (every minute)"
echo "cd $APP_ROOT && $CRON_PHP artisan schedule:run >> /dev/null 2>&1"
echo ""
echo "# Queue worker (every minute — shared hosting)"
echo "cd $APP_ROOT && $CRON_PHP artisan queue:work database --stop-when-empty --max-time=55 >> $APP_ROOT/storage/logs/queue.log 2>&1"
echo ""
echo "Document root must point to: $APP_ROOT/public"
echo ""
echo "Verify:"
echo "  cd $APP_ROOT && $CRON_PHP artisan isp:production-audit --skip-tests"
echo ""
echo "Future updates (one command):"
echo "  cd $APP_ROOT && git pull && bash scripts/post-deploy-cpanel.sh"
echo "=============================================="
