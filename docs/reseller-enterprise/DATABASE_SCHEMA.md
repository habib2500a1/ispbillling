# Reseller Enterprise — Database Schema

## Extended `resellers`

| Column | Type | Purpose |
|--------|------|---------|
| `bonus_wallet_balance` | decimal(14,2) | Promotional / bonus wallet |
| `credit_limit` | decimal(14,2) | Allowed negative main balance |
| `low_balance_threshold` | decimal | Auto-suspend trigger |
| `auto_suspend_on_low_balance` | boolean | Automation flag |
| `auto_restore_on_recharge` | boolean | Re-activate on credit |
| `portal_custom_domain` | string | White-label CNAME |
| `brand_secondary_color` | string | UI theme |
| `portal_login_message` | text | Custom login copy |
| `hierarchy_path` | string(512) | Materialized path `/1/5/` |
| `hierarchy_depth` | smallint | Tree depth |
| `max_onu`, `max_olt` | int | Resource quotas |
| `bandwidth_quota_mbps` | int | Aggregate bandwidth cap |
| `max_packages` | int | Catalog quota |
| `commission_mode` | string | `simple` or `tier` |
| `allowed_ips` | json | Portal IP allowlist |
| `api_access_enabled` | boolean | REST API keys |
| `api_rate_limit_per_minute` | int | Default rate limit |

**Indexes:** `(tenant_id, parent_id)`, `(tenant_id, hierarchy_path)`, `(tenant_id, is_active, franchise_type)`

## New tables

### `reseller_wallet_transactions`
Append-only ledger: `wallet_type` (main|bonus), `direction`, `amount`, `balance_after`, `transaction_type`, `reference`.

### `reseller_commission_tiers`
Tiered commission: `min_amount`, `max_amount`, `commission_type`, `commission_value`, `sort_order`.

### `reseller_customer_transfers`
Ownership moves: `customer_id`, `from_reseller_id`, `to_reseller_id`, `status`, approval timestamps.

### `reseller_api_keys` / `reseller_api_usage_logs`
Hashed API keys (`key_prefix` + SHA-256), per-request usage logging.

### `reseller_portal_login_logs`
Login success/failure, IP, user-agent, device fingerprint.

### `reseller_announcements` / `reseller_announcement_reads`
Broadcast messages to all or targeted resellers.

### `reseller_internal_tickets`
Partner → ISP support queue.

### `reseller_notes`
Admin/partner notes on account.

### `reseller_invoices`
B2B billing for reseller accounts (wholesale, platform fees).

### `reseller_custom_roles`
Per-reseller RBAC roles with `permissions` + `menu_permissions` JSON.

## Existing tables (unchanged core)

- `reseller_balance_transfers` — transfer events (linked from ledger)
- `reseller_commissions` — payment-linked accruals
- `reseller_settlements` — payout workflow
- `reseller_packages` — per-reseller pricing
- `reseller_staff` — sub-logins (+ `reseller_custom_role_id`)
- `customers.reseller_id` — ownership FK
