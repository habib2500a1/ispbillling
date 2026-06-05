# Coolify এ ISP Platform Deploy — Step by Step

Coolify দিয়ে Docker-based production deploy। Repo তে `deploy/Dockerfile` ও `deploy/docker-compose.yml` reference stack আছে।

**Repository:** https://github.com/habib2500a1/ispbillling

---

## Architecture (কি কি service লাগবে)

```
Internet → Coolify Proxy (Traefik) → Nginx → PHP-FPM (app)
                                      ↓
                              PostgreSQL + Redis
                                      ↓
                              Horizon (queue worker)
```

| Service | Image / Build | Port |
|---------|---------------|------|
| `app` | `deploy/Dockerfile` (PHP 8.3-FPM) | 9000 (internal) |
| `nginx` | `nginx:1.27-alpine` | 80 |
| `postgres` | `postgres:16-alpine` | 5432 |
| `redis` | `redis:7-alpine` | 6379 |
| `horizon` | same as `app`, command: `php artisan horizon` | — |

---

## Step 0 — Coolify Server প্রস্তুতি

1. Coolify install করা server এ SSH যান
2. Docker + Coolify running আছে কিনা চেক করুন
3. GitHub repo access — public repo, অথবা Coolify এ deploy key / PAT যোগ করুন

---

## Step 1 — PostgreSQL Database তৈরি

Coolify Dashboard → **Resources** → **New Resource** → **Database** → **PostgreSQL**

| Setting | Value |
|---------|-------|
| Name | `isp-postgres` |
| Version | 16 |
| Database | `isp_platform` |
| Username | `isp` |
| Password | strong random password |

**Internal hostname** নোট করুন — সাধারণত `isp-postgres` বা Coolify-generated name (যেমন `postgres-xxxxx`).

---

## Step 2 — Redis তৈরি

**New Resource** → **Database** → **Redis**

| Setting | Value |
|---------|-------|
| Name | `isp-redis` |
| Version | 7 |

Internal hostname নোট করুন।

---

## Step 3 — Main Application (PHP + Nginx)

### 3a — New Application

**New Resource** → **Application** → **Public Repository**

```
https://github.com/habib2500a1/ispbillling.git
Branch: main
```

### 3b — Build Settings

Coolify UI varies — সাধারণত **Dockerfile** path:

```
deploy/Dockerfile
```

Build context (base directory):

```
/
```
(Repository root — Dockerfile এ `COPY . .` আছে)

### 3c — Start Command

PHP-FPM container — default CMD from Dockerfile:

```
php-fpm
```

### 3d — Port

Internal: **9000** (PHP-FPM)

> Nginx আলাদা service হিসেবে যোগ করবেন (Step 4) অথবা Coolify **Docker Compose** deploy ব্যবহার করুন (Step 6 — সহজ পথ)।

---

## Step 4 — Nginx Reverse Proxy (আলাদা service)

যদি compose না ব্যবহার করেন, Coolify এ **Docker Compose** বা **multi-service** setup লাগে।

সরল পথ: **Step 6 — Docker Compose Deploy** ব্যবহার করুন (recommended)।

Manual nginx service:

| Setting | Value |
|---------|-------|
| Image | `nginx:1.27-alpine` |
| Config mount | `deploy/nginx.conf` |
| Root mount | repo → `/var/www/html` |
| fastcgi_pass | `app:9000` (app service hostname) |
| Expose port | 80 |

`deploy/nginx.conf` এ `root /var/www/html/public` — ঠিক আছে।

---

## Step 5 — Environment Variables

Coolify → Application → **Environment Variables**

নিচেরগুলো **অবশ্যই** সেট করুন:

```env
APP_NAME=ISP Platform
APP_ENV=production
APP_DEBUG=false
APP_URL=https://billing.yourisp.com
APP_TIMEZONE=Asia/Dhaka
ISP_BUNDLE_CSS=true

# Database — Coolify PostgreSQL internal host
DB_CONNECTION=pgsql
DB_HOST=isp-postgres
DB_PORT=5432
DB_DATABASE=isp_platform
DB_USERNAME=isp
DB_PASSWORD=<postgres-password-from-step-1>

# Redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=isp-redis
REDIS_PORT=6379
REDIS_PASSWORD=null

# Queue
QUEUE_HEAVY_JOBS_ENABLED=true

# Session
SESSION_DRIVER=database
SESSION_DOMAIN=.yourisp.com

# Security
TRUSTED_PROXIES=*
```

**APP_KEY** — প্রথম deploy এ container shell এ:

```bash
php artisan key:generate --show
```

output Coolify env তে `APP_KEY=base64:...` হিসেবে যোগ করুন, তারপর redeploy।

অথবা local এ generate করে paste করুন:

```bash
php artisan key:generate --show
```

Webhook secrets (production):

```bash
php artisan isp:generate-webhook-secrets
# output values manually env তে যোগ করুন
```

---

## Step 6 — Docker Compose Deploy (Recommended)

সব service একসাথে চালাতে repo এর compose file ব্যবহার করুন।

Coolify → **New Resource** → **Docker Compose**

| Setting | Value |
|---------|-------|
| Repository | `habib2500a1/ispbillling` |
| Compose file | `deploy/docker-compose.yml` |
| Base directory | `/` |

Deploy করার **আগে** `deploy/docker-compose.yml` এ password পরিবর্তন করুন:

```yaml
POSTGRES_PASSWORD: your_strong_secret
```

Coolify এ **Domains** যোগ করুন nginx service এ:

- Domain: `billing.yourisp.com`
- HTTPS: Enable (Let's Encrypt automatic)

Compose default port `8080:80` — Coolify proxy সাধারণত internal routing করে; domain nginx service এ bind করুন।

---

## Step 7 — Horizon (Queue Worker)

Horizon আলাদা service — `deploy/docker-compose.yml` এ already আছে:

```yaml
horizon:
  build:
    context: ..
    dockerfile: deploy/Dockerfile
  command: php artisan horizon
  depends_on:
    - app
    - redis
```

Coolify compose deploy এ `horizon` service **enable** রাখুন — এটি MikroTik sync, SMS, billing jobs চালায়।

Verify:

```bash
docker exec -it <horizon-container> php artisan horizon:status
```

---

## Step 8 — প্রথম Deploy Commands

Container shell (app service) এ একবার:

```bash
php artisan migrate --force
php artisan storage:link
php artisan isp:generate-webhook-secrets --write
php artisan config:cache
php artisan route:cache
```

Frontend assets — Dockerfile build এ `npm run build` **নেই**। দুটি option:

**Option A** — Local/CI তে build করে commit (যদি `public/build` tracked থাকে)

**Option B** — Deploy hook এ build যোগ করুন:

```bash
# Coolify Pre/Post deploy command
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

> Production Dockerfile এ শুধু `composer install` আছে — `npm run build` আলাদা step লাগে।

---

## Step 9 — Persistent Storage

Laravel এ persist করতে হবে:

| Path | কেন |
|------|-----|
| `storage/` | logs, uploads, cache |
| `bootstrap/cache/` | config/route cache |

Coolify → Application → **Storages / Volumes**:

```
/var/www/html/storage  →  persistent volume
/var/www/html/bootstrap/cache  →  persistent volume
```

PostgreSQL data — compose এ `pgdata` volume already defined।

---

## Step 10 — Scheduler (Cron)

Laravel scheduler — Coolify **Scheduled Tasks** বা আলাদা cron container:

```bash
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

Coolify UI: Application → **Scheduled Tasks** → every minute → উপরের command।

Verify:

```bash
php artisan schedule:list
```

---

## Step 11 — Domain + SSL

Coolify → Service (nginx) → **Domains**

1. `billing.yourisp.com` যোগ করুন
2. **Generate SSL** (Let's Encrypt)
3. `.env` / Coolify env: `APP_URL=https://billing.yourisp.com`

Cloudflare proxy থাকলে SSL mode **Full (strict)**।

---

## Step 12 — Smoke Test

| Check | Command / URL |
|-------|---------------|
| Health | `curl https://billing.yourisp.com/api/v1/health` |
| Admin | `https://billing.yourisp.com/admin` |
| Audit | `php artisan isp:production-audit --skip-tests` |
| Horizon | `php artisan horizon:status` |
| Logs | `storage/logs/laravel.log` |

---

## প্রতিবার Update (Redeploy)

1. Coolify → Application → **Redeploy** (বা Git webhook auto-deploy)
2. Post-deploy command (Coolify setting):

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

3. Horizon container restart (যদি queue config বদলায়)

Git webhook সেটআপ করলে `main` branch push এ auto deploy হবে।

---

## Coolify Environment Variable Cheat Sheet

| Variable | Example |
|----------|---------|
| `APP_KEY` | `base64:...` |
| `APP_URL` | `https://billing.yourisp.com` |
| `DB_HOST` | Coolify postgres internal hostname |
| `DB_PASSWORD` | from Step 1 |
| `REDIS_HOST` | Coolify redis internal hostname |
| `QUEUE_CONNECTION` | `redis` |
| `SESSION_DOMAIN` | `.yourisp.com` |
| `TRUSTED_PROXIES` | `*` (behind Coolify proxy) |

Payment/SMS/MikroTik keys — `.env.example` দেখুন অথবা Admin → System → Integrations।

---

## Troubleshooting

| সমস্যা | সমাধান |
|--------|--------|
| 502 Bad Gateway | PHP-FPM container running? `fastcgi_pass` hostname ঠিক? |
| DB connection refused | `DB_HOST` = Coolify **internal** hostname, not `127.0.0.1` |
| Queue jobs stuck | Horizon container running? `REDIS_HOST` ঠিক? |
| CSS missing | `npm run build` post-deploy এ চালান |
| Permission denied storage | Volume mount + `chown www-data` in Dockerfile already set |
| Scheduler not running | Coolify scheduled task every minute যোগ করুন |

---

## Reference Files (repo)

| File | Purpose |
|------|---------|
| `deploy/docker-compose.yml` | Full stack (app, nginx, postgres, redis, horizon) |
| `deploy/Dockerfile` | PHP 8.3-FPM + extensions |
| `deploy/nginx.conf` | Nginx config (`public/` root) |
| `deploy/PRODUCTION_CHECKLIST.md` | Production checklist |
| `scripts/post-deploy.sh` | VPS-style post-deploy (Coolify এ command হিসেবে adapt করুন) |

---

## Quick Start (Compose on Coolify)

1. PostgreSQL + Redis create করুন (অথবা compose এ embedded ব্যবহার করুন)
2. Docker Compose resource → `deploy/docker-compose.yml`
3. Env vars সেট করুন (`APP_KEY`, `APP_URL`, DB, Redis)
4. Domain nginx এ bind করুন + SSL
5. Deploy → shell: `php artisan migrate --force`
6. Scheduled task: `schedule:run` every minute
7. `php artisan isp:production-audit --skip-tests`
