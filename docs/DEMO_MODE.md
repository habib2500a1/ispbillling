# Demo mode — Sheba-Fi style ডেমো সাইট

**anetbd.com ডেমো:** [DEMO_ANETBD.md](./DEMO_ANETBD.md) — `demo.anetbd.com`, **পুরো ওয়েবসাইট** (landing + portal + reseller + shop + pay + admin), আলাদা DB, `deploy/.env.demo.example`

## দ্রুত সেটআপ (নতুন সার্ভার / subdomain)

```bash
cd /var/www/isp-platform

# 1) ডাটাবেস + অ্যাডমিন (ঐচ্ছিক: পুরো ডাটা মুছে নতুন)
php artisan isp:demo-setup --fresh

# 2) .env এ demo চালু
ISP_DEMO_MODE=true
APP_URL=https://demo.yourdomain.com
ISP_DEPLOYMENT_MODE=saas
ISP_LICENSE_ENFORCE=false

# 3) ক্যাশ ক্লিয়ার
php artisan config:clear
sudo -u www-data php artisan view:clear
```

লগইন: `{APP_URL}/admin`  
ইউজার: `ISP_ADMIN_EMAIL` / `ISP_ADMIN_PASSWORD` (`.env`)

---

## Demo mode চালু থাকলে কী হয়

| চালু থাকে | বন্ধ (নিরাপদ) |
|-----------|----------------|
| Admin UI, তালিকা, ফর্ম, রিপোর্ট | — |
| — | আসল **SMS** |
| — | **MikroTik / RADIUS** push |
| — | **WebSIP** লাইভ কল |
| — | Auto suspend (ডেমো ডেটা নষ্ট হবে না) |

উপরে **বেগুনি DEMO ব্যানার** দেখাবে।

---

## ডেমো ডেটা

```bash
php artisan isp:seed-demo-network --tenant=1 --force
```

- Demo router, OLT, POP, ONU
- `isp:demo-setup` তে ১৫ জন sample subscriber (`DEMO-001` … `DEMO-015`, fake phone)

Sheba-Fi JSON থেকে আরো ক্লায়েন্ট:

```bash
php artisan isp:import-sheba-fi-json /path/export.json --tenant=1
```

---

## Payment gateway (ডেমোতে sandbox)

Admin → **Payments** → gateway settings → **Sandbox ON** (bKash/Nagad/SSL).

লাইভ key ডেমো `.env`-এ দেবেন না।

---

## Production vs Demo

| | Demo | Production |
|---|------|------------|
| `ISP_DEMO_MODE` | `true` | `false` |
| `APP_DEBUG` | `false` (public demo) | `false` |
| Database | আলাদা DB বা `--fresh` | Real data |
| Domain | `demo.example.com` | `bill.flixbd.xyz` |

একই কোডবেস — শুধু `.env` + আলাদা database।
