# ISP Radiant Super App — Complete UI/UX Rebuild (v3.0.0)

**Mission:** Replace 100% of visual/UI layer while preserving all APIs, business logic, billing, tickets, payments, and workflows.

**Stack:** Flutter 3.12+ · Single APK · Customer / Staff / Reseller / Technician  
**Design language:** Stripe × Linear × Revolut × Material 3 · Radiant 3.0 Design System

---

## 1. Complete App Audit

| Metric | Count |
|--------|------:|
| Dart files (`lib/`) | 125 |
| Screen files | 49 |
| Services | 10 |
| Feature repositories | 7 |
| API methods (`ApiService`) | ~107 |

### Screens by role

**Shared:** `splash_gate`, `login_hub_screen`, `login_screen`, `server_setup_screen`, `payment_checkout_screen`, `ticket_thread_screen`

**Customer (13):** `customer_home_screen`, pay, bills, invoice detail, tickets, speed test, usage, ONU, packages, password, AI, referral

**Staff (27):** home shell, billing hub, collection, tickets, tasks, profile, clients, customer detail/edit/add, receive bill, receipt, approvals, expense, create ticket, packages, reports, comms, monitoring, NOC, inventory POS, MFS SMS, team discount, AI, global search, GIS map

**Technician (1 shell + reused staff):** `technician_home_screen` + tickets, search, profile, barcode, AI, clients

**Reseller (2):** `reseller_home_screen`, `reseller_web_portal_screen`

### Navigation flow

```
SplashGate → session validate → role route
  customer  → CustomerHomeScreen
  staff     → StaffHomeScreen | TechnicianHomeScreen (by staff_mode)
  reseller  → ResellerHomeScreen
  none      → LoginHubScreen → LoginScreen(role)
```

### State management

- **Riverpod:** theme, connectivity, customer dashboard
- **StatefulWidget + ApiService:** majority of staff/customer feature screens
- **Repositories:** customer dashboard, staff dashboard, billing, customers, comms, monitoring

### Integrations preserved

REST `/api/v1/*`, WebView payments, offline collection sync, MFS SMS ingest, OSM GIS map, barcode scanner, biometric unlock, speed test, push device registration, PDF receipts.

---

## 2. Old UI Problems

| Problem | Impact |
|---------|--------|
| Legacy ISP blue (`#1565C0`) admin-template aesthetic | Feels like 2018 billing software, not 2025 SaaS |
| Duplicate theme systems (`AppTheme` shim + `DesignTokens`) | Inconsistent colors/typography across screens |
| Flat white cards + gradient headers everywhere | No visual hierarchy; all screens look identical |
| Standard Material `NavigationBar` (68px, always visible labels) | Generic Android feel |
| Staff dashboard = module grid + table KPIs | Desktop admin ported to mobile |
| Monolithic 500–800 line screen files | Hard to evolve UI without breaking logic |
| Customer 2s usage poll | Battery/CPU drain on low-end devices |
| Reseller list rows show raw `e.toString()` | Unfinished partner experience |
| No unified page transitions | Jarring navigation |
| Orphan screens (`app_welcome_screen`, `client_ping_screen`) | Dead code / confusion |

---

## 3. New Design System — Radiant 3.0

**Location:** `lib/design_system/`

| Token | Light | Dark |
|-------|-------|------|
| Brand | `#6366F1` Indigo | same |
| Accent | `#8B5CF6` Violet | same |
| Background | `#FAFAFA` | `#09090B` OLED |
| Surface | `#FFFFFF` | `#18181B` |
| Typography | Inter (Google Fonts) | Inter |

**Components:**

- `RadiantGlassCard` — glassmorphism + blur
- `RadiantKpiTile` — metric tiles (replaces StatCard layout)
- `RadiantMeshBackground` — hero mesh gradients
- `RadiantStatusChip` / `RadiantQuickChip`
- `RadiantSectionHeader`
- `RadiantSkeleton` / `RadiantDashboardSkeleton`
- `RadiantTheme` — app-wide Material 3 theme

**Extension:** `context.radiant`, `context.isDark`

---

## 4. New Navigation System

**`RadiantSuperShell`** (`lib/design_system/navigation/radiant_super_shell.dart`)

- Floating pill bottom bar (16px inset, 28px radius)
- **Center FAB** for primary action (Customer: Pay, Staff: Collect — phased)
- `wire:poll.visible` pattern mirrored in mobile: pause background refresh when tab hidden
- `RadiantPageRoute` — fade + slide transitions (280ms, easeOutCubic)
- `AppShell` delegates staff/technician to RadiantSuperShell

### Per-role nav

| Role | Tabs | Center FAB |
|------|------|------------|
| Customer | Home · Billing · Support · Profile | Quick Pay |
| Staff | Home · Billing · Collection · Support · Task | Collect (existing FAB) |
| Technician | Home · Visits · Search · Profile | Scan (phased) |
| Reseller | Home · Customers · Commission · More | — |

**Role switcher:** unchanged logic (`role_switcher_sheet` + `SuperAppNavigator`) — UI refresh in Phase 2.

---

## 5. Customer UI Redesign

**Status: Phase 1 COMPLETE** (`customer_home_screen.dart` v3)

| Before | After |
|--------|-------|
| Blue gradient header + 4-icon row in AppCard | Mesh hero + glass due card + gradient avatar |
| StatCard 2×2 grid | RadiantKpiTile dynamic widgets |
| Bottom nav: Home/Pay/Support/Speed | Home/Billing/Support/Profile + FAB Pay |
| Speed test as tab | Speed test in quick chips + profile |
| 2s traffic poll | 5s poll (performance) |

**Modules (navigation preserved):** Billing tab → `CustomerBillsScreen`, Pay → FAB → `CustomerPayScreen`, Support → tickets, Profile → settings hub.

**Remaining Phase 2 screens:** pay, bills, tickets, usage, ONU, packages, password, AI — swap layouts to Radiant components.

---

## 6. Reseller UI Redesign

**Status: Phase 1 PARTIAL** (`reseller_home_screen.dart`)

- RadiantSuperShell navigation
- Mesh hero header
- RadiantKpiTile metrics
- Glass quick actions card

**Phase 2:** Customer/commission list tiles, charts, commission analytics.

---

## 7. Staff UI Redesign

**Status: Phase 1 NAV ONLY** — `AppShell` → RadiantSuperShell

**Phase 2 targets:**

- Replace `IspUiKit.gradientHeader` on staff home dashboard
- Module grid → contextual drawer + smart search entry
- Billing/collection/ticket screens → Radiant forms
- NOC/monitoring → live widget cards

---

## 8. Technician UI Redesign

**Status: Phase 1 NAV ONLY** — uses updated AppShell

**Phase 2 targets:**

- Visit cards with map preview chips
- Nearby faults/customers widgets
- Asset scanner FAB integration
- Field-ops color accent (cyan/teal)

---

## 9. Dashboard Redesign

### Customer (done)

- Dynamic widgets: connection status, due amount hero, live traffic chart, AI entry, notices
- Personalized greeting (name + package)

### Staff (planned)

- Task-first layout (not module grid first)
- KPI strip + revenue sparkline
- “Today” section: collections, tickets, approvals

### Technician (planned)

- Assigned visits timeline
- Nearby map snippet
- Quick: navigate, complete, scan

### Reseller (partial)

- Revenue / due / collection KPIs
- Partner quick actions

---

## 10. Component Library

```
lib/design_system/
├── radiant_tokens.dart
├── radiant_theme.dart
├── components/
│   ├── radiant_glass_card.dart
│   ├── radiant_kpi_tile.dart
│   ├── radiant_section.dart
│   └── radiant_skeleton.dart
└── navigation/
    └── radiant_super_shell.dart
```

**Phase 2 components:** `RadiantFormField`, `RadiantListTile`, `RadiantChart`, `RadiantDrawer`, `RadiantSearchBar`, `RadiantAiSheet`.

---

## 11. Light Theme

Premium SaaS light: `#FAFAFA` background, white glass surfaces, indigo primary, subtle zinc borders, soft glow shadows.

Applied via `RadiantTheme.light` in `app.dart`.

---

## 12. Dark Theme

OLED `#09090B` base, `#18181B` surfaces, reduced glow opacity, light text `#FAFAFA`.

Default mode remains dark (`ThemeController`).

---

## 13. Animation System

| Animation | Duration | Curve |
|-----------|----------|-------|
| Page push | 280ms | easeOutCubic |
| Page pop | 180ms | easeOutCubic |
| Nav icon select | 180ms | scale 1.05 |
| Skeleton pulse | 900ms | easeInOut |

**Phase 2:** staggered list reveal, chart draw, FAB expand menu, hero transitions on KPI tap.

---

## 14. Performance Strategy

| Target | Approach |
|--------|----------|
| Launch < 1s | SplashGate parallel config + session validate (existing) |
| Screen load < 500ms | Skeleton first paint; repository cache |
| Search < 300ms | Debounced staff global search (existing) |
| Low-end devices | Lazy lists, 5s customer poll (was 2s), offline cache |
| Slow network | OfflineBanner, offline collection queue, dashboard cache |

---

## 15. Features Preserved (100%)

- All `ApiService` endpoints
- Auth + token refresh + staff_mode persistence
- Role resolver + multi-interface staff
- Payment WebView checkout
- Offline sync collections
- MFS SMS listener
- Ticket thread + CSAT
- Technician field visits API
- GIS map + barcode
- Biometric unlock
- Remote config / branding
- Speed test service

---

## 16. Features Improved

| Feature | Improvement |
|---------|-------------|
| Customer home | New IA, profile tab, FAB pay, cleaner modules |
| Login hub | Glass role cards, mesh hero, skeleton load |
| Navigation | Floating pill bar, visible-tab awareness pattern |
| Reseller home | Native KPI tiles vs raw glass cards |
| Theme | Single Radiant token source + Inter typography |
| Performance | Slower usage poll, lighter visual effects on low-end optional |

---

## 17. Features Added

| Feature | Description |
|---------|-------------|
| Radiant 3.0 Design System | Full token + component library foundation |
| RadiantSuperShell | Unified nav across all personas |
| RadiantPageRoute | Consistent transitions |
| Customer Profile tab | Dedicated account hub (password, usage, network, AI) |
| Quick pay FAB | One-tap payment from any customer tab |

---

## 18. Developer Implementation Plan

### Phase 1 — Foundation ✅ (v3.0.0+40)

- [x] Audit documented
- [x] `RadiantTokens` + `RadiantTheme`
- [x] Core components (glass, KPI, skeleton, mesh)
- [x] `RadiantSuperShell` + `RadiantPageRoute`
- [x] Customer home complete rebuild
- [x] Login hub complete rebuild
- [x] Reseller shell + dashboard header/KPIs
- [x] Staff/technician nav shell upgrade

### Phase 2 — Feature screens (2–3 weeks)

- [ ] Customer: pay, bills, tickets, usage, ONU, packages, AI screens
- [ ] Staff home dashboard rebuild (keep module key → route map)
- [ ] Staff billing, collection, receive bill forms
- [ ] Technician home + visit cards
- [ ] Login + splash visual polish
- [ ] `RadiantFormField`, `RadiantListTile`

### Phase 3 — Staff depth (2 weeks)

- [ ] CRM customer 360 layout
- [ ] NOC/monitoring live widgets
- [ ] Inventory POS + barcode UI
- [ ] GIS map marker UI
- [ ] Role switcher sheet redesign

### Phase 4 — Polish & ship (1 week)

- [ ] Lottie/micro-interactions on pay success
- [ ] FCM + `google-services.json`
- [ ] Integration tests: login → role switch → pay → ticket
- [ ] APK publish `scripts/build-mobile-docker.sh`
- [ ] Remove legacy `IspUiKit`, `AppTheme` shim, orphan screens

### Migration rules for developers

1. **Never change API method signatures** in `api_service.dart` during UI work.
2. **Keep navigation targets** — module keys from `/staff/dashboard` must map to same screens.
3. **Use Radiant components** for all new UI; do not import `isp_ui_kit.dart` in new code.
4. **Preserve `embedded` / `active` flags** on tab child screens.
5. **Test offline path** after any collection/billing UI change.

---

## File map (Phase 1 touched)

| File | Change |
|------|--------|
| `lib/design_system/**` | NEW |
| `lib/app.dart` | RadiantTheme |
| `lib/screens/customer_home_screen.dart` | Full rebuild |
| `lib/screens/login_hub_screen.dart` | Full rebuild |
| `lib/screens/reseller_home_screen.dart` | Shell + dashboard |
| `lib/widgets/app_shell.dart` | RadiantSuperShell delegate |
| `pubspec.yaml` | 3.0.0+40 |

---

**Final goal:** Users opening v3.0 should not recognize the old app layout. Phase 1 delivers new shell + customer experience; Phases 2–4 complete the remaining 40+ screens.
