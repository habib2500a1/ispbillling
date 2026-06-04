# API configuration — Sheba-Fi vs আমাদের প্ল্যাটফর্ম

## তুলনা

| Sheba-Fi ডেমো | আমাদের প্ল্যাটফর্ম (নিরাপদ) |
|---------------|------------------------------|
| Subdomain binding (টেক্সট) | **Settings → API configuration** — `api_host` অথবা `{slug}.{ISP_TENANT_BASE_DOMAIN}` |
| HMAC secret + Regenerate | **Encrypted** `app_settings` (`tenant.{id}.integrations.webhook_hmac_secret`) — UI-তে শুধু masked; regenerate-এ একবার plaintext |
| API tokens after save | **Sanctum** — “Create REST token” (একবার দেখানো) |
| Plain secret in DB | ❌ — Laravel `encrypted` cast + per-tenant scope |

## Admin URL

`/admin/api-configuration`

## Webhook auth (দুই মোড)

1. **HMAC (recommended)** — tenant-এ secret থাকলে বাধ্যতামূলক:
   - Header: `X-ISP-Signature: t={unix},v1={hex}`
   - Signed string: `{unix}.{raw_json_body}`
   - Algorithm: `HMAC-SHA256` with tenant secret
   - Timestamp ±5 minutes

2. **Legacy** — `.env` `CALL_CENTER_WEBHOOK_SECRET` / `ISP_SUPPORT_WEBHOOK_SECRET` + header `X-ISP-Webhook-Secret`

Production-এ অন্তত একটি পদ্ধতি কনফিগার থাকতে হবে।

## REST API

- Base: `{api_host}/api/v1`
- Auth: `Authorization: Bearer {sanctum_token}`
- Docs: `public/docs/API_V1.md`

## CLI

```bash
php artisan isp:generate-webhook-secrets   # .env secrets (server-level)
```

Tenant HMAC admin UI থেকে regenerate করুন — `.env`-এ commit করবেন না।
