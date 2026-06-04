# Reseller Enterprise — Production Deployment

## Stack

- **App:** Laravel 11, PHP 8.2+, Horizon
- **DB:** PostgreSQL 15+ (recommended)
- **Cache/Queue:** Redis
- **Web:** Nginx + PHP-FPM
- **Optional:** Kubernetes (stateless app pods + managed PG/Redis)

## Migration

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder  # if fresh
```

## Scheduler

Add to `isp:run-automatic-processes` or schedule directly:

```php
$schedule->command('isp:reseller-auto-suspend-low-balance')->everyFifteenMinutes();
```

## Horizontal scaling

- Session: Redis/database
- Sanctum tokens: database
- API rate limits: Redis Cache
- WebSocket: Laravel Reverb / Pusher cluster

## Wildcard SSL for white-label

See `ResellerBranding::sslSetupGuide()` — DNS `*.tenant-base-domain` → load balancer.

## Performance checklist

- [ ] PostgreSQL indexes from enterprise migration applied
- [ ] `hierarchy_path` backfill: `php artisan tinker` → `Reseller::each(fn ($r) => app(ResellerHierarchyService::class)->syncPath($r))`
- [ ] Horizon workers ≥ CPU cores
- [ ] Read replica for report exports
