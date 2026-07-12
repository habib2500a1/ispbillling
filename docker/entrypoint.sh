#!/bin/bash
set -e

cd /var/www/html

if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
  else
    touch .env
  fi
fi

# Apply common Docker defaults when not already set in the environment / .env
set_env_default() {
  local key="$1"
  local value="$2"
  if ! grep -q "^${key}=" .env 2>/dev/null || [ -z "$(grep "^${key}=" .env | cut -d= -f2-)" ]; then
    if grep -q "^${key}=" .env 2>/dev/null; then
      sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
      echo "${key}=${value}" >> .env
    fi
  fi
}

# Prefer container env vars (NextDeploy Environment tab) over file defaults
for var in APP_NAME APP_ENV APP_KEY APP_DEBUG APP_URL APP_TIMEZONE \
           DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
           SESSION_DRIVER QUEUE_CONNECTION CACHE_STORE LOG_CHANNEL \
           MFS_SMS_TOKEN; do
  if [ -n "${!var}" ]; then
    if grep -q "^${var}=" .env 2>/dev/null; then
      sed -i "s|^${var}=.*|${var}=${!var}|" .env
    else
      echo "${var}=${!var}" >> .env
    fi
  fi
done

set_env_default DB_CONNECTION mysql
set_env_default DB_HOST mysql
set_env_default DB_PORT 3306
set_env_default DB_DATABASE mikrotik_billing
set_env_default DB_USERNAME billing
set_env_default DB_PASSWORD secret
set_env_default SESSION_DRIVER database
set_env_default QUEUE_CONNECTION database
set_env_default CACHE_STORE file
set_env_default APP_ENV production
set_env_default APP_DEBUG false

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force --no-interaction || true
fi

echo "Waiting for MySQL..."
ATTEMPTS=0
until php -r "
\$h=getenv('DB_HOST')?:'mysql';
\$p=(int)(getenv('DB_PORT')?:3306);
\$u=getenv('DB_USERNAME')?:'billing';
\$pw=getenv('DB_PASSWORD')?:'secret';
\$d=getenv('DB_DATABASE')?:'mikrotik_billing';
try {
  new PDO(\"mysql:host=\$h;port=\$p;dbname=\$d\", \$u, \$pw);
  exit(0);
} catch (Throwable \$e) {
  exit(1);
}
" 2>/dev/null; do
  ATTEMPTS=$((ATTEMPTS + 1))
  if [ "$ATTEMPTS" -ge 60 ]; then
    echo "MySQL not ready after 60s — continuing anyway"
    break
  fi
  sleep 1
done

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

php artisan storage:link --force --no-interaction 2>/dev/null || true
php artisan migrate --force --no-interaction || true

# First-boot admin only
if php -r "try { \$p=new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'3306').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(((int)\$p->query('select count(*) from users')->fetchColumn())===0?0:1);} catch(Throwable \$e){exit(1);}" 2>/dev/null; then
  echo "Seeding permissions, roles, and super admin..."
  php artisan db:seed --class=PermissionSeeder --force --no-interaction 2>/dev/null || true
  php artisan db:seed --class=RoleSeeder --force --no-interaction 2>/dev/null || true
  php artisan db:seed --class=SuperAdminSeeder --force --no-interaction 2>/dev/null || true
fi
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

mkdir -p public/images/avatars public/customer-images storage/app/public storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache public/storage public/images public/customer-images 2>/dev/null || true

exec "$@"
