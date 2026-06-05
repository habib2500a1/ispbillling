# Docker / NextDeploy deploy notes

এই ফোল্ডার ISP Platform কে [NextDeploy](https://github.com/masudranaxpert/NextDeploy) panel এ চালানোর জন্য।  
Panel compose path: **`docker-compose.yml`** (repo root)।

**লক্ষ্য:** GitHub থেকে pull → Redeploy — বাকি সব **auto** (permission, DB, admin, logo, cache, optimize)।

---

## একবার মাত্র panel এ (ম্যানুয়াল)

| Step | কাজ |
|------|-----|
| 1 | GitHub repo connect + compose path `docker-compose.yml` |
| 2 | Environment: `deploy/.env.nextdeploy.example` copy → password/domain বদলান |
| 3 | `APP_KEY` — Terminal: `php artisan key:generate --show` → Environment এ যোগ |
| 4 | Domains: service **`nginx`**, port **`80`** |
| 5 | **Redeploy with rebuild** |

এর পর প্রতিটি redeploy এ auto:

- `composer install` (vendor incomplete হলে)
- PostgreSQL `isp_app` user (`ensure-db-user.sh`)
- `migrate` + `isp:bootstrap-admin`
- `storage:link` + **permission fix** (`ensure-permissions.sh`)
- **Logo/favicon seed** (`deploy/branding/` → storage)
- Webhook secrets (`.env` এ না থাকলে)
- `config:cache`, `route:cache`, Filament optimize, OPcache

---

## Panel — অবশ্যই ঠিক রাখুন

### Domains tab

| Field | Value | ভুল (কখনো নয়) |
|-------|-------|----------------|
| Service | **`nginx`** | `app` |
| Port | **`80`** (container port) | `8023` (host test port) |

`8023` শুধু ব্রাউজারে `http://SERVER_IP:8023` test এর জন্য — Domains tab এ দেবেন না।

### Environment (`.env`)

- `DB_HOST=postgres` (127.0.0.1 নয়)
- `POSTGRES_USER=isp` — existing volume এ superuser; `DB_USERNAME=isp_app` OK
- `DB_PASSWORD` = `POSTGRES_PASSWORD` (একই)
- `ISP_ADMIN_EMAIL` — **একবারই** (duplicate লাইন দেবেন না)
- `APP_DEBUG=false` — true হলে site ধীর
- `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `REDIS_HOST=redis`
- `ISP_LANDING_DOMAIN=yourdomain.com` — Caddy compose label (optional backup)
- `APP_URL=https://yourdomain.com` — production domain

Template: [`deploy/.env.nextdeploy.example`](.env.nextdeploy.example)

### Compose ports

```yaml
nginx:
  ports:
    - "8023:80"   # ✅
    # - "80:80"   # ❌ host 80 = NextDeploy Caddy
```

---

## GitHub এ যা আছে (auto deploy)

| Path | উদ্দেশ্য |
|------|---------|
| `docker-compose.yml` | Panel stack |
| `deploy/Dockerfile` | PHP 8.3 + OPcache + FPM tune |
| `deploy/nginx.conf` | gzip + static cache |
| `deploy/docker-entrypoint.sh` | composer + permissions |
| `deploy/bootstrap-app.sh` | DB + admin + logo + optimize |
| `deploy/branding/` | Default logo + favicon (git-tracked) |
| `public/css/`, `public/js/` | Pre-built assets (`ISP_BUNDLE_CSS=true`) |

**Git এ নেই (স্বাভাবিক):** `.env`, `vendor/`, customer upload (`storage/app/public/*` except auto-seed), database data (`pgdata` volume)।

নতুন logo git এ রাখতে: `deploy/branding/company-logo.png` replace → commit → redeploy।

---

## Architecture

```
Browser → Cloudflare → Caddy (NextDeploy) → nginx:80 → app:9000 (php-fpm)
Direct test: http://SERVER_IP:8023 → nginx:80 → app:9000
```

`app` + `nginx` দুটোই **`NextDeploy`** + **`isp`** network এ — Caddy ও fastcgi দুটোই কাজ করে।

---

## সমস্যা দ্রুত চেক

| লক্ষণ | কারণ | Fix |
|--------|------|-----|
| 502 domain | Domains `app:8023` বা `app:80` | `nginx` + `80` → Redeploy |
| 502 after redeploy | nginx পুরনো app IP | Restart `app` তারপর `nginx` |
| `app` restarting loop | php-fpm `daemonize=no` missing | Redeploy with rebuild (latest Dockerfile) |
| DB `isp_app` auth fail | Volume `isp` দিয়ে তৈরি | `POSTGRES_USER=isp`, redeploy rebuild |
| Login fail | duplicate `ISP_ADMIN_EMAIL` | একটা email, redeploy |
| IP login fail | `SESSION_DOMAIN=.domain` + HTTP IP | domain দিয়ে login করুন |
| Logo দেখায় না | পুরনো deploy / branding seed হয়নি | Git pull + **Redeploy with rebuild** |
| Site ধীর | cache/OPcache চালু নেই | `APP_DEBUG=false`, redeploy rebuild |
| Page layout ভাঙা | Livewire menu navigate এ page CSS load হয়নি | Git pull + redeploy; `ISP_BUNDLE_CSS=true`; Ctrl+F5 |
| Permission 500 | root-owned storage/views | auto-fix on restart; `ensure-permissions.sh` |
| `80:80` bind error | Caddy already uses host 80 | `8023:80` রাখুন |

বিস্তারিত: [`docs/DEPLOY_NEXTDEPLOY.md`](../docs/DEPLOY_NEXTDEPLOY.md)
