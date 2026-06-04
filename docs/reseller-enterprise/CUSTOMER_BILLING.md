# Reseller-managed customer billing

## Layers

| Layer | Who controls | What |
|-------|----------------|------|
| Admin → Reseller | HQ | Package wholesale rate, settlement mode, credit limit, new-customer charge mode, default prepaid/postpaid |
| Reseller → Customer | Partner portal | Add/edit subscribers, bills, collect payment, grace, keep-online-when-due |

## Monthly cycle (day 1)

Run `isp:generate-bills` (or automatic process) on each subscriber's `billing_day` (default **1**):

- Package line (+ proration / first-month rules)
- ONU lease lines (separate invoice items)
- Reseller admin receivable accrual (postpaid_due mode)
- Ledger entries

## Prepaid vs postpaid

**Prepaid customer**

- Due date = issue + grace (reseller `default_prepaid_grace_days`, default 5)
- Unpaid after grace → auto suspend (PPPoE off) unless customer meta `allow_active_when_due`
- Reseller `allow_overdue_customers_active` does **not** skip prepaid suspend

**Postpaid customer**

- Due date = issue + grace (`default_postpaid_grace_days`, default 10)
- Service can stay **active** while due accumulates when:
  - Reseller policy = `reseller_controlled` and `allow_overdue_customers_active` = true, or
  - Customer meta `allow_active_when_due` = true
- Next month bill adds to total due (carry forward on open invoices)

## Mid-month new customer

Admin sets per reseller **New customer charge**:

| Mode | Behaviour |
|------|-----------|
| `prorated` | Package = monthly ÷ days × remaining days |
| `full_month` | Full cycle package price |
| `first_month_free` | Package line 0 (ONU fees still apply) |
| `first_month_half` | Package line × 50% |

Optional: **Reseller can override charge mode** per subscriber (portal) when enabled.

## Admin configuration

Reseller edit → **Wholesale billing**:

- Settlement mode, credit limit, customer billing policy
- New customer charge, default prepaid/postpaid, grace days
- Allow overdue customers active (postpaid)

Env defaults: `config/reseller_billing.php`

## Reseller portal

- Create subscriber: billing mode, grace, join date, generate bill, collect payment
- **Bills** → **Generate all monthly bills** — one click for all active subscribers (partner code e.g. `0001`)
- Per subscriber: customer profile → **Generate bill**
- Bills list / PDF / send / adjust / line edit
- Due account: customer due vs HQ payable

Env: `RESELLER_PORTAL_BULK_INVOICE=true` (`config/reseller_billing.php` → `portal_bulk_invoice_generate`).

## Services

- `ResellerCustomerBillingEngine` — proration, first month, suspend exemption, grace
- `ResellerHierarchicalBillingService` — invoice → admin receivable
- `ResellerInvoiceAdjustmentService` — portal discount / waive (audit in invoice meta)
- `ResellerCustomerDueReminderService` — SMS/email due reminder from portal
- `ResellerMonthlyStatementService` — monthly HQ receivable snapshots
- `ResellerCollectionPerformanceService` — admin collection dashboard data
- `InvoiceGenerator` — integrates engine for reseller subscribers

## Portal permissions

- `portal.invoice.adjust` — discount or waive open invoices
- `portal.invoice.edit` — change line prices or add adjustment lines
- `portal.billing.view` — send due reminders
- `portal.payment.collect` — record cash/MFS; FIFO multi-invoice or wallet advance

## Partial payments (FIFO)

When a subscriber has multiple open bills, **Collect payment** can apply cash to **oldest invoices first** (`allocation_mode=fifo`). Surplus after all open bills are paid is credited to the subscriber wallet.

Env: `RESELLER_PAYMENT_FIFO=true` in `config/reseller_billing.php`.

## Ops Telegram

When `RESELLER_TELEGRAM_OPS_ALERTS=true`, HQ ops chat receives alerts for reseller portal **collections** and **due reminders** (uses platform Telegram bot settings).

## Subscriber Telegram

Set **Telegram chat ID** on create/edit subscriber (numeric ID from @userinfobot or similar). When `NOTIFICATIONS_TELEGRAM_ENABLED` and `NOTIFICATIONS_CUSTOMER_TELEGRAM_DUE=true`, due reminders also go to that chat.

## Bulk due reminders

**Portal:** Subscribers list → **Remind all due** (open bills only; 24h cooldown per invoice by default).

**CLI:**

```bash
php artisan isp:send-reseller-due-reminders
php artisan isp:send-reseller-due-reminders --reseller=FR001 --dry-run
php artisan isp:send-reseller-due-reminders --days-overdue=3
```

Env: `RESELLER_BULK_DUE_REMINDERS`, `RESELLER_BULK_DUE_MIN_DAYS_OVERDUE`, `RESELLER_DUE_REMINDER_COOLDOWN_HOURS`.

## Admin pages

- **Collection performance** — partner due, collection %, HQ receivable, risk
- Partner → **Monthly statements** tab — sync/close month
- Partner → **Admin receivable ledger** — settlement, credit note, debit note

## Commands

```bash
php artisan isp:close-reseller-monthly-statements
php artisan isp:close-reseller-monthly-statements --dry-run
```
