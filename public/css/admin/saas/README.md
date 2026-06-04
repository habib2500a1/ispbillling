# Admin SaaS CSS modules

Edit these files instead of one giant `admin-saas.css`.

| File | Contents |
|------|----------|
| `01-tokens.css` | Colors, radii, `--isp-*` variables |
| `02-sidebar.css` | Sidebar, nav, accordion, search |
| `03-dashboard-widgets.css` | Dashboard stats, welcome, lifecycle |
| `04-analytics-blocks.css` | Metering, leaderboard, settlement |
| `05-mobile-dock.css` | Mobile bottom bar & dock |
| `06-hubs-pages.css` | Hub pages, bill desk, accounting, HR |
| `07-tables-subscribers.css` | Subscriber list tables, sticky actions |
| `08-dashboard-ops.css` | NOC, ops wall, WAN charts |
| `09-forms-details.css` | Create/edit forms, client details |
| `10-filament-overrides.css` | Filament fields, selects, tables |
| `11-subscriber-view-legacy.css` | Subscriber 360, billing notices |

## Load order

Filament loads modules via `App\Support\AdminSaasStyles` (see `design-system.blade.php`).

## Optional single file

```bash
./scripts/concat-admin-saas-css.sh
```

Rebuilds `public/css/admin-saas.css` for CDN or tools that expect one URL.

## Re-split from monolith (rare)

```bash
./scripts/split-admin-saas-css.sh
```

Only if you restored an old monolithic `admin-saas.css` and need to re-chunk it.
