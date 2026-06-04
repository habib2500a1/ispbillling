#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/public/css/subscriber-view-pro.css"
MIN_LINES=400
for f in 01-shell-hero 02-panels-cards 03-network-diagnostics 04-contact-location; do
  mod="$ROOT/public/css/admin/subscriber-view/${f}.css"
  if [[ ! -f "$mod" ]] || [[ "$(wc -l < "$mod")" -lt 50 ]]; then
    echo "Module $mod missing or too small — run split-subscriber-view-css.sh from a full bundle first" >&2
    exit 1
  fi
done
{
  echo "/** Subscriber 360 — bundled from public/css/admin/subscriber-view/ */"
  for f in 01-shell-hero 02-panels-cards 03-network-diagnostics 04-contact-location; do
    echo ""
    echo "/* --- ${f}.css --- */"
    cat "$ROOT/public/css/admin/subscriber-view/${f}.css"
  done
} > "$DEST"
echo "Wrote $(wc -l < "$DEST") lines"
