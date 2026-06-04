# Reseller Enterprise — Backend Architecture

## Layering

```
HTTP (Portal / API / Filament)
    → Middleware (auth, 2FA, IP, permissions, rate limit)
    → Controllers (thin)
    → Services (domain logic)
    → Models (Eloquent + BelongsToTenant)
    → PostgreSQL
```

## Core services (`app/Services/Resellers/`)

| Service | Responsibility |
|---------|----------------|
| `ResellerHierarchyService` | Path sync, ancestors/descendants |
| `ResellerWalletLedgerService` | Main/bonus wallets, credit limit, ledger rows |
| `ResellerBalanceService` | Transfers (existing) + ledger + auto-restore hook |
| `ResellerCommissionService` | Simple + tier commission, payout |
| `ResellerQuotaService` | Customer/ONU/package limits |
| `ResellerCustomerTransferService` | Approval workflow |
| `ResellerApiKeyService` | Key lifecycle + usage logs |
| `ResellerBillingService` | Reseller invoices |
| `ResellerAutomationService` | Low-balance suspend, restore on recharge |
| `ResellerEnterpriseReportService` | Revenue, P&L, growth |
| `ResellerPortalLoginLogger` | Login audit + IP allowlist |

## Events & observers

- `Reseller::saved` — sync `hierarchy_path`, subscriber sync on `is_active`
- `PaymentObserver` — commission accrual (existing)

## Queues & commands

- `isp:reseller-auto-suspend-low-balance` — scheduled automation
- Horizon workers for SMS/notifications on wallet/commission events

## Caching

- API rate limits: `Cache` per key per minute
- Dashboard metrics: optional Redis cache per reseller (TTL 60s) for 10k+ partners

## Read scaling

- Replica reads for reports (`ResellerEnterpriseReportService`)
- Ledger pagination indexed by `(reseller_id, created_at)`
