# Professional ISP Mobile Architecture (Native + API)

**Principle:** Website/Admin = main ERP. Android app = native API client. All business logic stays on **Laravel backend**; the app displays data, calls APIs, and syncs in real time.

```
┌─────────────────────────────────────────────────────────────────┐
│  Android Apps (Flutter → optional Kotlin modules later)         │
│  Customer │ Collector │ Technician │ NOC │ Admin (unified v1)   │
└────────────────────────────┬────────────────────────────────────┘
                             │ HTTPS REST (+ WebSocket Phase 3)
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  API Gateway  /api/v1/*                                         │
│  Sanctum auth │ Role middleware │ Throttle │ Tenant scope       │
└────────────────────────────┬────────────────────────────────────┘
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  Laravel Services (single codebase with Filament admin)         │
│  Billing │ Collector │ Portal │ ONU │ MikroTik │ Tickets │ Jobs │
└────────────────────────────┬────────────────────────────────────┘
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  MySQL/PostgreSQL │ Redis cache/queue │ Storage │ FCM tokens    │
└────────────────────────────┬────────────────────────────────────┘
                             ▼
                    MikroTik │ OLT/ONU webhooks │ Payment gateways
```

## App products (target)

| App | Primary users | Status in RADIANT ISP |
|-----|---------------|------------------------|
| **Customer** | Subscribers | **Phase 1 — live** (dashboard, bills, pay, usage, tickets, packages) |
| **Collector** | Cashiers, collectors | **Phase 1 — partial** (search, collect, wallet, expenses API exists) |
| **Technician** | Field engineers | **Phase 2 — partial** (field visits API exists) |
| **NOC** | Network team | **Phase 3 — planned** (online clients API started; OLT/ONU APIs via webhooks) |
| **Admin** | Owner, managers | **Phase 1 — partial** (staff dashboard; full control stays in Filament until Phase 2) |

Today one Flutter APK (`isp_radiant`) covers **Customer + Staff** with role switch at login. Split APKs or flavors come in Phase 2.

## API surface (current `/api/v1`)

### Public
- `GET /health` — API status
- `GET /mobile/config` — app name, links, features, ticket defaults
- `POST /mobile/login` — unified login (`role: staff|customer`)

### Customer (`auth:sanctum` + customer guard)
- Auth: login, logout, **refresh** (Phase 1)
- Dashboard, usage/live, bills, pay initiate, tickets, packages, profile password
- FCM: `POST /customer/devices`

### Staff (`auth:sanctum`)
- Dashboard, customers search/list/detail, tickets, tasks, online clients
- Collector: collections, wallet, expenses, settlements, daily closing
- Technician: field visits, devices

### Webhooks (server ← network)
- Netflow, ONU optical ingest, payments — feed realtime data into DB

## Sync model

| Data | Source of truth | App sync |
|------|-----------------|----------|
| Customers, invoices, payments | Laravel DB | REST pull + push on actions |
| Live bandwidth / PPP | Radius/MikroTik services | `GET /customer/usage/live`, portal dashboard payload |
| ONU signal | ONU ingest webhook | Portal + future `GET /customer/onu` |
| Tickets | Support module | REST CRUD |
| Push alerts | FCM + `DeviceTokenService` | Register token on login |

**Offline (Phase 2):** SQLite queue on device for collector/technician; `POST` replay with idempotency keys.

**Realtime (Phase 3):** Laravel Reverb / Pusher — channels: `tenant.{id}.payments`, `onu.{id}.signal`, `router.alerts`.

## Security (production checklist)

| Feature | Status |
|---------|--------|
| Sanctum bearer tokens | Done |
| Token refresh | Phase 1 endpoint |
| Role-based routes | Done (`EnsureSanctumCustomer`, Collector, Technician) |
| HTTPS | Production |
| SSL pinning | Phase 2 (Flutter) |
| Biometric unlock | Phase 2 (local only) |
| Device binding | Phase 2 |
| JWT | Not required — Sanctum PAT is sufficient for mobile |

## Flutter app structure (target)

```
lib/
  config/          # API base, remote config
  core/            # api client, retry, auth interceptor, cache
  features/
    customer/      # dashboard, billing, usage, support
    collector/     # collection, settlement
    technician/    # visits, install
    admin/         # KPIs, controls
  shared/          # widgets, theme (demo UI)
```

## Implementation phases

### Phase 1 — Foundation (current → 4 weeks)
- [x] Native UI, no website redirect for core flows
- [x] REST APIs for customer + staff dashboard
- [x] Route cache + deploy script
- [ ] Token refresh + 401 auto-retry in app
- [ ] FCM register on login
- [ ] Customer ONU endpoint from portal service
- [ ] API integration tests per module

### Phase 2 — Role apps & offline (6–8 weeks)
- App flavors: customer-only, staff-only APKs
- Collector offline queue + sync
- Technician: installation checklist API, photo upload
- Admin: suspend/reconnect PPP API wrappers
- PDF invoice download in-app

### Phase 3 — Realtime NOC (4–6 weeks)
- Reverb broadcasting for payments, ONU, router
- NOC dashboard app module
- Push for OLT down, weak signal, due reminder

### Phase 4 — Enterprise (ongoing)
- AI assistant (ticket triage)
- Anomaly detection on traffic
- TimescaleDB for metrics (optional)
- SSL pinning, crash reporting (Sentry/Firebase Crashlytics)

## Backend stack (this project)

| Layer | Choice |
|-------|--------|
| API | **Laravel 11** (not NestJS — same DB as admin) |
| Admin | Filament |
| Mobile | **Flutter** (fits multi-role + fast iteration) |
| DB | MySQL (PostgreSQL compatible) |
| Queue | Redis/database queues |
| Realtime | Laravel Reverb (configured, enable in Phase 3) |
| Push | FCM via `config/mobile.php` |

## Rules for developers

1. **Never** put business rules only in the app — duplicate in `app/Services/*`.
2. **Every** new screen needs an API contract in `routes/api.php` + feature test.
3. After deploy: `sudo scripts/refresh-api-routes.sh`
4. App builds: `./scripts/build-mobile-apk.sh https://your-domain`

## Related files

- `routes/api.php` — all mobile routes
- `app/Services/Mobile/*` — mobile-specific aggregations
- `mobile/isp_radiant/` — Flutter app
- `config/mobile.php` — token expiry, FCM, APK URL
