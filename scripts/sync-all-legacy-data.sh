#!/usr/bin/env bash
# Full legacy portal data sync (production-safe — no migrate:fresh).
set -euo pipefail
cd "$(dirname "$0")/.."
LOG="storage/logs/all-legacy-data-sync-$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG") 2>&1

echo "=== All legacy data sync started $(date -Iseconds) ==="

php artisan migrate --force

echo "--- Resellers + MAC links ---"
php artisan isp:import-legacy-portal-resellers --force

echo "--- Extras (SMS, collectors, app users, service invoices) ---"
php artisan isp:import-legacy-portal-extras --force

echo "--- Subscriber details (ONU MAC, network meta) ---"
php artisan isp:sync-legacy-portal-details --force

echo "--- ONU sync ---"
php artisan isp:legacy-portal-onu-sync || true

echo "--- Billing history refresh ---"
php artisan isp:import-legacy-portal-billing --force

echo "--- Collections + current due ---"
php artisan isp:sync-legacy-portal-collections
php artisan isp:sync-legacy-portal-current-billing

echo "--- Prices + package profiles ---"
php artisan isp:sync-package-profiles-from-legacy-portal
php artisan isp:sync-prices-from-legacy-portal --with-onu-details

echo "--- Align lifecycle, grace, reconcile ---"
php artisan isp:align-legacy-portal --skip-clients
php artisan isp:reconcile-imported-billing

echo "--- Audit ---"
php artisan isp:audit-legacy-portal-import

echo "=== Finished $(date -Iseconds) — log: $LOG ==="
