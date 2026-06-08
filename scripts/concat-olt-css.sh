#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/public/css/olt-pro.css"
MIN_LINES=15
for f in 01-tokens 02-hub-operations 03-optical-noc-shell 04-signal-quality 05-pon-faults 06-olt-list-profile 07-topology-insights 08-mobile 09-light-dark; do
  mod="$ROOT/public/css/admin/olt/${f}.css"
  if [[ ! -f "$mod" ]] || [[ "$(wc -l < "$mod")" -lt "$MIN_LINES" ]]; then
    echo "Module $mod missing or too small" >&2
    exit 1
  fi
done
{
  echo "/** OLT / GPON operations — bundled */"
  for f in 01-tokens 02-hub-operations 03-optical-noc-shell 04-signal-quality 05-pon-faults 06-olt-list-profile 07-topology-insights 08-mobile 09-light-dark; do
    echo ""
    echo "/* --- ${f}.css --- */"
    cat "$ROOT/public/css/admin/olt/${f}.css"
  done
  for leg in olt-hub-pro.css optical-noc.css; do
    if [[ -f "$ROOT/public/css/$leg" ]]; then
      echo ""
      echo "/* --- $leg (legacy) --- */"
      cat "$ROOT/public/css/$leg"
    fi
  done
} > "$DEST"
echo "Wrote $(wc -l < "$DEST") lines"
