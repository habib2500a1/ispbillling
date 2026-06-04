<?php

return [
    'base_url' => rtrim((string) env('LEGACY_PORTAL_URL', env('ISP_DIGITAL_URL', 'https://pay.anetbd.com')), '/'),
    'username' => (string) env('LEGACY_PORTAL_USERNAME', env('ISP_DIGITAL_USERNAME', 'admin')),
    'password' => (string) env('LEGACY_PORTAL_PASSWORD', env('ISP_DIGITAL_PASSWORD', '')),

    /** Default commission % for MAC resellers when legacy portal row has no explicit rate. */
    'default_mac_reseller_commission_percent' => (float) env('LEGACY_PORTAL_MAC_RESELLER_COMMISSION_PERCENT', 0),

    /**
     * Collection report default: desk | legacy_portal | all
     * desk = only entries made in this system (not legacy portal import).
     * legacy_portal = pay.anetbd history (matches old portal).
     */
    'collection_report_default_source' => env('LEGACY_PORTAL_COLLECTION_REPORT_SOURCE', 'legacy_portal'),

    /** Max invoices on bill collection desk / subscriber billing tab (legacy portal history). */
    'bill_history_limit' => (int) env('LEGACY_PORTAL_BILL_HISTORY_LIMIT', 120),

    /** Max payments on subscriber billing statement. */
    'payment_history_limit' => (int) env('LEGACY_PORTAL_PAYMENT_HISTORY_LIMIT', 120),

    /** After billing import, void duplicate local desk rows when ISD already has the payment. */
    'sync_collections_void_orphans' => (bool) env('LEGACY_PORTAL_SYNC_VOID_ORPHANS', true),

    /**
     * While pay.anetbd.com is still live, pull subscribers + billing + collections into this DB.
     * Set false when you cut over and run only on bill.flixbd.xyz.
     */
    'daily_sync_enabled' => (bool) env('LEGACY_PORTAL_DAILY_SYNC_ENABLED', true),

    /** Cron time (server timezone) for isp:sync-legacy-portal-daily */
    'daily_sync_at' => (string) env('LEGACY_PORTAL_DAILY_SYNC_AT', '02:30'),

    /** Page size for isp:import-legacy-portal --all during daily sync */
    'daily_sync_import_batch' => (int) env('LEGACY_PORTAL_DAILY_SYNC_IMPORT_BATCH', 100),

    /** Shown in bill collection / payment source (not the vendor name). */
    'portal_label' => (string) env('BILLING_PORTAL_LABEL', 'Online portal'),

    /** Billing dashboard KPI subtitles — off by default (no “legacy portal” on cards). */
    'show_dashboard_kpi_hint' => (bool) env('LEGACY_PORTAL_SHOW_DASHBOARD_KPI_HINT', false),

    /** When hint is on and empty, uses isp.company_name. */
    'dashboard_kpi_hint' => env('LEGACY_PORTAL_DASHBOARD_KPI_HINT', ''),
];
