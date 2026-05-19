#!/usr/bin/env bash
# Quick health: DB connections + stuck schedulers (exit 1 if unhealthy).
set -euo pipefail
MAX_DB="${MAX_DB_CONNECTIONS:-40}"
MAX_PROC="${MAX_AUTO_PROCESSES:-2}"

DB_COUNT=$(sudo -u postgres psql -tAc "SELECT count(*) FROM pg_stat_activity WHERE usename='isp_app';" 2>/dev/null | tr -d '[:space:]' || echo 999)
PROC_COUNT=$(pgrep -fc 'isp:run-automatic-processes' 2>/dev/null || true)
PROC_COUNT=${PROC_COUNT:-0}

echo "DB connections (isp_app): ${DB_COUNT} / max ${MAX_DB}"
echo "isp:run-automatic-processes: ${PROC_COUNT} / max ${MAX_PROC}"

if [[ "$DB_COUNT" -gt "$MAX_DB" ]]; then
  echo "UNHEALTHY: too many DB connections"
  exit 1
fi
if [[ "$PROC_COUNT" -gt "$MAX_PROC" ]]; then
  echo "UNHEALTHY: too many automatic process workers"
  exit 1
fi

echo "OK"
exit 0
