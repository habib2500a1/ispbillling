#!/usr/bin/env bash
# One-time server tuning: PostgreSQL connection limits + PHP OPcache (no data loss).
set -euo pipefail

DB_USER="${DB_USER:-isp_app}"

echo "==> PostgreSQL role limits for ${DB_USER}..."
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
ALTER ROLE ${DB_USER} SET idle_in_transaction_session_timeout = '60s';
ALTER ROLE ${DB_USER} SET statement_timeout = '180s';
SQL

echo "==> PHP OPcache (FPM)..."
sudo tee /etc/php/8.3/fpm/conf.d/99-isp-opcache.ini >/dev/null <<'INI'
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
INI

echo "==> PHP-FPM pool (ondemand — fewer idle DB connections)..."
sudo tee /etc/php/8.3/fpm/pool.d/zz-isp-performance.conf >/dev/null <<'INI'
[www]
pm = ondemand
pm.max_children = 12
pm.process_idle_timeout = 15s
pm.max_requests = 500
INI

echo "==> Nginx static cache snippet..."
SNIP=/etc/nginx/snippets/isp-static-cache.conf
sudo tee "$SNIP" >/dev/null <<'NGX'
location ~* \.(?:css|js|jpg|jpeg|gif|png|svg|ico|webp|woff2?|ttf|eot)$ {
    expires 30d;
    add_header Cache-Control "public, immutable";
    access_log off;
    try_files $uri =404;
}
NGX

NGINX_SITE=/etc/nginx/sites-available/isp-platform
if ! grep -q 'isp-static-cache' "$NGINX_SITE" 2>/dev/null; then
  sudo sed -i '/location \/ {/i\    include snippets/isp-static-cache.conf;' "$NGINX_SITE" || true
fi

echo "==> Reload services..."
sudo systemctl reload php8.3-fpm
sudo nginx -t && sudo systemctl reload nginx

echo "Done. Run scripts/production-optimize.sh from the app directory after each deploy."
