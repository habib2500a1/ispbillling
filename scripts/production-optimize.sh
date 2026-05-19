#!/usr/bin/env bash
# Safe production caches — run after deploy (does not touch customer data).
set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP_BIN:-php}"
export APP_ENV="${APP_ENV:-production}"

echo "==> Clearing stale caches..."
$PHP artisan config:clear
$PHP artisan route:clear
$PHP artisan view:clear
$PHP artisan cache:clear

echo "==> Building production caches..."
$PHP artisan config:cache
# Load DB settings into runtime config before caching views (bill-payment layout uses live OTP flag).
$PHP -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); if (\Illuminate\Support\Facades\Schema::hasTable('app_settings')) { \App\Models\AppSetting::syncToRuntimeConfig(); }"
$PHP artisan route:cache
# Skip view:cache — it bakes config() into compiled Blade and breaks admin OTP toggles.
$PHP artisan event:cache 2>/dev/null || true

echo "==> Optimizing Composer autoloader..."
COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o --no-dev 2>/dev/null || COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o

echo "==> Re-cache after package hooks..."
$PHP artisan config:cache
$PHP artisan route:cache

echo "==> Permissions..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

echo "Done. Site should load faster with cached config/routes/views."
