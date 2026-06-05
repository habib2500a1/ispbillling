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

কোড pull হলে **Files** tab এ `deploy/docker-compose.yml` দেখা যাবে।

---

## Step 4 — Compose File Path

App → **Overview** (বা compose settings) → **Compose file path**:

```
deploy/docker-compose.yml
```

**Save path** চাপুন।

---

## Step 5 — Environment Variables

App → **Environment** tab → **Raw editor**

`deploy/.env.nextdeploy.example` ফাইলের content copy করে paste করুন (repo তে আছে)।

**অবশ্যই বদলান:**

- `APP_URL` → আপনার domain (`https://billing.yourisp.com`)
- `SESSION_DOMAIN` → `.yourisp.com`
- `DB_PASSWORD` + `POSTGRES_PASSWORD` → একই strong password
- `APP_KEY` → খালি রাখুন, Step 8 এ generate করবেন

**Save environment** চাপুন।

---

## Step 6 — Domain + HTTPS

App → **Domains** tab → **Add domain**

| Field | Value |
|-------|-------|
| Domain | `billing.yourisp.com` |
| Service | `nginx` |
| Port | `80` |
| HTTPS | ✅ Enable |

DNS: domain এর A record → server IP

Domain save করার পর **Redeploy** লাগবে (panel reminder দেবে)।

Environment এ `APP_URL` domain এর সাথে মিলিয়ে আবার save করুন।

---

## Step 7 — প্রথম Deploy

App → **Deployment** tab → **Deploy** (বা Pull & Deploy)

Live logs দেখুন — `app`, `nginx`, `postgres`, `redis`, `horizon`, `scheduler` build/start হবে।

---

## Step 8 — প্রথমবার Setup Commands

Deploy success হলে App → **Terminal** tab (বা Containers → `app` exec):

```bash
php artisan key:generate --show
```

output copy করে **Environment** tab এ `APP_KEY=base64:...` যোগ করুন → Save → **Redeploy**

তারপর আবার Terminal:

```bash
php artisan migrate --force
php artisan storage:link
php artisan isp:generate-webhook-secrets --write
php artisan config:cache
php artisan route:cache
```

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
| 502 Bad Gateway | Containers tab — `app` + `nginx` running? Redeploy |
| Database error | `DB_HOST=postgres` (127.0.0.1 নয়) |
| CSS/JS নেই | `--profile setup run --rm assets` |
| Domain কাজ করে না | Domains save → Redeploy; DNS A record চেক |
| Queue job আটকে | `horizon` container running? |
| Upload fail | `deploy/nginx.conf` এ 20M limit আছে |

NextDeploy panel issue: [NextDeploy troubleshooting](https://github.com/masudranaxpert/NextDeploy/blob/main/docs/troubleshooting.md)

---

## Server এ থাকা ফাইল

| File | কাজ |
|------|-----|
| `deploy/docker-compose.yml` | Panel compose path |
| `deploy/.env.nextdeploy.example` | Environment tab template |
| `deploy/PRODUCTION_CHECKLIST.md` | Full production checklist |
