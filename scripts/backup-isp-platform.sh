#!/usr/bin/env bash
# PostgreSQL dump + storage archive for ISP Platform.
# Usage: sudo -u www-data ./scripts/backup-isp-platform.sh [output_dir]
# Requires: pg_dump, .env at project root with DB_* or DATABASE_URL.

set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
OUT="${1:-${ROOT}/storage/app/backups}"
mkdir -p "$OUT"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$OUT/$STAMP"

if [[ ! -f .env ]]; then
  echo "Missing .env in $ROOT" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

DB_URL="${DATABASE_URL:-}"
if [[ -n "$DB_URL" ]]; then
  pg_dump "$DB_URL" -Fc -f "$OUT/$STAMP/database.dump"
else
  : "${DB_CONNECTION:?Set DB_CONNECTION or DATABASE_URL}"
  if [[ "$DB_CONNECTION" != "pgsql" ]]; then
    echo "This script only supports pgsql when DATABASE_URL is unset." >&2
    exit 1
  fi
  PGPASSWORD="${DB_PASSWORD:-}"
  export PGPASSWORD
  pg_dump -h "${DB_HOST:-127.0.0.1}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME}" -Fc "${DB_DATABASE}" -f "$OUT/$STAMP/database.dump"
fi

if [[ -d storage/app ]]; then
  tar -czf "$OUT/$STAMP/storage-app.tgz" -C storage app
fi

echo "Backup written to $OUT/$STAMP"
