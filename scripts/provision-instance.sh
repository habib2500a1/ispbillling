#!/usr/bin/env bash
# Create a new isolated ISP instance (separate path + .env + database name).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ID=""
APP_URL=""
INSTANCE_PATH=""
DB_NAME=""
BRANCH="main"

usage() {
  cat <<'EOF'
Usage:
  provision-instance.sh --id=client1 --url=https://billing.client1.com --path=/var/www/instances/billing.client1.com --db=isp_client1

Creates directory, clones repo, copies deploy/.env.nextdeploy.example → .env, syncs URL.
Then run migrations manually:
  cd /var/www/instances/... && php artisan migrate --force && php artisan isp:post-deploy
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --id=*) ID="${1#*=}" ;;
    --url=*) APP_URL="${1#*=}" ;;
    --path=*) INSTANCE_PATH="${1#*=}" ;;
    --db=*) DB_NAME="${1#*=}" ;;
    --branch=*) BRANCH="${1#*=}" ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
  esac
  shift
done

if [[ -z "$ID" || -z "$APP_URL" || -z "$INSTANCE_PATH" ]]; then
  usage
  exit 1
fi

HOST="$(php -r 'echo parse_url(getenv("U"), PHP_URL_HOST) ?: "";' U="$APP_URL")"
if [[ -z "$HOST" ]]; then
  echo "Invalid --url: $APP_URL" >&2
  exit 1
fi

if [[ -e "$INSTANCE_PATH" ]]; then
  echo "ERROR: path already exists: $INSTANCE_PATH" >&2
  exit 1
fi

mkdir -p "$(dirname "$INSTANCE_PATH")"
git clone "$APP_ROOT" "$INSTANCE_PATH"
cd "$INSTANCE_PATH"
git checkout "$BRANCH" 2>/dev/null || true

cp deploy/.env.nextdeploy.example .env
php artisan key:generate --force --no-interaction

if [[ -n "$DB_NAME" ]]; then
  sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
fi

bash "$APP_ROOT/scripts/sync-instance-url.sh" --path="$INSTANCE_PATH" --url="$APP_URL" --landing="$HOST"

echo ""
echo "Instance [$ID] provisioned at $INSTANCE_PATH"
echo "Next:"
echo "  1) Create PostgreSQL database: ${DB_NAME:-isp_${ID}}"
echo "  2) Edit .env DB_* credentials"
echo "  3) cd $INSTANCE_PATH && php artisan migrate --force && php artisan isp:post-deploy"
echo "  4) Add deploy/instances.json entry and point DNS/nginx to $HOST"
