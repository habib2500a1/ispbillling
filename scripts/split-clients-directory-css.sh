#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/public/css/clients-directory-pro.css"
DEST="$ROOT/public/css/admin/clients-directory"

[[ -f "$SRC" ]] || { echo "Missing $SRC" >&2; exit 1; }
mkdir -p "$DEST"

split_range() {
  local out="$1" start="$2" end="$3" title="$4"
  {
    echo "/** Clients directory — $title */"
    sed -n "${start},${end}p" "$SRC"
  } > "$DEST/$out"
}

split_range "01-page-shell.css" 1 78 "Page shell & tokens"
split_range "02-chrome-toolbar.css" 79 319 "Toolbar, stats, tabs"
split_range "03-table.css" 320 947 "Table layout & actions"
split_range "04-due-page.css" 948 1122 "Due clients variant"
split_range "05-vip-page.css" 1123 1299 "VIP clients variant"

echo "Split into $(ls -1 "$DEST"/*.css | wc -l) modules"
