#!/bin/sh
# Fix Laravel storage/bootstrap permissions on every container start (bind-mount safe).

cd /var/www/html 2>/dev/null || exit 0

mkdir -p \
  storage/app/public/company-branding \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache \
  public/css

if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data storage bootstrap/cache vendor public/css 2>/dev/null || true
fi
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
chmod -R a+rX public/css 2>/dev/null || true

# Root-owned compiled views break Blade with "Permission denied".
find storage/framework/views -type f ! -user www-data -delete 2>/dev/null || true
