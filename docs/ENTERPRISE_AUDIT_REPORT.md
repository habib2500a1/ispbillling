# Enterprise audit report — ISP Platform

**Stack:** Laravel 11 · Filament 3 · PostgreSQL · Redis · Livewire  
**Date:** 2026-05-19  
**Tests:** 311 passing (`php artisan test`)

This document is the honest status of a **production Laravel ISP platform**, not a greenfield React rewrite. The product already ships billing, GPON, MikroTik, portal, accounting, tickets, and mobile APIs on one codebase.

---

## Executive summary

| Area | Grade | Notes |
|------|-------|-------|
| Feature completeness | A- | Core ISP modules implemented; roadmap items (FCM, full RADIUS UI) remain |
| Code quality / tests | A | Broad feature + smoke coverage |
| Security | B+ | Hardened this pass (headers, webhooks, throttles); mass-assignment review ongoing |
| Performance | B+ | DB indexes + dashboard cache added; fast_mode bandwidth default |
| UI/UX | B+ | Aurora SaaS theme, dark/light, mobile dock; not a full Stripe-level redesign |
| DevOps | B | Docker reference + GitHub CI added; server cron/Horizon still manual |
| Realtime | B | Polling widgets (20–120s); no Socket.IO layer (not required for current stack) |

---

## What was implemented (this audit cycle)

### Security
- `SecurityHeaders` middleware (HSTS, nosniff, frame options, referrer policy)
- Webhook rate limit (`throttle:webhooks`, 120/min per IP)
- API mobile throttle (`throttle:api-mobile`, 180/min)
- `WebhookAuth` — fail closed in **production** when NetFlow/Optical secrets missing
- Configurable `TRUSTED_PROXIES` instead of blind `*` in production

### Performance
- Migration: indexes on `payments(tenant_id,status,paid_at)`, `invoices(tenant_id,status,due_date)`, `invoices(customer_id,status)`, `devices(customer_id,type)`
- `DashboardMetricsService::onlineUsersTrend` cached 2 minutes per tenant

### Navigation (prior pass)
- Hub-first HR & Inventory; duplicate sales-lead menu removed
- Dashboard hub hidden from sidebar (⌘K + Operations)
- Command palette extended for hidden reports

### DevOps
- `deploy/docker-compose.yml`, `deploy/Dockerfile`, `deploy/nginx.conf`
- `.github/workflows/ci.yml` — audit + PHPUnit

### Bugs fixed (prior passes)
- PPP login resolver cache invalidation
- Auto-suspend nullable boolean override
- Overdue invoice detection (SQLite/date safe)
- ONU smart-link when auto-provision disabled

---

## Architecture (actual)

```
┌─────────────────────────────────────────────────────────┐
│  Filament Admin (/admin)  ·  Portal  ·  Reseller        │
│  Livewire + Blade + admin-saas.css (dark/light)         │
└──────────────────────────┬──────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────┐
│  Laravel: Services, Policies, Observers, Queues         │
│  Sanctum API (customer / technician / collector)        │
└──────────────────────────┬──────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────┐
│  PostgreSQL  ·  Redis (cache/queue)  ·  Scheduler       │
│  MikroTik API · SNMP/OLT · Payment gateways · SMS       │
└─────────────────────────────────────────────────────────┘
```

**Not in scope for this codebase:** migrating admin to React/Next.js. That would be a separate multi-month product rewrite with no billing/feature parity guarantee.

---

## Module validation checklist

| Module | Status | Tests / entry |
|--------|--------|----------------|
| Auth / 2FA / RBAC | OK | `StaffControlTest`, `RbacCatalogTest` |
| Subscribers | OK | `SubscriberManagementTest`, PPP resolver |
| Billing / invoices | OK | `BillingEngineTest`, `BillCollectionDeskTest` |
| Payments / gateways | OK | bKash, Rocket, reconciliation |
| Collection / GPS | OK | `CollectionDeskReportTest`, collector API |
| MikroTik / bandwidth | OK | `BandwidthMonitoringTest`, session integrity |
| GPON / ONU | OK | `OpticalMonitoringTest`, BDCOM sync |
| Accounting / GL | OK | `AccountingSystemTest` |
| Support tickets | OK | `SupportTicketEnhancementsTest` |
| Notifications | OK | KhudeBarta unit + logs |
| Mobile API | OK | `MobileApiTest`, customer portal tests |
| Cron / automation | OK | `AutomaticProcess` + scheduler |

---

## Manual production steps (cannot be automated here)

1. Set all webhook secrets in `.env` (see `isp:production-audit` warnings)
2. `TRUSTED_PROXIES` = load balancer IPs
3. `APP_DEBUG=false`, `APP_URL` HTTPS
4. Cron + Horizon on server (`deploy/scheduler-cron.example`, `laravel-horizon.service.example`)
5. KhudeBarta DLR URL in provider portal
6. `php artisan migrate --force` on staging then production
7. `php artisan config:cache` after env final

---

## Recommended roadmap (priority)

1. **P0** — Mass-assignment hardening on `Customer` / `Payment` / `Invoice` (guarded financial fields)
2. **P1** — Consolidate `DashboardMetricsService` KPI queries (single aggregate vs 10+ counts)
3. **P1** — Sentry / OpenTelemetry + structured logging
4. **P2** — FCM push for collector/technician apps
5. **P2** — Installment desk UI, FUP auto-throttle
6. **P3** — Optional React customer portal (keep admin on Filament)

---

## Commands

```bash
cd /var/www/isp-platform
php artisan config:clear
php artisan migrate --force
php artisan isp:production-audit
php artisan test
```

See also: `docs/PRODUCTION_AUDIT_PROMPT.md`, `deploy/PRODUCTION_CHECKLIST.md`.
