<?php

return [
    'hierarchical_enabled' => (bool) env('RESELLER_HIERARCHICAL_BILLING', true),

    /** postpaid_due = accrue admin receivable; wallet_prepaid = debit wallet on invoice (legacy). */
    'default_settlement_mode' => env('RESELLER_DEFAULT_SETTLEMENT_MODE', 'postpaid_due'),

    'default_due_grace_days' => (int) env('RESELLER_DEFAULT_DUE_GRACE_DAYS', 15),

    'default_allow_overdue_active' => (bool) env('RESELLER_ALLOW_OVERDUE_ACTIVE', true),

    'automation' => [
        'evaluate_policies' => (bool) env('RESELLER_BILLING_EVALUATE_POLICIES', true),
        'warning_threshold_percent' => (int) env('RESELLER_CREDIT_WARNING_PERCENT', 80),
    ],

    'aging_buckets' => [30, 60, 90],

    /** prorated | full_month | first_month_free | first_month_half */
    'default_new_customer_charge_mode' => env('RESELLER_NEW_CUSTOMER_CHARGE_MODE', 'prorated'),

    'default_prepaid_grace_days' => (int) env('RESELLER_DEFAULT_PREPAID_GRACE_DAYS', 5),

    'default_postpaid_grace_days' => (int) env('RESELLER_DEFAULT_POSTPAID_GRACE_DAYS', 10),

    /** Allow reseller portal to pick charge mode per subscriber when creating. */
    'reseller_can_override_charge_mode' => (bool) env('RESELLER_CAN_OVERRIDE_CHARGE_MODE', false),

    /** Max discount on subtotal a reseller can apply per invoice (percent). */
    'max_invoice_discount_percent' => (float) env('RESELLER_MAX_INVOICE_DISCOUNT_PERCENT', 100),

    'monthly_statements' => [
        'auto_close' => (bool) env('RESELLER_AUTO_CLOSE_MONTHLY_STATEMENTS', true),
    ],

    'due_reminders' => [
        'reseller_portal_enabled' => (bool) env('RESELLER_PORTAL_DUE_REMINDERS', true),
        'bulk_enabled' => (bool) env('RESELLER_BULK_DUE_REMINDERS', true),
        /** Only remind when due_date is on or before today minus N days. */
        'bulk_min_days_overdue' => (int) env('RESELLER_BULK_DUE_MIN_DAYS_OVERDUE', 0),
        /** Per-invoice cooldown (hours) — same key as manual portal reminder. */
        'cooldown_hours' => (int) env('RESELLER_DUE_REMINDER_COOLDOWN_HOURS', 24),
    ],

    /** Telegram ops chat alerts when reseller collects or sends due reminders. */
    'telegram_ops_alerts' => (bool) env('RESELLER_TELEGRAM_OPS_ALERTS', true),

    'payment_allocation' => [
        'fifo_enabled' => (bool) env('RESELLER_PAYMENT_FIFO', true),
    ],

    /** Reseller portal: generate bills for all active subscribers at once. */
    'portal_bulk_invoice_generate' => (bool) env('RESELLER_PORTAL_BULK_INVOICE', true),
];
