#!/usr/bin/env bash
# Enable full GIS enterprise stack: PostGIS, vector tiles, geom sync, optional Soketi.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Pulling GIS images (postgis, pg_tileserv, soketi)…"
docker compose -p ispbilling pull postgres pg_tileserv soketi 2>/dev/null || true

echo "==> Recreating Postgres with PostGIS image (data volume preserved)…"
docker compose -p ispbilling up -d postgres --force-recreate --no-build

echo "==> Waiting for Postgres…"
for i in $(seq 1 40); do
  if docker compose -p ispbilling exec -T postgres pg_isready -U "${POSTGRES_USER:-isp}" -d "${POSTGRES_DB:-isp_platform}" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

echo "==> Enabling PostGIS + geom columns…"
docker compose -p ispbilling exec -T app php artisan isp:enable-postgis || true

echo "==> Starting vector tile server + Soketi WebSocket…"
docker compose -p ispbilling up -d pg_tileserv soketi --no-build

echo "==> Reloading nginx (tile proxy /ws)…"
docker compose -p ispbilling up -d nginx --force-recreate --no-build 2>/dev/null || docker compose -p ispbilling restart nginx 2>/dev/null || true

echo "==> Caching config…"
docker compose -p ispbilling exec -T app php artisan config:cache

echo ""
echo "GIS stack enabled."
echo "  • PostGIS:     docker compose exec app php artisan isp:sync-gis-geom"
echo "  • Vector MVT:  https://YOUR_DOMAIN/gis/tiles/public.gis_mvt_customers/{z}/{x}/{y}.pbf"
echo "  • WebSocket:   set BROADCAST_CONNECTION=pusher + PUSHER_* in .env, then config:cache"
echo "  • Map UI:      /admin/fiber-plant-map"
