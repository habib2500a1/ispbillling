# ISP Platform — Rent / Sell করার সময় Security (কোড নিরাপদ রাখা)

এই ডকুমেন্ট **স্ক্রিপ্ট ভাড়া (SaaS)** বা **বিক্রি (on-premise)** করার সময় আপনার ও গ্রাহকের নিরাপত্তার চেকলিস্ট।

> আইনি চুক্তি (license agreement) আলাদা আইনজীবী/টেমপ্লেট দিয়ে করুন — এখানে শুধু **টেকনিক্যাল** নির্দেশনা।

---

## Rent (SaaS) vs Sell (বিক্রি) — কোনটা নিরাপদ?

| | **Rent (আপনি হোস্ট করেন)** | **Sell (তাদের সার্ভারে)** |
|---|---------------------------|---------------------------|
| কোড এক্সপোজার | কম — তারা শুধু `/admin` ব্যবহার করে | **বেশি** — পুরো `app/`, `.env` হাতে |
| আপডেট | আপনি কেন্দ্রীয়ভাবে দেন | তারা নিজে pull করতে পারে (কন্ট্রোল হারান) |
| ডেটা আইসোলেশন | **Multi-tenant** (`tenant_id`) বাধ্যতামূলক | প্রতি ISP আলাদা ইনস্টল (= আলাদা DB) |
| সুপারিশ | এক ডোমেন, subdomain/slug per ISP | এক ক্লায়েন্ট = এক VPS, source **না** দিন (compiled deploy) |

**আমাদের কোডবেস Rent-এর জন্য বেশি উপযুক্ত** (multi-tenant + RBAC + encrypted settings)।

---

## কোডে যা ইতিমধ্যে আছে (বিক্রেতা/অপারেটর সুরক্ষা)

| স্তর | ফিচার |
|------|--------|
| Tenant | `BelongsToTenant` — এক ISP অন্যের ডেটা দেখতে পারে না |
| Admin | Spatie roles/permissions, super-admin আলাদা |
| 2FA | Staff 2FA (`EnsureStaffTwoFactorVerified`) |
| Secrets | `app_settings` **encrypted**; API keys hash; webhook HMAC per tenant |
| API | Sanctum tokens (একবার দেখানো); Reseller API `rsk_*` hashed |
| Webhook | `X-ISP-Signature` (HMAC + timestamp) বা `X-ISP-Webhook-Secret` |
| Reseller | IP allowlist, portal 2FA, activity logs |
| Production | `isp:production-audit`, webhook secrets fail-closed |

বিস্তারিত: `docs/API_CONFIGURATION.md`, `docs/reseller-enterprise/SECURITY.md`

---

## Rent (SaaS) — আপনি যা করবেন

1. **এক ISP = এক tenant** — কখনো দুই ISP এক tenant-এ দেবেন না।
2. **SSL** — সব ডোমেন HTTPS; `APP_URL` সঠিক।
3. **`.env` শেয়ার করবেন না** — গ্রাহককে শুধু admin login দিন।
4. **Super-admin** শুধু আপনার টিম; ISP-কে `isp-admin` / scoped roles।
5. **API configuration** (`/admin/api-configuration`) — tenant HMAC regenerate; REST token প্রয়োজনে।
6. **Backup** — DB daily encrypted backup; restore টেস্ট।
7. **আপডেট** — staging → production; `composer install --no-dev` on server।

```bash
php artisan isp:production-audit
php artisan isp:generate-webhook-secrets   # প্রথম deploy
```

---

## Sell (on-premise) — কোড চুরি/রিসেল ঠেকাতে

1. **Source code দেবেন না** — শুধু deploy package (git exclude `.env`, `storage`, keys).
2. **Private Git** — buyer-কে read access না দিয়ে আপনি deploy করুন, অথবা **obfuscated release zip** (optional).
3. **License চুক্তি** — domain bind, support মেয়াদ, reverse-engineering নিষেধ।
4. **`.env.example` only** — প্রতিটি ক্লায়েন্ট নিজের secrets generate।
5. **Remove** আপনার `APP_KEY`, webhook secrets, SMS keys — তারা নিজের বানাবে।
6. **License (sell)** — RSA-signed `ISP_LICENSE_KEY` + domain bind (`isp:issue-license`). **Rent** — `ISP_DEPLOYMENT_MODE=saas`, enforce off.

---

## .env — দুই মোড (আপনি “২টাই” করবেন)

### Rent — আপনার সার্ভার (flixbd / multi-ISP)

```env
ISP_DEPLOYMENT_MODE=saas
ISP_LICENSE_ENFORCE=false
```

Admin: **Settings → Deployment & license** — “SaaS (rent)” দেখাবে।  
API: **Settings → API configuration** — per-tenant HMAC।

### Sell — কাস্টমারের সার্ভার

**আপনি (vendor):**

```bash
php artisan isp:license:generate-keys    # একবার
php artisan isp:issue-license bill.customer.com --expires=2027-12-31
```

**কাস্টমার `.env`:**

```env
ISP_DEPLOYMENT_MODE=on_premise
ISP_LICENSE_ENFORCE=true
ISP_LICENSE_KEY=<paste signed key>
```

ভুল domain / মেয়াদ শেষ → admin 503 (webhooks চলতে পারে)।

---

## গ্রাহককে যা দেবেন / যা দেবেন না

| দিন | দেবেন না |
|-----|---------|
| Admin URL + role-based users | Root SSH / `.env` (rent মোডে) |
| Operator guide (`BANGLA_OPERATOR_GUIDE.md`) | Database dump অন্য ISP-এর |
| Support channel | `storage/logs` with secrets |
| Sandbox payment keys first | Production bKash/SIP password in email |

---

## Payment / SIP / API — shared hosting ঝুঁকি

- **কখনো** এক সার্ভারে `APP_DEBUG=true` production-এ রাখবেন না।
- `storage/`, `bootstrap/cache` — `www-data` writable, world-readable নয়।
- Cron/queue আলাদা user বা supervisor — root দিয়ে `artisan` চালাবেন না (permission bug হয়েছিল)।
- Webhook URL public — শুধু HMAC/secret দিয়ে guard (`WebhookAuthenticator`)।

---

## দ্রুত চেকলিস্ট (প্রতি নতুন ISP onboard)

- [ ] নতুন `tenant` + slug
- [ ] Admin users tenant_id সেট
- [ ] `isp:production-audit` clean (production)
- [ ] Webhook HMAC regenerate (API configuration)
- [ ] Payment gateway tenant-scoped keys
- [ ] 2FA policy for staff
- [ ] Backup + monitoring (disk, failed jobs)

---

## সাহায্য

- Ops: `deploy/PRODUCTION_CHECKLIST.md`
- API: `docs/API_CONFIGURATION.md`
- Sheba-Fi parity: `docs/SHEBA_FI_MENU_MAP.md`
