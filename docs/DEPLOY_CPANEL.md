# cPanel এ ISP Platform Deploy — Step by Step

এই গাইড অনুযায়ী GitHub থেকে clone করে cPanel/VPS এ production deploy করা যাবে।

**Repository:** https://github.com/habib2500a1/ispbillling

---

## এক ক্লিকে Install (সহজ — প্রথমে এটা দেখুন)

**One-click guide:** [`docs/INSTALL_CPANEL_WEBUZO.md`](INSTALL_CPANEL_WEBUZO.md)

### ZIP + Web Wizard (public_html — panel user এর জন্য সবচেয়ে সহজ)

1. Download: https://github.com/habib2500a1/ispbillling/releases/latest → `isp-platform-cpanel-public_html.zip`
2. cPanel File Manager → `/home/user/` এ upload + extract
3. Domain document root = `public_html`
4. Browser: `https://your-domain.com/install` → Permissions → Database → Admin

Server Terminal এ এক লাইন (git clone):

```bash
curl -fsSL https://raw.githubusercontent.com/habib2500a1/ispbillling/main/install.sh | bash
```

অথবা clone করার পর:

```bash
cd ~/isp-platform && bash scripts/install-cpanel-webuzo.sh
```

cPanel Git Version Control ব্যবহার করলে `.cpanel.yml` auto-deploy করে — **Pull or Deploy** চাপলেই হবে।

নিচের step-by-step guide manual setup বা troubleshooting এর জন্য।

---

## Step 0 — আগে যা লাগবে

| Item | Minimum |
|------|---------|
| PHP | **8.2 বা 8.3** (8.3 recommended) |
| Database | **PostgreSQL 14+** (production) অথবা MySQL 8 |
| Redis | Strongly recommended (queue + cache) |
| Composer | 2.x |
| Node.js | 20+ (শুধু প্রথম build এর জন্য) |
| SSL | Let's Encrypt (cPanel AutoSSL) |

**PHP extensions (অবশ্যই চালু থাকতে হবে):**

`pdo`, `pdo_pgsql` (বা `pdo_mysql`), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `intl`, `zip`, `pcntl`, `redis` (যদি Redis ব্যবহার করেন)

cPanel → **Select PHP Version** → Extensions থেকে চেক করুন।

---

## Step 1 — Subdomain / Domain তৈরি

1. cPanel → **Domains** → **Create A New Domain** (বা Subdomain)
2. Domain উদাহরণ: `billing.yourisp.com`
3. **Document Root** সেট করুন:

```
/home/USERNAME/isp-platform/public
```

> **গুরুত্বপূর্ণ:** Laravel এর root `public/` ফোল্ডার — project root (`isp-platform/`) নয়।

যদি আগে থেকে `public_html` এ domain থাকে, তাহলে **Addon Domain** ব্যবহার করে আলাদা folder এ clone করুন।

---

## Step 2 — GitHub থেকে Code নামানো

### Option A — cPanel Git Version Control (সহজ)

1. cPanel → **Git™ Version Control** → **Create**
2. Clone URL:

```
https://github.com/habib2500a1/ispbillling.git
```

3. Repository Path:

```
/home/USERNAME/isp-platform
```

4. **Clone** চাপুন
5. পরবর্তী update এর জন্য: **Pull or Deploy** → **Update from Remote**

### Option B — SSH / Terminal

```bash
cd /home/USERNAME
git clone https://github.com/habib2500a1/ispbillling.git isp-platform
cd isp-platform
```

---

## Step 3 — `.env` ফাইল তৈরি

```bash
cd /home/USERNAME/isp-platform
cp .env.example .env
```

cPanel **File Manager** বা `nano .env` দিয়ে এডিট করুন। নিচের মানগুলো অবশ্যই বদলান:

```env
APP_NAME="Your ISP Billing"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://billing.yourisp.com
APP_TIMEZONE=Asia/Dhaka

# Production CSS bundle (optional, faster admin)
ISP_BUNDLE_CSS=true

# Database — PostgreSQL (recommended)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=isp_platform
DB_USERNAME=isp_user
DB_PASSWORD=YOUR_STRONG_PASSWORD

# Session (domain অনুযায়ী)
SESSION_DOMAIN=.yourisp.com

# Queue + Cache (Redis recommended)
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Cloudflare / reverse proxy থাকলে
TRUSTED_PROXIES=173.245.48.0/20,103.21.244.0/22
```

cPanel **PostgreSQL Databases** থেকে DB + user তৈরি করে উপরের মানগুলো মিলিয়ে নিন।

---

## Step 4 — Application Key

Terminal বা cPanel **Terminal**:

```bash
cd /home/USERNAME/isp-platform
php artisan key:generate
```

`.env` এ `APP_KEY=base64:...` সেট হয়ে যাবে।

---

## Step 5 — Composer Dependencies

```bash
cd /home/USERNAME/isp-platform
composer install --no-dev --optimize-autoloader
```

cPanel এ Composer না থাকলে:

```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
```

---

## Step 6 — Frontend Assets Build

প্রথম deploy এ একবার (Node.js লাগবে):

```bash
cd /home/USERNAME/isp-platform
npm ci
npm run build
```

> Local PC তে build করে `public/build/` upload করাও যায় — তবে server এ build করা ভালো।

Admin modular CSS bundle চালু থাকলে:

```bash
php artisan isp:build-styles
```

---

## Step 7 — Database Migration

```bash
cd /home/USERNAME/isp-platform
php artisan migrate --force
```

প্রথম admin user seed করতে (যদি seeder থাকে):

```bash
php artisan db:seed --force
```

অথবা Filament admin panel থেকে user তৈরি করুন।

---

## Step 8 — Storage Permission

```bash
cd /home/USERNAME/isp-platform
chmod -R ug+rwx storage bootstrap/cache
chown -R USERNAME:USERNAME storage bootstrap/cache
```

cPanel shared hosting এ `USERNAME` = আপনার cPanel user। কিছু host এ `nobody` বা `www-data` লাগতে পারে — support জিজ্ঞেস করুন।

অথবা script:

```bash
bash scripts/fix-storage-perms.sh
```

---

## Step 9 — Laravel Scheduler (Cron) — অবশ্যই

cPanel → **Cron Jobs** → **Add New Cron Job**

| Field | Value |
|-------|-------|
| Minute | `*` |
| Hour | `*` |
| Day | `*` |
| Month | `*` |
| Weekday | `*` |
| Command | দেখুন নিচে |

```bash
cd /home/USERNAME/isp-platform && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

> PHP path খুঁজতে: `which php` বা cPanel **Select PHP Version** এ path দেখুন। সাধারণত `/usr/local/bin/php` বা `/usr/bin/php`.

যাচাই:

```bash
php artisan schedule:list
```

---

## Step 10 — Queue Worker (Redis + Horizon)

Production এ background job (MikroTik sync, SMS, invoice) এর জন্য queue চালু রাখতে হবে।

`.env`:

```env
QUEUE_CONNECTION=redis
QUEUE_HEAVY_JOBS_ENABLED=true
```

### Shared cPanel (Horizon না থাকলে)

Cron এ প্রতি মিনিটে (scheduler এর পাশাপাশি) — **সব host এ কাজ নাও করতে পারে**:

```bash
cd /home/USERNAME/isp-platform && /usr/local/bin/php artisan queue:work redis --stop-when-empty --max-time=55 >> storage/logs/queue.log 2>&1
```

### VPS / root access থাকলে (recommended)

```bash
sudo cp deploy/laravel-horizon.service.example /etc/systemd/system/laravel-horizon.service
# WorkingDirectory + User ঠিক করুন
sudo systemctl daemon-reload
sudo systemctl enable --now laravel-horizon
```

---

## Step 11 — Production Cache

`.env` ফাইনাল হওয়ার **পর** একবার:

```bash
cd /home/USERNAME/isp-platform
bash scripts/production-optimize.sh
```

অথবা ম্যানুয়ালি:

```bash
php artisan config:cache
php artisan route:cache
php artisan optimize:clear
```

---

## Step 12 — SSL + Cloudflare

1. cPanel → **SSL/TLS Status** → AutoSSL enable
2. Cloudflare ব্যবহার করলে:
   - SSL mode: **Full (strict)**
   - `.env` এ `TRUSTED_PROXIES` সেট করুন
   - `APP_URL=https://billing.yourisp.com` (http নয়)

---

## Step 13 — Webhook Secrets (Production)

```bash
php artisan isp:generate-webhook-secrets --write
php artisan config:clear
php artisan config:cache
```

---

## Step 14 — Smoke Test

ব্রাউজারে খুলুন:

| URL | কি দেখবেন |
|-----|-----------|
| `https://billing.yourisp.com` | Landing / portal |
| `https://billing.yourisp.com/admin` | Filament admin login |
| `https://billing.yourisp.com/api/v1/health` | JSON health |

Terminal audit:

```bash
php artisan isp:production-audit --skip-tests
```

---

## প্রতিবার Update (Git Pull পর)

GitHub থেকে নতুন code আনার পর **এই ক্রম** মেনে চলুন:

```bash
cd /home/USERNAME/isp-platform
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
bash scripts/post-deploy.sh
# অথবা
bash scripts/production-optimize.sh
```

`scripts/post-deploy.sh` করে:

- `migrate --force`
- `optimize:clear`
- PHP-FPM reload (VPS এ)

---

## cPanel সমস্যা সমাধান

| সমস্যা | সমাধান |
|--------|--------|
| 500 Error | `storage/logs/laravel.log` দেখুন; permission ঠিক করুন |
| CSS/JS ভাঙা | `npm run build` + `php artisan optimize:clear` |
| Admin search/filter কাজ করে না | Hard refresh: `Ctrl+Shift+R`; cache clear |
| Image upload fail | `bash scripts/fix-image-upload-limits.sh` (VPS) অথবা cPanel PHP `upload_max_filesize=12M` |
| `.env` দেখা যায় | Document root `public/` আছে কিনা চেক করুন |
| Session logout হয় | `SESSION_DOMAIN` ঠিক করুন |

---

## দরকারি ফাইল (repo তে)

| File | কাজ |
|------|-----|
| `install.sh` | GitHub one-liner (clone + install) |
| `scripts/install-cpanel-webuzo.sh` | Interactive one-click installer |
| `scripts/post-deploy-cpanel.sh` | cPanel shared hosting post-deploy |
| `.cpanel.yml` | cPanel Git auto-deploy on pull |
| `deploy/.env.cpanel.example` | cPanel `.env` template |
| `deploy/PRODUCTION_CHECKLIST.md` | Full production checklist |
| `scripts/post-deploy.sh` | VPS pull পর quick deploy |
| `scripts/production-optimize.sh` | Cache + FPM reload |
| `deploy/scheduler-cron.example` | Cron line example |
| `deploy/laravel-horizon.service.example` | Horizon systemd |

---

## Quick Command Summary (copy-paste)

প্রথম deploy:

```bash
cd /home/USERNAME/isp-platform
cp .env.example .env
# .env edit করুন
php artisan key:generate
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
chmod -R ug+rwx storage bootstrap/cache
php artisan isp:generate-webhook-secrets --write
bash scripts/production-optimize.sh
php artisan isp:production-audit --skip-tests
```

পরবর্তী update:

```bash
cd /home/USERNAME/isp-platform && git pull origin main && composer install --no-dev --optimize-autoloader && bash scripts/post-deploy.sh
```
