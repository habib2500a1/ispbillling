# ISP Radiant — Android Super App Audit & Modernization Blueprint

**App:** `mobile/isp_radiant` (Flutter → single APK)  
**Version audited:** 2.6.7+37  
**Backend:** `https://bill.flixbd.xyz/api/v1`  
**Date:** June 2026

---

## 1. Complete App Audit

| Area | Finding | Severity |
|------|---------|----------|
| Architecture | Single Flutter APK; customer + staff native; reseller WebView; technician APIs exist but no native shell (fixed in Phase 1) | High |
| Auth | Unified `POST /mobile/login`; role chosen at hub; staff sub-modes stored in `staff_mode` prefs | Medium |
| Navigation | Bottom tabs per persona; no global role switcher UI (fixed in Phase 1) | Medium |
| Design | Material 3 tokens, light/dark toggle, gradient headers — partial premium polish | Low |
| Performance | Riverpod + repository pattern on dashboards; some screens still monolithic | Medium |
| Offline | Queue + `/mobile/sync` implemented; collection UI did not enqueue (fixed in Phase 1) | High |
| Push | FCM flag in config; placeholder device ID in client | High |
| GIS | Backend `/staff/gis/*` live; no in-app map widget (Phase 1: search + external maps) | Medium |
| AI | Customer AI wired; staff AI API live, no screen (fixed in Phase 1) | Medium |

**Verdict:** Strong billing/operations foundation (~43 screens, 50+ staff API methods). Super App gap was role orchestration, technician shell, discoverability, and offline wiring—not missing backend.

---

## 2. Existing Feature Inventory

### Customer (native — 11 modules)
- Dashboard (usage chart, due, package, notices)
- Pay bill (bKash, Nagad, SSLCommerz, card via web checkout)
- Bills & invoice detail
- Usage statistics
- Speed test
- ONU status / reboot
- Tickets + thread
- Packages browse
- Password change
- AI assistant (`/customer/ai/ask`)
- 4-tab bottom nav: Home · Pay · Support · Account

### Staff / Admin (native — 23 screens)
- Executive dashboard (KPIs, 7-day revenue, zone chart, modules grid)
- Billing hub (due, invoices, collections, receipts PDF)
- Collection / receive bill (cash, bKash, Nagad, bank)
- Clients list + Customer 360 (edit, ONU, usage live, network suspend/reconnect)
- Tickets, tasks, approvals, expenses
- Monitoring + NOC dashboard
- Packages CRUD, reports, comms (SMS bulk, broadcast)
- Inventory POS, MFS SMS ingest, team discounts
- 5-tab nav: Home · Billing · Collection · Support · Tasks

### Reseller
- WebView portal only (`ResellerWebPortalScreen`) — reseller API exists for future native modules

### Collector / NOC (staff sub-modes)
- Logic in `staff_mode`: `admin` | `collector` | `noc` — affects monitoring vs NOC screen, MFS listener
- Collector wallet, expenses via `/collector/*`

### Technician (backend only → Phase 1 native)
- `/technician/field-visits`, installations, device register

---

## 3. Hidden Feature Analysis

| Hidden capability | Location | Why hidden |
|-------------------|----------|------------|
| Staff AI copilot | `/staff/ai/ask`, `/staff/ai/dashboard` | No Flutter screen |
| GIS search & map payload | `/staff/gis/search`, `/staff/gis/map` | Not exposed in app |
| Offline sync queue | `OfflineSyncService` | Never called from receive-bill |
| Realtime WebSocket | `RealtimeService` + `/mobile/realtime` | Silent background refresh only |
| MFS SMS auto-ingest | `MfsSmsListener` | Admin/collector mode only; no onboarding |
| Barcode scanner | `barcode_scan_screen.dart` | Used in inventory, not technician ONU scan |
| Staff network control | suspend/reconnect/extend | Buried in customer detail |
| Orphan screens | `app_welcome_screen`, `client_ping_screen` | Unwired |

---

## 4. Duplicate Feature Analysis

| Duplicate | Instances | Recommendation |
|-----------|-----------|----------------|
| Customer search | `StaffClientsScreen`, collection search, billing due lists | Keep; unify via Global Search (Phase 1) |
| Monitoring vs NOC | Two dashboards, mode-dependent routing | Keep; expose via role switcher |
| Pay flows | Customer pay + staff receive bill + web `/pay` | Keep separate personas; same payment APIs |
| Login paths | Hub + direct `LoginScreen` | Keep hub as single entry |

---

## 5. Missing Feature Analysis (vs MyGP / bKash / UISP / Splynx)

| Feature | Status | Priority |
|---------|--------|----------|
| Multi-role switcher in one session | Phase 1 (staff interfaces) | P0 |
| Native technician field app | Phase 1 shell | P0 |
| Native reseller dashboard | WebView only | P1 |
| In-app map (Google/OSM) | External maps via `url_launcher` | P1 |
| FCM push registration | Stub | P0 |
| Referral program UI | Not in app | P2 |
| Customer referral / loyalty | Backend unknown | P2 |
| Biometric login | Config flag only | P2 |
| Cached offline profile/bills/tickets | Partial (sync queue only) | P1 |
| CSAT on tickets | Web admin | P2 |

---

## 6. Customer Mode Design

**Dashboard cards:** Internet status · Due · Next bill · Package · Notices · Open tickets  
**Bottom nav:** Home · Pay · Support · Account  
**Modules:** Existing screens + enhanced discoverability via quick actions  
**Payments:** Preserve `payment_checkout_screen` — bKash, Nagad, Rocket, SSLCommerz, card  
**Premium UX:** Glass cards on gradient header (existing `IspUiKit`), skeleton loaders on dashboard  

---

## 7. Reseller Mode Design

**Current:** WebView to `/reseller/login`  
**Target (P1):** Native shell using `/reseller/me`, customers, due reports, commission — same APK, separate Sanctum token after reseller login  
**Dashboard KPIs:** Revenue · Collection · Active/due customers  
**Modules:** Customer mgmt · Billing · Collections · Commission reports · Tickets  

---

## 8. Staff Mode Design

**Dashboard:** Today KPIs · Collection overview · Finance/reseller summary · Module grid · Tickets/tasks  
**Bottom nav:** Home · Billing · Collection · Support · Tasks  
**Workspace modes:** Admin (full) · Collector (wallet-focused) · NOC (network dashboard)  
**New (Phase 1):** Global search · Operations AI · Role switcher in header/profile  

---

## 9. Technician Mode Design

**Shell:** `TechnicianHomeScreen` — Home · Visits · Search · Profile  
**Dashboard:** Assigned visits · Today count · Quick: tickets, customer lookup, AI  
**Modules:** Field visits (start/complete/navigate) · GIS search · Customer 360 · Ticket list  
**Future:** ONU barcode scan · Photo upload · Signal monitoring · Asset scanner  

---

## 10. AI Assistant Design

| Persona | Endpoint | Sample intents |
|---------|----------|----------------|
| Customer | `/customer/ai/ask` | Due balance, invoices, package, create ticket |
| Staff | `/staff/ai/ask` | Today's collection, pending tickets, due customers |
| Technician | Same staff endpoint | Assigned tickets, weak signal, nearby faults |

**UI:** Chip shortcuts + free text; render reply + optional cards table from orchestrator  

---

## 11. GIS Integration Design

**Phase 1:** `StaffGlobalSearchScreen` — `/staff/gis/search` + customer search; open Google Maps via coordinates  
**Phase 2:** Embed `google_maps_flutter` or `flutter_map` (OSM) with `/staff/gis/map` GeoJSON payload  
**Phase 3:** Fiber route overlay, OLT/ONU pins, turn-by-turn  

---

## 12. Communication Center Design

**Existing:** Staff comms (SMS bulk, broadcast), customer notices from config, ticket threads  
**Push:** Wire FCM token → `/staff/devices` or `/customer/devices`  
**Channels:** SMS · WhatsApp (web/deep link) · Email (admin) · In-app notices · Push  
**Alert types:** Billing · Maintenance · Outage · Ticket updates  

---

## 13. Search Architecture

```
GlobalSearchScreen
├── staff/customer search → GET /staff/customers/search?q=
├── GIS index → GET /staff/gis/search?q=
└── (future) tickets, invoices, ONU serial → dedicated endpoints
```

Debounce 280ms · Target &lt;300ms on LAN · Cache last 10 queries in SharedPreferences  

---

## 14. Android Navigation Architecture

```
SplashGate → validate token
├── customer → CustomerHomeScreen (4 tabs)
├── staff → SuperAppNavigator.goStaffHome(staff_mode)
│   ├── admin|collector|noc → StaffHomeScreen (5 tabs)
│   └── technician → TechnicianHomeScreen (4 tabs)
└── login hub → LoginScreen(role) | ResellerWebPortal

RoleSwitcherSheet → save staff_mode → pushReplacement home shell
```

**Multi-role users:** Same staff token; Spatie roles from `/me` determine available interfaces. True customer+staff dual account requires separate logins (by design of unified login API).

---

## 15. Light Theme

- Primary: `DesignTokens.primary` / brand from remote config  
- Background: `#F1F5F9` page · white cards · subtle shadows  
- Gradient headers: indigo → violet (login) · blue (staff)  
- Typography: Google Fonts via `app_themes.dart`  

---

## 16. Dark Theme

- OLED black `#0B0F14` surface  
- Muted text `#94A3B8` · elevated cards `#151B23`  
- Toggle: `ThemeToggleTile` + `theme_controller.dart`  
- Reduce gradient opacity 40% in dark mode  

---

## 17. Material Design 3 System

- `useMaterial3: true` in `app.dart`  
- `NavigationBar` bottom tabs  
- Filled / outlined buttons · `InputDecorationTheme`  
- Dynamic color: optional Phase 2 (`dynamic_color` package)  

---

## 18. Component Library

| Component | Path |
|-----------|------|
| `IspUiKit` | gradient header, search bar |
| `AppShell` | tab scaffold |
| `AppCard`, `ModuleTile` | dashboard |
| `CustomerSearchResultTile` | search results |
| `PaymentSuccessSheet` | post-payment |
| `Skeleton` loaders | dashboard cold start |
| `RoleSwitcherSheet` | multi-interface staff |

---

## 19. Performance Strategy

| Target | Approach |
|--------|----------|
| Launch &lt;1s | `validateSession(quick: true)` 8s cap; parallel config load |
| Screen &lt;500ms | Repository cache; skeleton first paint |
| Search &lt;300ms | 280ms debounce; parallel customer+GIS |
| Low RAM | Lazy tab bodies; dispose WebView on pop |
| Slow network | Offline queue; retry on connectivity |
| Android 8+ | minSdk 26 recommended; test on 2GB devices |

---

## 20. Features Preserved

- All login flows (`/mobile/login`, customer/staff/reseller)
- Customer billing, pay, tickets, ONU, speed test, AI
- Staff billing, collection, CRM, tickets, tasks, NOC, monitoring
- Reseller WebView portal
- API contracts unchanged
- Session storage, token refresh, remote config sync

---

## 21. Features Improved (Phase 1 — implemented)

- **Role resolver** from `/me` Spatie roles  
- **Role switcher** for admin / collector / NOC / technician  
- **Technician native shell** with field visits  
- **Staff AI screen**  
- **Global search** (customers + GIS)  
- **Offline collection enqueue** on network failure  
- **SuperAppNavigator** central routing  

---

## 22. Features Added (roadmap)

| Phase | Items |
|-------|-------|
| 1 ✅ | Role architecture, technician shell, AI, search, offline wire |
| 2 ✅ | Enhanced push, OSM GIS map, native reseller shell |
| 3 ✅ | Offline dashboard cache, biometric unlock |
| 4 ✅ | Referral share, ticket CSAT, technician barcode scan |
| 5 ✅ | GlassCard component, premium KPI cards on reseller/technician |

---

## 23. Developer Implementation Notes

1. **Do not break APIs** — all changes are client-side routing + new screens calling existing endpoints.  
2. **Staff mode key:** `staff_mode` in secure storage (`admin`|`collector`|`noc`|`technician`).  
3. **Role detection:** `RoleCapabilities.fromMe(await api.staffMe())`.  
4. **Technician middleware:** roles `isp-engineer`, `isp-support`, etc. — same staff token.  
5. **Collector middleware:** requires `cashier` role or `collector` token ability.  
6. **Testing:** Use staff user with multiple Spatie roles to verify switcher.  
7. **Build:** `flutter build apk --release` from `mobile/isp_radiant`.  
8. **Config:** Feature flags from `GET /mobile/config` → `RemoteConfig`.  
9. **Filament parity:** Admin web ISP OS hubs are separate; mobile consumes REST only.  
10. **Next PR:** Add `integration_test/` for login → role switch → technician visit list.

---

*Super App v2.8.0+39 — all phases implemented. Add `google-services.json` for live FCM tokens.*
