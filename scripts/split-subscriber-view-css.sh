#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/public/css/subscriber-view-pro.css"
DEST="$ROOT/public/css/admin/subscriber-view"
[[ -f "$SRC" ]] || { echo "Missing $SRC" >&2; exit 1; }
mkdir -p "$DEST"
split_range() {
  { echo "/** Subscriber 360 — $3 */"; sed -n "${2},${4}p" "$SRC"; } > "$DEST/$1"
}
split_range "01-shell-hero.css" 1 1 397 "Shell, hero, KPIs"
split_range "02-panels-cards.css" 2 398 765 "Panels & cards"
split_range "03-network-diagnostics.css" 3 766 1017 "Network / ONU diagnostics"
split_range "04-contact-location.css" 4 1018 99999 "Contact, map, tables"
echo "Split subscriber-view into $(ls -1 "$DEST"/*.css | wc -l) modules"
