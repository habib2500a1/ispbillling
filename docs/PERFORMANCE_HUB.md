# Performance Hub

Admin: **Settings → Performance** (`/admin/performance-settings`)

## What moved from `.env`

| Setting | Default | Why |
|---------|---------|-----|
| Auto OLT sync on subscriber view | **OFF** | Biggest page-speed win |
| Optical sync queue | `redis` | Non-blocking saves |
| Bandwidth poll interval | 5 min | Load vs freshness |
| ONU signal poll interval | 10 min | Optical cron load |
| MikroTik status poll | ON | Online clients |
| MikroTik fetch-details poll | **OFF** | API heavy |
| OLT SNMP poll | ON | NOC intelligence |
| Fast sync mode | ON | Batch MikroTik/OLT |
| Bundle admin CSS | ON | Fewer HTTP requests |
| Settings cache TTL | 120s | Faster panel boot |
| Automation runner limits | 1 / 1800s | Prevents 502 |

## Actions on the page

- **Save** — writes encrypted `app_settings`, applies immediately
- **Warm dashboard caches** — `isp:warm-dashboard-caches`
- **Rebuild CSS bundles** — after enabling bundled CSS
- **Reset to recommended defaults** — 500k-scale safe profile

## Still in `.env` only

```
APP_KEY, APP_URL, DB_*, REDIS_HOST, CACHE_STORE, SESSION_DRIVER, QUEUE_CONNECTION
```

## Related

- **Settings → Customer search** — Meilisearch fast lookup
- **Network → Laser thresholds** — ONU RX/TX alert bands
