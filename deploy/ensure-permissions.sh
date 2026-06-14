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
  public/css \
  public/downloads

if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data storage bootstrap/cache vendor public/css public/downloads 2>/dev/null || true
fi
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
# setgid on cache dirs so files created by any user inherit the www-data group;
# combined with group-write this lets PHP-FPM overwrite compiled views/caches
# even if a root-run `php artisan` created them, avoiding "Permission denied".
find storage/framework bootstrap/cache -type d -exec chmod g+s {} + 2>/dev/null || true
chmod -R a+rX public/css 2>/dev/null || true
chmod 755 public/downloads 2>/dev/null || true
chmod 644 public/downloads/*.apk public/downloads/*.json 2>/dev/null || true

# Root-owned compiled views/cache break Blade with "Permission denied".
find storage/framework/views -type f ! -user www-data -delete 2>/dev/null || true
find storage/framework/cache -type f ! -user www-data -exec chown www-data:www-data {} \; 2>/dev/null || true
find storage/framework/sessions -type f ! -user www-data -exec chown www-data:www-data {} \; 2>/dev/null || true
find storage/logs -type f ! -user www-data -exec chown www-data:www-data {} \; 2>/dev/null || true
find bootstrap/cache -type f ! -user www-data -exec chown www-data:www-data {} \; 2>/dev/null || true

# Manual `php artisan` as root leaves .env unreadable to PHP-FPM (www-data).
if [ -f .env ] && id www-data >/dev/null 2>&1; then
  chown www-data:www-data .env 2>/dev/null || true
  chmod 640 .env 2>/dev/null || true
fi
