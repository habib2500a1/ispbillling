# demo.anetbd.com — ডেমো সাইট সেটআপ

শুধু **নকল ডেটা** — কোনো আসল গ্রাহক, ফোন, বিল বা MikroTik push নেই।

**পুরো ওয়েবসাইট** demo — শুধু admin নয়:

| অংশ | URL |
|-----|-----|
| Landing (packages) | `https://demo.anetbd.com/` |
| Sign in hub | `/login` |
| Customer portal | `/portal/login` |
| Reseller portal | `/reseller/login` |
| Pay bill | `/pay` |
| Shop | `/shop` |
| Admin | `/admin` |

---

## সংক্ষেপে কী করবেন

| ধাপ | কাজ |
|-----|-----|
| 1 | DNS: `demo` → `anetbd.com` এর IP |
| 2 | NextDeploy এ **নতুন app** (production app এর সাথে মিশাবেন না) |
| 3 | Environment: `deploy/.env.demo.example` copy → password/key বদলান |
| 4 | Domain: `demo.anetbd.com` → service **nginx**, port **80** |
| 5 | Redeploy → Terminal: `bash scripts/setup-demo-site.sh` |

---

## ১) DNS (cPanel / Cloudflare / domain panel)

```
Type: A
Name: demo
Value: (anetbd.com যে IP — যেমন 204.136.10.31)
TTL: Auto
```

ফলাফল: `https://demo.anetbd.com` সার্ভারে যাবে।

---

## ২) NextDeploy — আলাদা app + আলাদা branch (গুরুত্বপূর্ণ)

**একই app এ demo domain যোগ করবেন না** — production database এ real customer চলে আসবে।

**Branch:** Production → `main` · Demo → **`demo`** ([DEMO_BRANCH.md](./DEMO_BRANCH.md))

| Field | Demo app |
|-------|----------|
| Repo | `habib2500a1/ispbillling` (একই) |
| Branch | **`demo`** (main নয়) |
| Compose | `docker-compose.yml` |
| Environment | `deploy/.env.demo.example` থেকে copy |
| `APP_URL` | `https://demo.anetbd.com` |
| `ISP_DEMO_MODE` | `true` |
| `DB_DATABASE` | `isp_platform_demo` (আলাদা) |
| `POSTGRES_DB` | `isp_platform_demo` |
| `CACHE_PREFIX` | `demo_` (Redis clash এড়াতে) |
| Domain | `demo.anetbd.com` → **nginx:80** |

`APP_KEY` জেনারেট:

```bash
php artisan key:generate --show
```

Environment এ paste করুন।

---

## ৩) ডেমো ডেটা সিড

প্রথম deploy এর পর app container Terminal:

```bash
bash scripts/setup-demo-site.sh
```

অথবা পুরো DB নতুন (শুধু demo app এ):

```bash
bash scripts/setup-demo-site.sh --fresh
```

Auto: `ISP_DEMO_MODE=true` থাকলে প্রথম deploy এ `isp:post-deploy` নিজে থেকে demo data সিড করবে (DEMO-001 না থাকলে)।

---

## ৪) লগইন (সব fake)

| Role | Login | Password |
|------|-------|----------|
| **Customer portal** | `DEMO-001` | `demo123` |
| **Reseller** | `DEMO-RSL` | `demo123` |
| **Admin** | `demo@anetbd.com` | `.env` `ISP_ADMIN_PASSWORD` |
| **Pay bill** | Client code `DEMO-001` | OTP বন্ধ (সরাসরি bill) |

প্রতিটি পেজে **বেগুনি DEMO ব্যানার** + login পেজে **ডেমো credential hint** দেখাবে।

---

## ডেমোতে কী আছে (fake)

- **Landing:** ৪টি package (10/15/25/50 Mbps), notice, marquee
- **Customer portal:** ১৫ জন subscriber, bill/invoice, usage UI
- **Reseller:** `DEMO-RSL` franchise + ৮ জন reseller customer
- **Shop:** ONU, router, cable ইত্যাদি demo product
- **Pay bill:** `DEMO-001` দিয়ে lookup
- **Admin:** network (MikroTik, OLT, POP), subscribers, reports

**নেই:** আসল customer export, live SMS, router push, bKash live payment।

---

## Demo vs Production

| | demo.anetbd.com | anetbd.com |
|---|-----------------|------------|
| App | আলাদা NextDeploy app | Production app |
| Database | `isp_platform_demo` | `isp_platform` |
| `ISP_DEMO_MODE` | `true` | `false` |
| Mobile APK | build বন্ধ (`MOBILE_BUILD_ON_DEPLOY=0`) | auto sync |
| Data | শুধু DEMO-* | Real |

---

## সমস্যা সমাধান

**Production data দেখাচ্ছে** → ভুল app/domain; আলাদা DB চেক করুন।

**DEMO ব্যানার নেই** → `ISP_DEMO_MODE=true` + `php artisan config:clear`

**502 / DB error** → `DB_HOST=postgres`, `POSTGRES_PASSWORD` = `DB_PASSWORD`

**Admin login হয় না** → `php artisan isp:bootstrap-admin`

---

## আরো ডেটা (optional)

```bash
php artisan isp:seed-demo-network --tenant=1 --force
```

Sheba-Fi style JSON import (manual file):

```bash
php artisan isp:import-sheba-fi-json /path/export.json --tenant=1
```

বিস্তারিত: [DEMO_MODE.md](./DEMO_MODE.md)
