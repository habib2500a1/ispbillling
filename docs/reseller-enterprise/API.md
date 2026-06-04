# Reseller Enterprise — API

## Sanctum partner API (existing)

Base: `POST /api/v1/reseller/login` → Bearer token.

Enterprise endpoints follow existing `/api/v1/reseller/*` patterns. Enable per account: `api_access_enabled = true`.

### Enterprise parity (mobile)

| Feature | Method | Path |
|---------|--------|------|
| Sub-partners list | GET | `/api/v1/reseller/sub-resellers` |
| Sub-partner detail | GET | `/api/v1/reseller/sub-resellers/{id}` |
| Create sub-partner | POST | `/api/v1/reseller/sub-resellers` |
| Customer transfers | GET | `/api/v1/reseller/customer-transfers` |
| Request transfer | POST | `/api/v1/reseller/customers/{id}/transfer` |
| HQ announcements | GET | `/api/v1/reseller/announcements` |
| Due account summary | GET | `/api/v1/reseller/due-account` |
| Internal HQ tickets | GET/POST | `/api/v1/reseller/internal-tickets` |

Permissions match web: `reseller.api.permission:*` uses the same `ResellerPortalPermission` constants.

Additional Sanctum routes: `GET /wallet/overview`, `GET /reports/enterprise`, `GET|POST|PATCH|DELETE /staff`, `GET|POST|DELETE /api-keys`.

## API key authentication

Header: `Authorization: Bearer rsk_...` or `X-Reseller-Api-Key: rsk_...`

Middleware: `reseller.api_key` (rate limit per key + usage logging). Keys act as the reseller owner (no staff impersonation).

Partner read routes (prefix `/api/v1/reseller/partner`):

```http
GET /api/v1/reseller/partner/dashboard
GET /api/v1/reseller/partner/wallet
GET /api/v1/reseller/partner/customers
GET /api/v1/reseller/partner/commissions
GET /api/v1/reseller/partner/notifications
Authorization: Bearer rsk_xxxxxxxx
```

Manage keys via Sanctum: `GET/POST /api/v1/reseller/api-keys`, `DELETE /api/v1/reseller/api-keys/{id}` (requires `API_KEYS_MANAGE` and `api_access_enabled`).

## Rate limiting

- Default: `api_rate_limit_per_minute` on `resellers` (default 120)
- Per-key override on `reseller_api_keys.rate_limit_per_minute`
- Response `429` when exceeded

## Portal permissions → API

`reseller.api.permission:*` mirrors `ResellerPortalPermission` constants.

## Webhooks (platform-level)

Reseller events can emit to tenant webhooks (payment, commission, transfer completed) — integrate via existing `PaymentObserver` / transfer service hooks.
