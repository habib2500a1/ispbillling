# NextDeploy Panel দিয়ে Deploy — Step by Step

আপনি **[NextDeploy](https://github.com/masudranaxpert/NextDeploy)** panel ব্যবহার করবেন — cPanel/Coolify লাগবে না।

**App repo:** https://github.com/habib2500a1/ispbillling

---

## Step 1 — NextDeploy Install (একবার)

Server এ panel না থাকলে:

```bash
curl -fsSL https://raw.githubusercontent.com/masudranaxpert/NextDeploy/main/install.sh | sudo bash
```

ব্রাউজার: `http://<server-ip>:8080` → প্রথম **admin account** তৈরি করুন।

---

## Step 2 — নতুন App তৈরি

Panel → **Apps** → **New App**

| Field | Value |
|-------|-------|
| App name | `isp-billing` (ছোট হাতের অক্ষর, ড্যাশ) |
| Source | **GitHub** |
| Repository URL | `https://github.com/habib2500a1/ispbillling.git` |
| Branch | `main` |
| Access | Public repo |

**Create App** চাপুন — workspace auto তৈরি হবে।

---

## Step 3 — Git Sync

App খুলুন → **Git** tab → **Sync repository**

কোড pull হলে **Files** tab এ root এ `docker-compose.yml` দেখা যাবে।

---

## Step 4 — Compose File Path

App → **Overview** (বা compose settings) → **Compose file path**:

```
docker-compose.yml
```

> **গুরুত্বপূর্ণ:** `deploy/docker-compose.yml` ব্যবহার করবেন না — NextDeploy bake build path ভুল resolve করে। Root `docker-compose.yml` ব্যবহার করুন।

**Save path** চাপুন।

---

## Step 5 — Environment Variables

App → **Environment** tab → **Raw editor**

`deploy/.env.nextdeploy.example` ফাইলের content copy করে paste করুন (repo তে আছে)।

**অবশ্যই বদলান:**

- `APP_URL` → আপনার domain (`https://billing.yourisp.com`)
- `ISP_LANDING_DOMAIN` → same domain (Caddy backup label)
- `SESSION_DOMAIN` → `.yourisp.com`
- `DB_PASSWORD` + `POSTGRES_PASSWORD` → একই strong password
- `POSTGRES_USER=isp` + `DB_USERNAME=isp_app` → standard (auto `isp_app` on deploy)
- `ISP_ADMIN_EMAIL` + `ISP_ADMIN_PASSWORD` → **একবারই** (duplicate নয়)
- `APP_KEY` → খালি রাখুন, Step 8 এ generate করবেন

**Save environment** চাপুন।

---

## Step 6 — Domain + HTTPS

App → **Domains** tab → **Add domain**

| Field | Value |
|-------|-------|
| Domain | `billing.yourisp.com` |
| Service | `nginx` (**not** `app`) |
| Port | `80` = container ভিতরের port (**8023 নয়**) |
| HTTPS | ✅ Enable |

> **অথবা:** Environment এ `ISP_LANDING_DOMAIN=anetbd.com` থাকলে `docker-compose.yml` nginx এ Caddy label auto যোগ হয় — Domains tab এ ভুল `app:8023` থাকলে **Delete** করে Redeploy করুন (duplicate route এড়াতে)।

> **গুরুত্বপূর্ণ:** `docker-compose.yml` এ nginx **`8023:80`** রাখুন — **`80:80` নয়** (host 80 = Caddy)।

DNS: domain এর A record → server IP

Domain save করার পর **Redeploy** লাগবে (panel reminder দেবে)।

Environment এ `APP_URL` domain এর সাথে মিলিয়ে আবার save করুন।

---

## Step 7 — প্রথম Deploy

App → **Deployment** tab → **Deploy** (বা Pull & Deploy)

Live logs দেখুন — `app`, `nginx`, `postgres`, `redis`, `horizon`, `scheduler` build/start হবে।

---

## Step 8 — প্রথমবার Setup Commands

Deploy success হলে App → **Terminal** tab (`app` container)।

`vendor/` না থাকলে (bind mount) আগে:

```bash
composer install --no-dev --optimize-autoloader --no-scripts
```

তারপর:

```bash
php artisan key:generate --show
```

output copy করে **Environment** tab এ `APP_KEY=base64:...` যোগ করুন → Save → **Redeploy**

তারপর আবার Terminal:

Environment এ `ISP_ADMIN_EMAIL` + `ISP_ADMIN_PASSWORD` দিন — **app container start এ auto migrate + super-admin** তৈরি হয়।

```bash
php artisan isp:bootstrap-admin
php artisan isp:generate-webhook-secrets --write
```

Container start এ `bootstrap-app.sh` + `optimize-app.sh` auto চালায় (`config:cache`, `route:cache`, Filament optimize, OPcache)। ম্যানুয়াল দরকার হলে:

```bash
php artisan config:cache
php artisan route:cache
```

Admin login: `ISP_ADMIN_EMAIL` / `ISP_ADMIN_PASSWORD`

### Frontend CSS/JS build (প্রথমবার)

`public/build` repo তে নেই — একবার assets build:

Deployment tab বা server SSH থেকে workspace folder এ:

```bash
docker compose -f deploy/docker-compose.yml --profile setup run --rm assets
```

---

## Step 9 — যাচাই

| Test | URL |
|------|-----|
| Health API | `https://billing.yourisp.com/api/v1/health` |
| Admin panel | `https://billing.yourisp.com/admin` |
| Production audit | Terminal: `php artisan isp:production-audit --skip-tests` |

---

## Step 10 — Auto Deploy (optional)

App → **Deployment** tab → **Git webhook** section:

1. Payload URL copy করুন
2. GitHub repo → Settings → Webhooks → Add
3. Content type: `application/json`
4. Secret: panel এ দেখানো secret
5. Panel এ **Auto-deploy** on করুন

এখন থেকে `main` branch এ push করলে panel auto redeploy করবে।

---

## Step 11 — Mobile APK (automatic — কিছু দিতে হবে না)

**Flow:** `.env` এ `APP_URL` → deploy/install শেষে **auto build** → `public/downloads/` → `${APP_URL}/downloads/*.apk`

আলাদা GitHub secret, mobile tag, বা manual config **লাগে না**।

Environment tab (শুধু domain):

```env
APP_URL=https://anetbd.com
MOBILE_USE_GITHUB_RELEASES=false
```

Deploy বা install wizard শেষে background এ `scripts/auto-mobile-after-deploy.sh` চলে।  
Docker থাকলে Flutter ছাড়াই build হয় (`build-mobile-docker.sh`)।

### Manual (server এ Flutter থাকলে)

```bash
./scripts/deploy-mobile-apks.sh
```

Download links:

- `https://billing.yourisp.com/downloads/isp-radiant.apk`
- `https://billing.yourisp.com/downloads/isp-mfs-verify.apk`

---

## প্রতিবার Code Update

1. GitHub এ push (`git push origin main`)
2. Webhook থাকলে auto — না থাকলে panel → **Git** → **Sync** → **Deploy**
3. Terminal (প্রয়োজনে):

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

CSS বদলালে: `docker compose -f deploy/docker-compose.yml --profile setup run --rm assets`

---

## Panel Tab Quick Reference

| Tab | কাজ |
|-----|-----|
| **Git** | Code pull / sync |
| **Environment** | `.env` — সব config এখানে |
| **Domains** | Domain + HTTPS (Caddy auto) |
| **Deployment** | Deploy button + live logs |
| **Containers** | Running services দেখুন |
| **Terminal** | `php artisan` commands |
| **Logs** | Laravel / container logs |
| **Volumes** | `storage/`, database backup |

---

## সমস্যা হলে

| সমস্যা | সমাধান |
|--------|--------|
| 502 Bad Gateway | Domains: **`nginx` + `80`** (never `app:8023`). Restart `app` then `nginx`. **Redeploy rebuild** |
| 502 after redeploy only | nginx cached old app IP — Containers: restart app → nginx | 
| Port 80 / deploy bind error | nginx **`8023:80`** only — not `80:80` (Caddy uses host 80) |
| Database error | `DB_HOST=postgres` (127.0.0.1 নয়) |
| `password authentication failed for user "isp_app"` | `POSTGRES_USER=isp`, `DB_USERNAME=isp_app`, same password → redeploy rebuild |
| Admin login fail | duplicate `ISP_ADMIN_EMAIL` in .env → one email; `php artisan isp:bootstrap-admin` |
| IP:8023 login loop | `SESSION_DOMAIN` set but using HTTP IP — use `https://domain/admin` |
| CSS/JS নেই | `--profile setup run --rm assets` |
| Domain কাজ করে না | Domains save → Redeploy; DNS A record চেক |
| Queue job আটকে | `horizon` container running? |
| Upload fail | `deploy/nginx.conf` এ 20M limit আছে |

NextDeploy panel issue: [NextDeploy troubleshooting](https://github.com/masudranaxpert/NextDeploy/blob/main/docs/troubleshooting.md)

---

## Server এ থাকা ফাইল

| File | কাজ |
|------|-----|
| `docker-compose.yml` | Panel compose path (repo root) |
| `deploy/.env.nextdeploy.example` | Environment tab template |
| `deploy/README.md` | Docker/NextDeploy quick reference (502, domains, env) |
| `deploy/PRODUCTION_CHECKLIST.md` | Full production checklist |
