# Reseller Enterprise — API

## Sanctum partner API (existing)

Base: `POST /api/v1/reseller/login` → Bearer token.

Enterprise endpoints follow existing `/api/v1/reseller/*` patterns. Enable per account: `api_access_enabled = true`.

## API key authentication (new)

Header: `Authorization: Bearer rsk_...` or `X-Reseller-Api-Key: rsk_...`

Middleware: `reseller.api_key` (rate limit per key + usage logging).

Example (future route group):

```http
GET /api/v1/reseller/partner/vitals
Authorization: Bearer rsk_xxxxxxxx
```

## Rate limiting

- Default: `api_rate_limit_per_minute` on `resellers` (default 120)
- Per-key override on `reseller_api_keys.rate_limit_per_minute`
- Response `429` when exceeded

## Portal permissions → API

`reseller.api.permission:*` mirrors `ResellerPortalPermission` constants.

## Webhooks (platform-level)

Reseller events can emit to tenant webhooks (payment, commission, transfer completed) — integrate via existing `PaymentObserver` / transfer service hooks.
