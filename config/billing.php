<?php

return [

    'invoice_number_prefix' => env('BILLING_INVOICE_PREFIX', 'INV'),

    /**
     * Prefix only; year and sequence still appended (e.g. INV-2026-00001).
     */
    'invoice_number_year_infix' => env('BILLING_INVOICE_YEAR_INFIX', true),

    /**
     * Day of month (1–28) for auto bill generation (isp:generate-bills) for new subscribers.
     * Set to 1 for bills on the 1st of each month.
     */
    'default_billing_day' => (int) env('BILLING_DEFAULT_BILLING_DAY', 1),

    /** Day of month (1–31) for service expiry; 0 = last day of current month. */
    'default_expire_day' => (int) env('BILLING_DEFAULT_EXPIRE_DAY', 0),

    /**
     * Auto-generate monthly invoices for prepaid / advance subscribers on their bill day.
     */
    'prepaid_auto_invoice' => (bool) env('BILLING_PREPAID_AUTO_INVOICE', true),

    /**
     * Prepaid/advance on registration: this_month = bill today; next_month = wait for bill day.
     */
    'default_first_bill_cycle_prepaid' => env('BILLING_FIRST_BILL_PREPAID', 'this_month'),

    'default_first_bill_cycle_postpaid' => env('BILLING_FIRST_BILL_POSTPAID', 'next_month'),

    /** Create first invoice immediately when first_bill_cycle is this_month. */
    'bill_on_customer_create' => (bool) env('BILLING_ON_CUSTOMER_CREATE', true),

    /**
     * Default grace days when subscriber has none set.
     */
    'default_grace_period_days' => (int) env('BILLING_DEFAULT_GRACE_DAYS', 10),

    /**
     * How service_expires_at is extended when a bill is fully paid.
     * smart = within late_grace_days after expire use previous date, else payment date;
     * from_payment_date = always from pay date; from_previous_expiry = always from old expire date.
     */
    'payment_renewal_base' => env('BILLING_PAYMENT_RENEWAL_BASE', 'smart'),

    /** Days after expire that late payment still renews from previous expire date (smart mode). */
    'payment_renewal_late_grace_days' => (int) env('BILLING_PAYMENT_RENEWAL_LATE_GRACE_DAYS', 7),

    /**
     * Auto-apply late fees when running isp:apply-late-fees (scheduled daily).
     */
    'late_fees_enabled' => env('BILLING_LATE_FEES_ENABLED', true),

    /**
     * Charge package setup_fee on first invoice (once per subscriber).
     */
    'setup_fee_on_first_invoice' => (bool) env('BILLING_SETUP_FEE_ON_FIRST_INVOICE', true),

    /**
     * Charge customer reconnection_fee_amount when pending_reconnection_fee is set.
     */
    'reconnection_fee_enabled' => (bool) env('BILLING_RECONNECTION_FEE_ENABLED', true),

    /**
     * Block new invoices when open balance exceeds credit_limit (when limit > 0).
     */
    'credit_limit_enforced' => (bool) env('BILLING_CREDIT_LIMIT_ENFORCED', true),

    /**
     * After bill generation, auto-apply wallet for prepaid/advance and extend service_expires_at.
     */
    'prepaid_wallet_auto_settle' => (bool) env('BILLING_PREPAID_WALLET_AUTO_SETTLE', true),

    /**
     * Bill extra data usage when period total exceeds (included_data_gb × days in period).
     */
    'fup_overage_enabled' => (bool) env('BILLING_FUP_OVERAGE_ENABLED', true),

    /** Default BDT per GB when package has no overage_price_per_gb. */
    'fup_overage_price_per_gb' => (float) env('BILLING_FUP_OVERAGE_PRICE_PER_GB', 10),

    /**
     * Portal upgrades: create prorated invoice and redirect to pay before applying package.
     */
    'portal_instant_upgrade' => (bool) env('BILLING_PORTAL_INSTANT_UPGRADE', true),

    /**
     * Block portal package change requests while monthly/other invoices have an open balance.
     */
    'portal_package_change_requires_clear_balance' => (bool) env('BILLING_PORTAL_PACKAGE_CHANGE_REQUIRES_CLEAR_BALANCE', true),

    /**
     * Dunning ladder stages (days relative to due_date: negative = before, positive = after).
     * Each stage uses notifications.templates.{event_key} and notifications.events.{event_key}.
     */
    'dunning' => [
        'enabled' => (bool) env('BILLING_DUNNING_ENABLED', true),
        'include_payment_link' => (bool) env('BILLING_DUNNING_PAYMENT_LINK', true),
        'stages' => [
            ['key' => 'invoice_due_soon', 'offset_days' => -3, 'label' => 'Due in 3 days'],
            ['key' => 'invoice_due_today', 'offset_days' => 0, 'label' => 'Due today'],
            ['key' => 'invoice_overdue_3', 'offset_days' => 3, 'label' => '3 days overdue'],
            ['key' => 'invoice_overdue_7', 'offset_days' => 7, 'label' => '7 days overdue'],
            ['key' => 'invoice_overdue_14', 'offset_days' => 14, 'label' => '14 days overdue (final)'],
        ],
    ],

    'fup_alerts' => [
        'enabled' => (bool) env('BILLING_FUP_ALERTS_ENABLED', true),
        'warn_percent' => (float) env('BILLING_FUP_WARN_PERCENT', 80),
        'critical_percent' => (float) env('BILLING_FUP_CRITICAL_PERCENT', 100),
    ],

    /**
     * Downgrades from portal schedule pending_package_* for next cycle (isp:apply-scheduled-package-changes).
     */
    'downgrade_next_cycle' => (bool) env('BILLING_DOWNGRADE_NEXT_CYCLE', true),

    'env_defaults' => [
        'invoice_number_prefix' => (string) env('BILLING_INVOICE_PREFIX', 'INV'),
        'invoice_number_year_infix' => filter_var(env('BILLING_INVOICE_YEAR_INFIX', true), FILTER_VALIDATE_BOOL),
    ],

    /**
     * Collection desk: partial-pay notes + admin-managed discount presets.
     * Overrides stored in app_settings key billing.collection_discount (JSON).
     */
    'collection_discount' => [
        'enabled' => true,
        'require_note_on_partial' => true,
        'require_note_on_discount' => true,
        'allow_custom_amount' => true,
        'max_discount_bdt' => 500.0,
        'max_discount_percent_of_due' => 50.0,
        'presets' => [
            [
                'id' => 'waiver_50',
                'label' => '৫০ টাকা ছাড়',
                'type' => 'fixed',
                'amount' => 50,
            ],
            [
                'id' => 'waiver_10pct',
                'label' => '১০% ছাড় (সর্বোচ্চ ২০০)',
                'type' => 'percent',
                'amount' => 10,
                'max_bdt' => 200,
            ],
            [
                'id' => 'waiver_100',
                'label' => '১০০ টাকা ছাড়',
                'type' => 'fixed',
                'amount' => 100,
            ],
        ],
    ],
];
