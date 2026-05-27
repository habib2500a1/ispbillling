# ISP Platform API v1

Operational API reference for staff apps, customer apps, reseller apps, and integration partners.

## Base URL

Use your deployment domain:

```text
https://your-domain.example/api/v1
```

Examples:

- Health: `/api/v1/health`
- Mobile login: `/api/v1/mobile/login`
- Staff login: `/api/v1/auth/login`
- Customer login: `/api/v1/customer/login`
- Reseller login: `/api/v1/reseller/login`

## Authentication

Current API auth mode is bearer token via Laravel Sanctum.

Header:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

Login responses now include:

- `token`
- `token_type`
- `auth_mode`
- `guard`
- `issued_at`
- `expires_at`
- `abilities`

## Public Endpoints

### Health

`GET /api/v1/health`

Returns API status, docs URL, and common endpoint hints.

### Mobile Config

`GET /api/v1/mobile/config`

Returns app config used by staff/customer mobile apps.

### Public Mobile Login

`POST /api/v1/mobile/login`

Unified login flow for supported mobile roles.

## Staff API

### Login

`POST /api/v1/auth/login`

Request:

```json
{
  "email": "admin@example.com",
  "password": "secret",
  "device_name": "Android Phone"
}
```

Response:

```json
{
  "token": "plain-text-token",
  "token_type": "Bearer",
  "auth_mode": "sanctum",
  "guard": "web",
  "issued_at": "2026-05-26T10:00:00+06:00",
  "expires_at": "2026-06-25T10:00:00+06:00",
  "abilities": ["staff", "technician"],
  "user": {
    "id": 1,
    "name": "ISP Admin",
    "email": "admin@example.com",
    "tenant_id": 1,
    "roles": ["isp-admin"]
  }
}
```

### Common Staff Endpoints

- `GET /api/v1/me`
- `GET /api/v1/staff/dashboard`
- `GET /api/v1/staff/monitoring/online`
- `GET /api/v1/staff/billing/summary`
- `GET /api/v1/staff/customers`
- `GET /api/v1/staff/customers/{customer}`
- `POST /api/v1/staff/payments`
- `POST /api/v1/staff/network/suspend`
- `POST /api/v1/staff/network/reconnect`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`

## Customer API

### Login

`POST /api/v1/customer/login`

Request:

```json
{
  "login": "CUST10001",
  "password": "portal-password",
  "device_name": "Customer App"
}
```

### Common Customer Endpoints

- `GET /api/v1/customer/me`
- `GET /api/v1/customer/dashboard`
- `GET /api/v1/customer/bills`
- `POST /api/v1/customer/bills/{invoice}/pay`
- `GET /api/v1/customer/usage/live`
- `GET /api/v1/customer/onu/status`
- `GET /api/v1/customer/packages`
- `GET /api/v1/customer/tickets`
- `POST /api/v1/customer/auth/refresh`
- `POST /api/v1/customer/logout`

## Reseller API

### Login

`POST /api/v1/reseller/login`

Request:

```json
{
  "login": "RSL-001",
  "password": "portal-password",
  "device_name": "Reseller Portal",
  "two_factor_code": "123456"
}
```

### Common Reseller Endpoints

- `GET /api/v1/reseller/me`
- `GET /api/v1/reseller/dashboard`
- `GET /api/v1/reseller/customers`
- `POST /api/v1/reseller/customers`
- `GET /api/v1/reseller/customers/{customer}`
- `PATCH /api/v1/reseller/customers/{customer}`
- `POST /api/v1/reseller/customers/{customer}/payments`
- `GET /api/v1/reseller/onu`
- `POST /api/v1/reseller/logout`

## Webhooks

These endpoints should be protected by secrets and rate limiting:

- `POST /api/webhooks/support-ticket-ingest`
- `POST /api/webhooks/payments/{gateway}`
- `POST /api/webhooks/netflow-ingest`
- `POST /api/webhooks/onu-optical-ingest`
- `GET /api/webhooks/whatsapp`
- `POST /api/webhooks/whatsapp`

Required production secrets:

- `ISP_SUPPORT_WEBHOOK_SECRET`
- `NETFLOW_WEBHOOK_SECRET`
- `OPTICAL_WEBHOOK_SECRET`
- `WHATSAPP_WEBHOOK_VERIFY_TOKEN`

Generate them quickly:

```bash
php artisan isp:generate-webhook-secrets --write
php artisan config:clear
```

## Notes

- APIs are tenant-aware where applicable.
- Public bill payment and payment link resolution are now tenant-safe.
- Sanctum bearer auth is the current production path.
- JWT bridge can be added later if third-party consumers require strict JWT tokens.
