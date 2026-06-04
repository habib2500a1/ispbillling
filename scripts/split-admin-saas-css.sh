#!/usr/bin/env bash
# Split legacy monolithic admin-saas.css into public/css/admin/saas/ modules.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/public/css/admin-saas.css"
DEST="$ROOT/public/css/admin/saas"

if [[ ! -f "$SRC" ]]; then
  echo "Missing $SRC" >&2
  exit 1
fi

mkdir -p "$DEST"

split_range() {
  local out="$1" start="$2" end="$3" title="$4"
  {
    echo "/**"
    echo " * Aurora ISP admin — $title"
    echo " * @module admin/saas/$(basename "$out")"
    echo " */"
    sed -n "${start},${end}p" "$SRC"
  } > "$DEST/$out"
}

# Line ranges from section map (1-indexed, inclusive)
split_range "01-tokens.css" 1 56 "Design tokens"
split_range "02-sidebar.css" 57 749 "Sidebar & shell layout"
split_range "03-dashboard-widgets.css" 750 1794 "Dashboard widgets & welcome"
split_range "04-analytics-blocks.css" 1795 2959 "Analytics, metering & settlement UI"
split_range "05-mobile-dock.css" 2960 3601 "Mobile dock & hub link cards"
split_range "06-hubs-pages.css" 3602 4541 "Hub pages, billing desk, HR, quick actions"
split_range "07-tables-subscribers.css" 4542 4800 "Subscriber tables & sticky actions"
split_range "08-dashboard-ops.css" 4801 6320 "NOC, ops wall, WAN, production UX"
split_range "09-forms-details.css" 6321 8009 "Forms, client details, role matrix"
split_range "10-filament-overrides.css" 8010 10068 "Filament field & form overrides"
split_range "11-subscriber-view-legacy.css" 10069 10939 "Subscriber 360 & billing notices"

echo "Split $(wc -l < "$SRC") lines into $(ls -1 "$DEST"/*.css | wc -l) modules under $DEST"
