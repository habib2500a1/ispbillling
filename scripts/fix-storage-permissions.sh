#!/usr/bin/env bash
# Run after deploy or any `php artisan` as root — prevents 500 "Permission denied" on views.
# Cron uses --quick (only fixes root-owned files, does not wipe all compiled views).
set -euo pipefail
APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WEB_USER="${WEB_USER:-www-data}"
QUICK=0
[[ "${1:-}" == "--quick" ]] && QUICK=1

chown -R "${WEB_USER}:${WEB_USER}" "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"
chmod -R ug+rwx "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"

ROOT_VIEWS=$(find "${APP_ROOT}/storage/framework/views" -type f -user root 2>/dev/null | wc -l || echo 0)

if [[ "${QUICK}" -eq 1 ]]; then
    if [[ "${ROOT_VIEWS}" -gt 0 ]]; then
        find "${APP_ROOT}/storage/framework/views" -type f -user root -delete 2>/dev/null || true
        sudo -u "${WEB_USER}" php "${APP_ROOT}/artisan" view:clear --quiet 2>/dev/null || true
    fi
else
    rm -rf "${APP_ROOT}/storage/framework/views/"[!.]* 2>/dev/null || true
    find "${APP_ROOT}/storage/framework/views" -type f ! -user "${WEB_USER}" -delete 2>/dev/null || true
    sudo -u "${WEB_USER}" php "${APP_ROOT}/artisan" view:clear --quiet 2>/dev/null || true
    if [[ "${ISP_SKIP_CONFIG_CACHE:-0}" != "1" ]]; then
        sudo -u "${WEB_USER}" php "${APP_ROOT}/artisan" config:cache --quiet 2>/dev/null || true
    fi
fi

if [[ -t 1 ]]; then
    echo "Storage permissions fixed for ${WEB_USER} (quick=${QUICK}, root_views_removed=${ROOT_VIEWS})."
fi
