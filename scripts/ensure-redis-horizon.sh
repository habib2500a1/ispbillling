#!/usr/bin/env bash
# Ensure Redis queue settings and Laravel Horizon service on production VPS.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"
ENV_FILE="${ENV_FILE:-$APP_ROOT/.env}"

set_env_default() {
  local key="$1"
  local value="$2"
  if [[ ! -f "$ENV_FILE" ]]; then
    return
  fi
  if grep -qE "^${key}=" "$ENV_FILE"; then
    return
  fi
  echo "${key}=${value}" >> "$ENV_FILE"
  echo "[redis] Added ${key}=${value}"
}

if command -v redis-cli >/dev/null 2>&1; then
  if redis-cli ping >/dev/null 2>&1; then
    echo "[redis] Redis OK"
  else
    echo "[redis] WARN: redis-cli found but ping failed — start redis-server"
  fi
else
  echo "[redis] WARN: redis-cli not found — install redis-server for queues"
fi

set_env_default QUEUE_CONNECTION redis
set_env_default CACHE_STORE redis
set_env_default QUEUE_HEAVY_JOBS_ENABLED true

# Docker compose uses service hostname `redis`; bare VPS uses 127.0.0.1
REDIS_DEFAULT=127.0.0.1
if grep -qE '^DB_HOST=postgres' "$ENV_FILE" 2>/dev/null; then
  REDIS_DEFAULT=redis
fi
set_env_default REDIS_HOST "$REDIS_DEFAULT"
set_env_default REDIS_PORT 6379

UNIT_SRC="$APP_ROOT/deploy/laravel-horizon.service.example"
UNIT_DST=/etc/systemd/system/laravel-horizon.service

if [[ -f "$UNIT_SRC" ]] && [[ ! -f "$UNIT_DST" ]]; then
  echo "[redis] Installing Horizon systemd unit..."
  sed "s|/var/www/isp-platform|$APP_ROOT|g" "$UNIT_SRC" | sudo tee "$UNIT_DST" >/dev/null
  sudo systemctl daemon-reload
  sudo systemctl enable laravel-horizon
  sudo systemctl start laravel-horizon
  echo "[redis] Horizon started"
elif systemctl is-active --quiet laravel-horizon 2>/dev/null; then
  echo "[redis] Horizon already running"
elif [[ -f "$UNIT_DST" ]]; then
  sudo systemctl start laravel-horizon 2>/dev/null || echo "[redis] WARN: could not start laravel-horizon"
else
  echo "[redis] Horizon unit not installed — run: sudo cp deploy/laravel-horizon.service.example /etc/systemd/system/laravel-horizon.service"
fi
