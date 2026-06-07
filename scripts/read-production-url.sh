#!/usr/bin/env bash
# Production APP_URL — .env wins, else deploy/production.url
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [[ -f "$APP_ROOT/.env" ]]; then
  url="$(grep -E '^APP_URL=' "$APP_ROOT/.env" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")"
  if [[ -n "$url" ]]; then
    echo "${url%/}"
    exit 0
  fi
fi

if [[ -f "$APP_ROOT/deploy/production.url" ]]; then
  url="$(grep -v '^#' "$APP_ROOT/deploy/production.url" | head -1 | tr -d '[:space:]')"
  if [[ -n "$url" ]]; then
    echo "${url%/}"
    exit 0
  fi
fi

exit 1
