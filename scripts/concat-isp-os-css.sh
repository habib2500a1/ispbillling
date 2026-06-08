#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/public/css/isp-os-pro.css"
{
  for f in "$ROOT"/public/css/admin/isp-os/*.css; do
    echo "/* $(basename "$f") */"
    cat "$f"
    echo
  done
} > "$OUT"
echo "Wrote $(wc -l < "$OUT") lines"
