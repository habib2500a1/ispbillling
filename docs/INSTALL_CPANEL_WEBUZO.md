# cPanel / Webuzo — One-Click Install (সহজ ইনস্টল)

GitHub থেকে **এক কমান্ডে** ISP Platform install করুন। Node.js লাগবে না — pre-built CSS already repo তে আছে।

**Repository:** https://github.com/habib2500a1/ispbillling

---

## আগে যা করবেন (৫ মিনিট)

| Step | cPanel / Webuzo |
|------|-----------------|
| 1 | **PHP 8.2 বা 8.3** enable করুন (Select PHP Version) |
| 2 | **MySQL Database** + **User** তৈরি করুন (cPanel → MySQL Databases) |
| 3 | **Domain / Subdomain** তৈরি করুন — Document Root = `.../isp-platform/public` |
| 4 | **Terminal** খুলুন (cPanel → Terminal) |

**PHP extensions চালু রাখুন:** `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `zip`

---

## Method 1 — সবচেয়ে সহজ (One Command)

Server Terminal এ copy-paste করুন:

```bash
curl -fsSL https://raw.githubusercontent.com/habib2500a1/ispbillling/main/install.sh | bash
```

Script automatically:
- GitHub থেকে clone করবে (`~/isp-platform`)
- `.env` তৈরি করবে
- প্রশ্ন করবে: domain, database, admin password
- `composer install`, migrate, admin user, cache — সব করবে

---

## Method 2 — cPanel Git Version Control (Panel থেকে)

### প্রথমবার

1. cPanel → **Git™ Version Control** → **Create**
2. Clone URL:
   ```
   https://github.com/habib2500a1/ispbillling.git
   ```
3. Repository Path:
   ```
   /home/YOUR_CPANEL_USER/isp-platform
   ```
4. **Clone** চাপুন
5. Terminal এ:
   ```bash
   cd ~/isp-platform
   bash scripts/install-cpanel-webuzo.sh
   ```

### পরবর্তী update (Auto Deploy)

`.cpanel.yml` repo তে আছে — cPanel Git এ **Pull or Deploy** চাপলেই automatic:
- `composer install`
- `migrate`
- cache rebuild

অথবা Terminal:
```bash
cd ~/isp-platform && git pull && bash scripts/post-deploy-cpanel.sh
```

---

## Method 3 — Non-Interactive (সব একসাথে)

Database তৈরি করে এক লাইনে:

```bash
cd ~/isp-platform
APP_URL=https://billing.yourisp.com \
DB_DATABASE=cpaneluser_isp \
DB_USERNAME=cpaneluser_isp \
DB_PASSWORD='YourDbPassword' \
ISP_ADMIN_EMAIL=admin@yourisp.com \
ISP_ADMIN_PASSWORD='YourAdminPass123' \
bash scripts/install-cpanel-webuzo.sh --yes
```

---

## Cron Jobs (অবশ্যই — ২টা line)

cPanel → **Cron Jobs** → Every minute (`* * * * *`):

**Line 1 — Scheduler:**
```bash
cd /home/YOUR_USER/isp-platform && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Line 2 — Queue (background jobs):**
```bash
cd /home/YOUR_USER/isp-platform && /usr/local/bin/php artisan queue:work database --stop-when-empty --max-time=55 >> /home/YOUR_USER/isp-platform/storage/logs/queue.log 2>&1
```

> `which php` দিয়ে PHP path চেক করুন। কিছু host এ `/usr/local/bin/ea-php83` হয়।

---

## Document Root (গুরুত্বপূর্ণ)

| সঠিক | ভুল |
|-------|-----|
| `/home/user/isp-platform/public` | `/home/user/isp-platform` |

Laravel এর `public/` folder web root হতে হবে — নাহলে `.env` leak হতে পারে।

---

## Install পর Verify

```bash
cd ~/isp-platform
php artisan isp:production-audit --skip-tests
```

Browser:
| URL | কি দেখবেন |
|-----|-----------|
| `https://your-domain.com` | Landing page |
| `https://your-domain.com/admin` | Admin login |
| `https://your-domain.com/api/v1/health` | JSON OK |

---

## Webuzo Panel

Webuzo তেও same steps:
1. Git clone বা Terminal দিয়ে `install.sh` চালান
2. Document root = `public/`
3. MySQL database cPanel-style তৈরি করুন
4. Cron ২টা line add করুন

---

## ফাইল রেফারেন্স

| File | কাজ |
|------|-----|
| `install.sh` | GitHub one-liner (clone + install) |
| `scripts/install-cpanel-webuzo.sh` | Main installer |
| `scripts/post-deploy-cpanel.sh` | Git pull / deploy পর |
| `.cpanel.yml` | cPanel auto-deploy |
| `deploy/.env.cpanel.example` | cPanel `.env` template |

বিস্তারিত manual steps: [`docs/DEPLOY_CPANEL.md`](DEPLOY_CPANEL.md)

---

## সমস্যা সমাধান

| সমস্যা | সমাধান |
|--------|--------|
| 500 Error | `storage/logs/laravel.log` দেখুন; `bash scripts/fix-storage-perms.sh` |
| Database error | cPanel MySQL user কে database এ **ALL PRIVILEGES** দিন |
| CSS ভাঙা | `.env` এ `ISP_BUNDLE_CSS=true` আছে কিনা চেক করুন |
| Queue কাজ করে না | Cron line 2 add করেছেন কিনা |
| Composer নেই | Script auto-download `composer.phar` |

Support: GitHub Issues — https://github.com/habib2500a1/ispbillling/issues
