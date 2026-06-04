# Reseller Enterprise — Frontend Architecture

## Admin (Filament 3)

- `ResellerResource` — CRUD, wallet, commission tiers, quotas, API flags
- Relation managers: commissions, transfers, staff, children, activity
- Hub pages: `ResellersHub`, `ResellerWalletHubPage`, `ResellerReportPage`

## Partner portal (Blade + Tailwind)

- Layout: `resources/views/reseller/layout.blade.php`
- CSS: `public/css/reseller-portal-pro.css` (legacy `reseller-portal.css` / `reseller-portal-v2.css` removed)
- Enterprise views: `resources/views/reseller/enterprise/*`
- Real-time: existing `ResellerRealtimeController` + WebSockets (Laravel Echo)

## White-label

- `ResolveResellerWhiteLabel` middleware — subdomain + `?partner=` query
- `ResellerBrandingController` — logo, colors, custom domain, login message
- Customer pay/login pages consume `ResellerBranding::forCustomer()`

## Mobile

- Sanctum API parity for Flutter (`/api/v1/reseller/*`)
- Branding payload: `ResellerBranding::mobileBrandingPayload()`

## Dashboard KPIs

`ResellerPortalDashboardService` exposes wallet (main + bonus + credit), collection charts, alerts.
