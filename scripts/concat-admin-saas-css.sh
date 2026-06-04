#!/usr/bin/env bash
# Rebuild public/css/admin-saas.css from modular sources (optional single-file deploy).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/public/css/admin-saas.css"
MODULES=(
  01-tokens
  02-sidebar
  03-dashboard-widgets
  04-analytics-blocks
  05-mobile-dock
  06-hubs-pages
  07-tables-subscribers
  08-dashboard-ops
  09-forms-details
  10-filament-overrides
  11-subscriber-view-legacy
)

{
  echo "/**"
  echo " * Aurora ISP admin — bundled from public/css/admin/saas/"
  echo " * Edit modules there; run: ./scripts/concat-admin-saas-css.sh"
  echo " */"
  for name in "${MODULES[@]}"; do
    f="$ROOT/public/css/admin/saas/${name}.css"
    if [[ ! -f "$f" ]]; then
      echo "Missing $f — run ./scripts/split-admin-saas-css.sh first" >&2
      exit 1
    fi
    echo ""
    echo "/* --- ${name}.css --- */"
    cat "$f"
  done
  for extra in admin-utilities.css admin-responsive.css; do
    f="$ROOT/public/css/${extra}"
    if [[ -f "$f" ]]; then
      echo ""
      echo "/* --- ${extra} --- */"
      cat "$f"
    fi
  done
} > "$DEST"

echo "Wrote $(wc -l < "$DEST") lines to $DEST"
