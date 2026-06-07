#!/usr/bin/env bash
# Build cPanel/Webuzo ZIP packages with vendor + web setup wizard.
#
# Usage:
#   bash scripts/build-cpanel-release-zip.sh
#
# Output:
#   releases/isp-platform-cpanel-public_html.zip  (unzip in /home/user/)
#   releases/isp-platform-cpanel-full.zip         (standard public/ layout)
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

RELEASE_DIR="$APP_ROOT/releases"
PUBLIC_STAGING="$RELEASE_DIR/.build-public_html"
FULL_STAGING="$RELEASE_DIR/.build-full"

PHP_BIN="${PHP_BIN:-php}"

info() { echo "==> $*"; }

info "Installing production Composer dependencies..."
if command -v composer >/dev/null 2>&1; then
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
else
  COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" composer.phar install --no-dev --optimize-autoloader --no-interaction
fi

mkdir -p "$RELEASE_DIR"
rm -rf "$PUBLIC_STAGING" "$FULL_STAGING"
mkdir -p "$PUBLIC_STAGING/isp-app" "$PUBLIC_STAGING/public_html" "$FULL_STAGING/isp-platform"

RSYNC_EXCLUDES=(
  --exclude '.git'
  --exclude '.cursor'
  --exclude 'node_modules'
  --exclude 'releases'
  --exclude 'tests'
  --exclude '.env'
  --exclude 'storage/logs/*'
  --exclude 'storage/framework/cache/data/*'
  --exclude 'storage/framework/sessions/*'
  --exclude 'storage/framework/views/*'
)

copy_app() {
  local dest="$1"
  rsync -a "${RSYNC_EXCLUDES[@]}" "$APP_ROOT/" "$dest/"
  mkdir -p "$dest/storage/logs" "$dest/storage/framework/cache" "$dest/storage/framework/sessions" "$dest/storage/framework/views"
  touch "$dest/storage/logs/.gitkeep"

  if [[ -f deploy/.env.cpanel.example ]]; then
    cp deploy/.env.cpanel.example "$dest/.env"
  else
    cp .env.example "$dest/.env"
  fi

  # Fresh install — wizard will generate key + mark installed
  sed -i 's/^APP_KEY=.*/APP_KEY=/' "$dest/.env" 2>/dev/null || true
  rm -f "$dest/storage/framework/.app-installed" 2>/dev/null || true
  rm -f "$dest/storage/framework/deploy-ready" 2>/dev/null || true
}

info "Staging public_html package..."
copy_app "$PUBLIC_STAGING/isp-app"
rsync -a "$APP_ROOT/public/" "$PUBLIC_STAGING/public_html/"
cp "$APP_ROOT/deploy/public_html/index.php" "$PUBLIC_STAGING/public_html/index.php"
cp "$APP_ROOT/deploy/README-UNZIP.txt" "$PUBLIC_STAGING/README-UNZIP.txt"

info "Staging full package..."
copy_app "$FULL_STAGING/isp-platform"
rsync -a "$APP_ROOT/public/" "$FULL_STAGING/isp-platform/public/"
cp "$APP_ROOT/deploy/README-UNZIP.txt" "$FULL_STAGING/README-UNZIP.txt"

chmod -R ug+rwX "$PUBLIC_STAGING/isp-app/storage" "$PUBLIC_STAGING/isp-app/bootstrap/cache" 2>/dev/null || true
chmod -R ug+rwX "$FULL_STAGING/isp-platform/storage" "$FULL_STAGING/isp-platform/bootstrap/cache" 2>/dev/null || true

PUBLIC_ZIP="$RELEASE_DIR/isp-platform-cpanel-public_html.zip"
FULL_ZIP="$RELEASE_DIR/isp-platform-cpanel-full.zip"

info "Creating $PUBLIC_ZIP"
( cd "$PUBLIC_STAGING" && zip -qr "$PUBLIC_ZIP" . )

info "Creating $FULL_ZIP"
( cd "$FULL_STAGING" && zip -qr "$FULL_ZIP" . )

rm -rf "$PUBLIC_STAGING" "$FULL_STAGING"

PUBLIC_SIZE="$(du -h "$PUBLIC_ZIP" | awk '{print $1}')"
FULL_SIZE="$(du -h "$FULL_ZIP" | awk '{print $1}')"

info "Done."
echo ""
echo "ZIP packages:"
echo "  $PUBLIC_ZIP  ($PUBLIC_SIZE)  → unzip in /home/user/ (public_html + isp-app)"
echo "  $FULL_ZIP  ($FULL_SIZE)  → unzip in /home/user/, docroot = isp-platform/public"
echo ""
echo "GitHub Release:"
echo "  ./scripts/github-release-cpanel-zip.sh"
echo "  or: Actions → cPanel Release ZIP"
echo ""
echo "After unzip open: https://your-domain.com/install"
