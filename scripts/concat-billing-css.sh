#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/public/css/billing-pro.css"
MIN_LINES=20
for f in 01-tokens 02-hub-dashboard 03-invoice-list 04-invoice-detail 05-notices 06-sidebar 07-mobile 08-light-mode 09-dark-mode 10-hub-v3 11-collection-desk-v3; do
  mod="$ROOT/public/css/admin/billing/${f}.css"
  if [[ ! -f "$mod" ]] || [[ "$(wc -l < "$mod")" -lt "$MIN_LINES" ]]; then
    echo "Module $mod missing or too small" >&2
    exit 1
  fi
done
{
  echo "/** Billing & invoices — bundled from public/css/admin/billing/ */"
  for f in 01-tokens 02-hub-dashboard 03-invoice-list 04-invoice-detail 05-notices 06-sidebar 07-mobile 08-light-mode 09-dark-mode 10-hub-v3 11-collection-desk-v3; do
    echo ""
    echo "/* --- ${f}.css --- */"
    cat "$ROOT/public/css/admin/billing/${f}.css"
  done
} > "$DEST"
echo "Wrote $(wc -l < "$DEST") lines"
