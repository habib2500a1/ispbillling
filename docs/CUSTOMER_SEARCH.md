# Customer Search (Scout + Meilisearch)

Fast typo-tolerant subscriber search — **managed from admin dashboard**, minimal `.env`.

## Dashboard

**Settings → Customer search** (`/admin/customer-search-settings`)

- Enable / disable Meilisearch
- View connection status & indexed count
- **Re-index all subscribers** (one click)
- No separate API key to copy — auto from `APP_KEY`

Used by: support ticket create, bill collection, mobile staff API, Ctrl+K search.

## Zero extra .env (recommended)

Only existing `APP_KEY` is used. Meilisearch master key = `sha256(APP_KEY + salt)` — same in PHP and Docker entrypoint.

Optional overrides (advanced only):

```env
# MEILISEARCH_HOST=http://127.0.0.1:7700
# MEILISEARCH_KEY=
```

## Deploy flow

```bash
composer update
docker compose up -d meilisearch app horizon
php artisan isp:post-deploy    # auto-indexes when Meilisearch is healthy
```

Or from dashboard: **Re-index all subscribers**.

## Fallback

If Meilisearch is offline → PostgreSQL `LIKE` search (slower at 500k). Toggle in dashboard.

## Code

```php
app(\App\Services\Billing\BillCollectionSearchService::class)->search('habib');
Customer::search('akib')->where('tenant_id', 1)->take(25)->get();
```
