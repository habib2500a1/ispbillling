#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/public/css/support-pro.css"
MIN_LINES=20
for f in 01-tokens 02-hub-dashboard 03-ticket-list 04-ticket-detail 05-timeline 07-gis-preview 06-sidebar 08-light-mode 09-dark-mode 10-hub-v3 11-ticket-create; do
  mod="$ROOT/public/css/admin/support/${f}.css"
  if [[ ! -f "$mod" ]] || [[ "$(wc -l < "$mod")" -lt "$MIN_LINES" ]]; then
    echo "Module $mod missing or too small" >&2
    exit 1
  fi
done
{
  echo "/** Support & tickets — bundled from public/css/admin/support/ */"
  for f in 01-tokens 02-hub-dashboard 03-ticket-list 04-ticket-detail 05-timeline 07-gis-preview 06-sidebar 08-light-mode 09-dark-mode 10-hub-v3 11-ticket-create; do
    echo ""
    echo "/* --- ${f}.css --- */"
    cat "$ROOT/public/css/admin/support/${f}.css"
  done
} > "$DEST"
echo "Wrote $(wc -l < "$DEST") lines"
