# Enterprise Reseller Management Module

Carrier-class multi-tenant reseller hierarchy, wallet ledger, commissions, quotas, transfers, API keys, white-label, automation, and reporting — built on Laravel 11 + Filament 3.

## Documentation index

| Document | Description |
|----------|-------------|
| [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) | Tables, columns, indexes |
| [ERD.md](./ERD.md) | Entity-relationship diagram (Mermaid) |
| [API.md](./API.md) | Partner REST API + API key auth |
| [BACKEND_ARCHITECTURE.md](./BACKEND_ARCHITECTURE.md) | Services, events, scaling |
| [FRONTEND_ARCHITECTURE.md](./FRONTEND_ARCHITECTURE.md) | Portal, Filament admin |
| [FOLDER_STRUCTURE.md](./FOLDER_STRUCTURE.md) | Code layout |
| [DEPLOYMENT.md](./DEPLOYMENT.md) | Production deployment |
| [SECURITY.md](./SECURITY.md) | RBAC, 2FA, audit, encryption |
| [MICROSERVICES.md](./MICROSERVICES.md) | Future extraction boundaries |
| [HIERARCHICAL_BILLING.md](./HIERARCHICAL_BILLING.md) | Wholesale due account, margin split, policies |
| [CUSTOMER_BILLING.md](./CUSTOMER_BILLING.md) | Reseller-managed subscriber billing, prepaid/postpaid |

## Quick start

```bash
php artisan migrate
php artisan isp:reseller-auto-suspend-low-balance
php artisan isp:evaluate-reseller-billing-policies
php artisan isp:close-reseller-monthly-statements
php artisan isp:send-reseller-due-reminders
```

Configure `.env`:

```env
RESELLER_AUTO_SUSPEND_LOW_BALANCE=true
RESELLER_AUTO_RESTORE_RECHARGE=true
RESELLER_WALLET_LEDGER_ENABLED=true
RESELLER_TRANSFER_REQUIRE_APPROVAL=true
RESELLER_API_RATE_LIMIT=120
RESELLER_HIERARCHICAL_BILLING=true
RESELLER_DEFAULT_SETTLEMENT_MODE=postpaid_due
```

## Hierarchy

Unlimited depth via `parent_id` + materialized `hierarchy_path` (e.g. `/1/5/12/`). Franchise types include:

`master_reseller` → `distributor` → `reseller` → `sub_reseller` (plus legacy `franchise`, `area_distributor`, `local_partner`).

## Partner portal routes

| Route | Permission |
|-------|------------|
| `/reseller/wallet/overview` | `portal.wallet.view` |
| `/reseller/reports/enterprise` | `portal.reports.view` |
| `/reseller/sub-resellers/create` | `portal.sub_reseller.create` |
| `/reseller/customer-transfers` | `portal.customer.transfer` |
| `/reseller/api-keys` | `portal.api_keys.manage` |
| `/reseller/branding` | `portal.branding.manage` |
| `/reseller/internal-tickets` | `portal.internal_ticket.manage` |
| `/reseller/security` | (authenticated) |

## Scale targets

- **10,000+** resellers per tenant (indexed `hierarchy_path`, `tenant_id`)
- **100,000+** ONUs via customer/reseller FK + quota checks
- Wallet ledger append-only for audit; balance columns for fast reads
