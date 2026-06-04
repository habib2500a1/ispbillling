# Reseller Enterprise — Security Architecture

## Authentication

- **Portal:** `reseller` guard, bcrypt `portal_password`, optional 2FA (`two_factor_secret`)
- **Staff sub-accounts:** `reseller_staff` with scoped permissions
- **API:** Sanctum personal access tokens + hashed API keys (`rsk_*`)

## Authorization

- **ISP admin:** Spatie permissions (`resellers.*`)
- **Portal:** `ResellerPortalPermission` + JSON overrides + `ResellerCustomRole`
- **Middleware:** `reseller.permission`, `reseller.owner`, `reseller.2fa`, `reseller.ip`

## Network controls

- `allowed_ips` JSON on reseller — enforced at login + each request (`EnsureResellerIpAllowed`)
- API rate limiting per key (Cache-backed)

## Audit

- `reseller_portal_activity_logs` — action trail
- `reseller_portal_login_logs` — auth attempts
- `reseller_wallet_transactions` — financial ledger
- `reseller_api_usage_logs` — API access

## Data protection

- API keys stored as SHA-256 hash; plain shown once at creation
- Sensitive columns hidden on `Reseller` model
- Tenant isolation via `BelongsToTenant` global scope

## Recommendations

- Enforce 2FA for master/distributor accounts
- Rotate API keys quarterly
- Archive usage logs after `RESELLER_API_LOG_RETENTION_DAYS`
