#!/usr/bin/env bash
# demo.anetbd.com — একবার চালান (আলাদা DB + ISP_DEMO_MODE=true থাকা app এ)
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

echo "==> ISP Platform demo setup $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "    APP_URL should be https://demo.anetbd.com"
echo "    Production DB এ চালাবেন না — আলাদা database বাধ্যতামূলক।"
echo ""

if [[ ! -f .env ]]; then
  echo "ERROR: .env নেই। deploy/.env.demo.example copy করে .env বানান।"
  exit 1
fi

if ! grep -qE '^ISP_DEMO_MODE=true' .env 2>/dev/null; then
  echo "ERROR: .env এ ISP_DEMO_MODE=true সেট করুন।"
  exit 1
fi

if grep -qE '^APP_URL=https?://anetbd\.com' .env 2>/dev/null \
  && ! grep -qE '^APP_URL=https?://demo\.anetbd\.com' .env 2>/dev/null; then
  echo "WARNING: APP_URL production domain দেখাচ্ছে। demo.anetbd.com সেট করুন।"
fi

FRESH=0
if [[ "${1:-}" == "--fresh" ]]; then
  FRESH=1
fi

if [[ $FRESH -eq 1 ]]; then
  if [[ -f storage/.production-live ]]; then
    echo "ERROR: migrate:fresh blocked — production-live marker আছে।"
    exit 1
  fi
  echo "==> migrate:fresh (সব ডেমো ডাটা মুছে নতুন)..."
  php artisan migrate:fresh --force --no-interaction
fi

echo "==> Demo data seed (admin, network, sample subscribers)..."
php artisan isp:demo-setup --no-interaction

echo "==> Cache clear..."
php artisan config:clear --no-interaction
php artisan route:clear --no-interaction
php artisan view:clear --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction

ADMIN_EMAIL="$(grep -E '^ISP_ADMIN_EMAIL=' .env | tail -1 | cut -d= -f2- | tr -d '"' || true)"
echo ""
BASE="$(grep -E '^APP_URL=' .env | tail -1 | cut -d= -f2- | tr -d '"' || echo 'https://demo.anetbd.com')"
echo "==> Full demo website ready (fake data)"
echo "    Landing:   ${BASE}/"
echo "    Sign in:   ${BASE}/login"
echo "    Portal:    ${BASE}/portal/login  (DEMO-001 / demo123)"
echo "    Reseller:  ${BASE}/reseller/login (DEMO-RSL / demo123)"
echo "    Pay bill:  ${BASE}/pay (code DEMO-001)"
echo "    Shop:      ${BASE}/shop"
echo "    Admin:     ${BASE}/admin"
echo "    Email:     ${ADMIN_EMAIL:-demo@anetbd.com}"
echo "    Pass:      (ISP_ADMIN_PASSWORD from .env)"
echo ""
echo "DNS: demo.anetbd.com → same server IP as anetbd.com"
echo "NextDeploy: আলাদা app, nginx:80, deploy/.env.demo.example"
