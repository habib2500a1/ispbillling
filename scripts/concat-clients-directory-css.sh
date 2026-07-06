#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/public/css/clients-directory-pro.css"
MODULES=(01-page-shell 02-chrome-toolbar 03-table 04-due-page 05-vip-page 06-light-mode 07-page-extras 08-crm-v2 09-galaxy-glass)

for name in 01-page-shell 02-chrome-toolbar 03-table; do
  mod="$ROOT/public/css/admin/clients-directory/${name}.css"
  if [[ ! -f "$mod" ]] || [[ "$(wc -l < "$mod")" -lt 20 ]]; then
    echo "Module $mod missing or too small — edit public/css/clients-directory-pro.css directly or restore modules" >&2
    exit 1
  fi
done

{
  echo "/** Bundled from public/css/admin/clients-directory/ — run ./scripts/concat-clients-directory-css.sh */"
  for name in "${MODULES[@]}"; do
    echo ""
    echo "/* --- ${name}.css --- */"
    cat "$ROOT/public/css/admin/clients-directory/${name}.css"
  done
} > "$DEST"

echo "Wrote $(wc -l < "$DEST") lines to $DEST"
