# Hierarchical wholesale billing

Admin bills resellers at **wholesale**; resellers bill customers at **retail**. Two independent due balances:

| Layer | Who owes whom | Example |
|-------|----------------|---------|
| Customer → Reseller | Retail invoice total | 1,000 BDT |
| Reseller → Admin | Wholesale (admin receivable) | 800 BDT |
| Reseller margin | Retail − wholesale | 200 BDT |

Customer non-payment does **not** auto-suspend the line when reseller policy allows overdue customers to stay active. Reseller credit breach is evaluated separately (admin policy).

## Settlement modes

- **postpaid_due** — On each customer invoice, accrue wholesale to `admin_receivable_due` (no wallet debit).
- **wallet_prepaid** — Debit reseller wallet on invoice (legacy).
- **hybrid** — Debit wallet first; accrue remainder to due account.

Config: `config/reseller_billing.php`, env `RESELLER_HIERARCHICAL_BILLING`, `RESELLER_DEFAULT_SETTLEMENT_MODE`.

## Ledger

Table `reseller_ledger_entries` — double-entry style audit trail:

- `admin_receivable_accrual` (debit) — bill generated
- `admin_receivable_collection` (credit) — customer payment applied to wholesale portion
- `admin_receivable_settlement` (credit) — reseller paid HQ

## Policies

**Reseller (admin-controlled)**

- `credit_limit` + `due_grace_period_days`
- `reseller_suspend_policy`: `credit_breach` | `none`
- `suspend_reseller_customers_on_breach` — optional mass customer suspend

**Customer (reseller-controlled)**

- `customer_billing_policy`: `reseller_controlled` | `follow_isp_due` | `never_auto`
- `allow_overdue_customers_active`

## Automation

```bash
php artisan isp:evaluate-reseller-billing-policies
php artisan isp:evaluate-reseller-billing-policies --dry-run
```

Register in admin → Automatic processes (recommended: daily).

## Admin UI

Reseller edit → **Wholesale billing** section; **Admin receivable ledger** tab → record settlement.

## Reseller portal

`/reseller/due-account` — admin due, customer due, aging, ledger.

## Services

- `ResellerInvoiceSplitCalculator`
- `ResellerDueLedgerService`
- `ResellerBillingPolicyService`
- `ResellerHierarchicalBillingService` — hooks from `InvoiceGenerator` and `PaymentObserver`
