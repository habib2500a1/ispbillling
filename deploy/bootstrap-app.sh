#!/bin/sh
# Docker app container: zero-touch bootstrap (DB, admin, storage, branding, optimize).

if [ -z "$APP_KEY" ]; then
  echo "[bootstrap] Skipping: APP_KEY not set in Environment"
  exit 0
fi

rm -f storage/framework/deploy-ready 2>/dev/null || true
touch storage/framework/deploy-bootstrapping 2>/dev/null || true
chown www-data:www-data storage/framework/deploy-bootstrapping 2>/dev/null || true

attempt=0
while [ $attempt -lt 12 ]; do
  if php artisan migrate:status --no-ansi >/dev/null 2>&1; then
    break
  fi
  attempt=$((attempt + 1))
  sleep 1
done

if ! php artisan migrate:status --no-ansi >/dev/null 2>&1; then
  echo "[bootstrap] WARNING: database not reachable"
  exit 0
fi

echo "[bootstrap] Running migrations..."
php artisan migrate --force --no-interaction || {
  echo "[bootstrap] WARNING: migrate failed"
  exit 0
}

echo "[bootstrap] Fast post-deploy (admin, .env defaults, SMS templates)..."
php artisan isp:post-deploy --skip-migrate --fast --no-interaction || true

echo "[bootstrap] Automatic processes sync (background)..."
php artisan isp:post-deploy --processes-only --no-interaction >>storage/logs/post-deploy-processes.log 2>&1 &

if [ -f /usr/local/bin/ensure-permissions.sh ]; then
  /usr/local/bin/ensure-permissions.sh || true
fi

if [ -L public/storage ] && [ ! -e public/storage ]; then
  rm -f public/storage
fi
if [ ! -e public/storage ]; then
  php artisan storage:link --no-interaction 2>/dev/null || true
fi

BRANDING_DIR="storage/app/public/company-branding"
if [ -d /var/www/html/deploy/branding ]; then
  HAS_LOGO=0
  for f in "$BRANDING_DIR"/*; do
    case "$f" in
      *.png|*.jpg|*.jpeg|*.webp|*.gif) HAS_LOGO=1; break ;;
    esac
  done
  if [ "$HAS_LOGO" = "0" ]; then
    echo "[bootstrap] Seeding default company branding (no logo in storage)..."
    cp -n /var/www/html/deploy/branding/company-logo.png "$BRANDING_DIR"/company-logo.png 2>/dev/null || true
    cp -n /var/www/html/deploy/branding/favicon-32.png "$BRANDING_DIR"/favicon-32.png 2>/dev/null || true
    chown -R www-data:www-data "$BRANDING_DIR" 2>/dev/null || true
  fi
fi

if [ -f .env ] && ! grep -q '^ISP_SUPPORT_WEBHOOK_SECRET=.' .env 2>/dev/null; then
  echo "[bootstrap] Generating webhook secrets in .env (first run)..."
  php artisan isp:generate-webhook-secrets --write --no-interaction 2>/dev/null || true
fi

if [ -f /usr/local/bin/optimize-app.sh ]; then
  /usr/local/bin/optimize-app.sh || true
fi

php artisan isp:mark-deploy-ready --no-interaction 2>/dev/null || touch storage/framework/deploy-ready
rm -f storage/framework/deploy-bootstrapping 2>/dev/null || true
chown www-data:www-data storage/framework/deploy-ready 2>/dev/null || true
echo "[bootstrap] Deploy ready."
