# Reseller Enterprise — API

**OpenAPI 3:** [`/docs/reseller-openapi.yaml`](/docs/reseller-openapi.yaml) · interactive UI [`/docs/reseller-api.html`](/docs/reseller-api.html)

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

Middleware: `reseller.api.auth` (Sanctum **or** API key), `reseller.api.readonly` (keys may only `GET`/`HEAD`), `reseller.api` (reseller context). Keys act as the reseller owner (no staff impersonation). Rate limit + usage logging per key.

**Read access:** use the same GET paths as Sanctum, e.g. `/api/v1/reseller/customers`. Legacy alias `/api/v1/reseller/partner/*` registers the same handlers.

**Writes:** require a Sanctum token from `POST /api/v1/reseller/login`; API keys receive `405` on POST/PATCH/DELETE.

```http
Authorization: Bearer rsk_xxxxxxxx
```

Optional per-key `abilities` array (portal permission constants). Empty/null abilities = full owner scope. Restricted keys are enforced on all read routes.

Manage keys via Sanctum: `GET/POST /api/v1/reseller/api-keys`, `DELETE /api/v1/reseller/api-keys/{id}` (requires `API_KEYS_MANAGE` and `api_access_enabled`). POST body may include `abilities: ["portal.customer.view", ...]`.

## Rate limiting

- Default: `api_rate_limit_per_minute` on `resellers` (default 120)
- Per-key override on `reseller_api_keys.rate_limit_per_minute`
- Response `429` when exceeded

## Portal permissions → API

`reseller.api.permission:*` mirrors `ResellerPortalPermission` constants.

## Webhooks (platform-level)

Reseller events can emit to tenant webhooks (payment, commission, transfer completed) — integrate via existing `PaymentObserver` / transfer service hooks.
