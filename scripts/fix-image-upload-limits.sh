#!/usr/bin/env bash
# Fix admin/customer image uploads (Filament FileUpload + Livewire).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> Livewire temp directory"
mkdir -p storage/app/livewire-tmp
chown -R www-data:www-data storage/app/livewire-tmp
chmod 775 storage/app/livewire-tmp

echo "==> Storage permissions"
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

PHP_INI="/etc/php/8.3/fpm/conf.d/99-isp-uploads.ini"
if [[ -d "$(dirname "$PHP_INI")" ]]; then
    cat > "$PHP_INI" <<'INI'
; ISP platform — logo, subscriber photo, payment proof uploads
upload_max_filesize = 12M
post_max_size = 16M
max_file_uploads = 30
INI
    echo "Wrote $PHP_INI"
    systemctl reload php8.3-fpm 2>/dev/null || service php8.3-fpm reload 2>/dev/null || true
fi

NGINX_SITE="/etc/nginx/sites-available/isp-platform"
if [[ -f "$NGINX_SITE" ]] && ! grep -q 'client_max_body_size' "$NGINX_SITE"; then
    sed -i '/charset utf-8;/a\    client_max_body_size 20M;' "$NGINX_SITE"
    nginx -t && systemctl reload nginx
    echo "Added client_max_body_size to nginx"
fi

echo "Done. upload_max=$(php -r 'echo ini_get("upload_max_filesize");') post_max=$(php -r 'echo ini_get("post_max_size");')"
