#!/usr/bin/env bash
# One-shot wrapper — same as: php artisan isp:remove-legacy-portal "$@"
set -euo pipefail
cd "$(dirname "$0")/.."
php artisan isp:remove-legacy-portal "$@"
