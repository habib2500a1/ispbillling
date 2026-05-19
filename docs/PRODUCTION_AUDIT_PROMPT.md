# Production audit prompt (Cursor / Agent)

Copy-paste this prompt when you want a **full production pass** on ISP Platform:

---

## Prompt (English)

```
You are auditing /var/www/isp-platform for PRODUCTION readiness.

Work systematically — do NOT skip steps:

### 1. Feature & bug pass (one module at a time)
For each area, read code + run related tests; fix bugs found:
- Auth, 2FA, RBAC, tenant scope
- Subscribers (Customer CRUD, expiry, network sync, portal)
- Billing (invoices, late fee, dunning, collection desk, reports)
- Payments (bKash, Nagad, SSL, Rocket, pending verify, reconciliation)
- Network (MikroTik, OLT, bandwidth, session integrity, auto suspend)
- Notifications (SMS KhudeBarta, DLR, Telegram ops)
- Accounting / GL
- Support tickets, outages
- API (customer, technician, collector)
- Cron / automatic processes / queue jobs

### 2. Menu & navigation
- Sidebar: grouped (Overview → Subscribers → Billing → Payments → Network → Support → HR/Inventory/Finance → Reports → System)
- Hubs hidden from sidebar but linked from Operations hub + ⌘K (Dashboard hub, HR, Inventory, Reports, etc.)
- No duplicate entries (HR hub vs Employee list, Inventory hub vs Vendor list)
- Register important reports in sidebar OR command palette

### 3. Dashboard
- Dark + light theme works (topbar toggle + Filament darkMode)
- Widgets load without error; session integrity + fleet health visible
- Dashboard hub links to NOC / Billing / Support role dashboards
- Mobile: bottom dock + responsive tables

### 4. Remove dead code
- Orphan widgets not on any dashboard → wire or delete
- Unused routes, duplicate services, commented legacy blocks
- Do NOT remove migrations or production config

### 5. Production checks
Run and fix failures:
  php artisan config:clear
  php artisan migrate --force --pretend  (then real on staging)
  php artisan isp:production-audit
  php artisan test

### 6. Deliverables
- List what was fixed
- List what remains manual (env, KhudeBarta DLR URL, cron on server)
- Update deploy/PRODUCTION_CHECKLIST.md if new env keys added
```

---

## Prompt (বাংলা)

```
/var/www/isp-platform প্রোডাকশনের জন্য পুরো অডিট করো।

১) এক এক করে মডিউল চেক: বাগ ফিক্স + টেস্ট
২) মেনু গুছানো: গ্রুপ, হাব, ডুপ্লিকেট বাদ
৩) ড্যাশবোর্ড: dark/light, মোবাইল, উইজেট
৪) অপ্রয়োজনীয় কোড বাদ
৫) php artisan isp:production-audit && php artisan test
৬) কী ফিক্স হলো + সার্ভারে কী ম্যানুয়াল তা লিস্ট করো
```

---

## Quick command

```bash
cd /var/www/isp-platform
php artisan isp:production-audit
php artisan test
```
