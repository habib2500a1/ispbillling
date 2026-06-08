#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/public/css/network-pro.css"
MIN_LINES=20
for f in 01-tokens 02-hub-dashboard 03-routers-list 04-router-profile 05-online-clients 06-bandwidth-monitor 07-monitoring-pages 08-settings-import 09-sidebar 10-light-dark-mobile; do
  mod="$ROOT/public/css/admin/network/${f}.css"
  if [[ ! -f "$mod" ]] || [[ "$(wc -l < "$mod")" -lt "$MIN_LINES" ]]; then
    echo "Module $mod missing or too small" >&2
    exit 1
  fi
done
{
  echo "/** Router / MikroTik NOC — bundled from public/css/admin/network/ */"
  for f in 01-tokens 02-hub-dashboard 03-routers-list 04-router-profile 05-online-clients 06-bandwidth-monitor 07-monitoring-pages 08-settings-import 09-sidebar 10-light-dark-mobile; do
    echo ""
    echo "/* --- ${f}.css --- */"
    cat "$ROOT/public/css/admin/network/${f}.css"
  done
} > "$DEST"
echo "Wrote $(wc -l < "$DEST") lines"
