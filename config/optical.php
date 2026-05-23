<?php

return [

    'enabled' => (bool) env('OPTICAL_MONITORING_ENABLED', true),

    /**
     * ISP Digital style: OLT SNMP inventory → match PPP login on ONU description → show dBm on subscriber.
     */
    'isp_digital_auto_sync' => (bool) env('OPTICAL_ISP_DIGITAL_AUTO_SYNC', true),

    /** Pull OLT inventory when opening subscriber view if no ONU / no RX (queued job). */
    'auto_sync_on_customer_view' => (bool) env('OPTICAL_AUTO_SYNC_ON_CUSTOMER_VIEW', true),

    /** After create/update PPP login — queue OLT sync + link. */
    'auto_sync_on_customer_save' => (bool) env('OPTICAL_AUTO_SYNC_ON_CUSTOMER_SAVE', true),

    /** Re-use inventory without new SNMP walk if synced within N seconds. */
    'isp_digital_inventory_max_age_seconds' => (int) env('OPTICAL_ISP_DIGITAL_INVENTORY_MAX_AGE', 180),

    /** When MAC lookup misses inventory, run BDCOM SNMP sync then retry (00:AD:24:F0:FB:3C = 00AD24F0FB3C). */
    'auto_sync_olt_on_mac_lookup' => (bool) env('OPTICAL_AUTO_SYNC_OLT_ON_MAC_LOOKUP', true),

    /**
     * Per-subscriber OLT sync job connection. Use "sync" when no queue:work/Horizon (auto still runs after save/view).
     * Set to "redis" when a worker is running.
     */
    'customer_sync_connection' => env('OPTICAL_CUSTOMER_SYNC_QUEUE', 'sync'),

    /**
     * Pull EPON/MAC from MikroTik PPP (comment, caller-id, last-caller-id) then search OLT inventory.
     * Put EPON0/4:29 or ONU MAC in MikroTik secret comment for full auto (router MAC alone is not on OLT).
     */
    'mikrotik_optical_bridge_enabled' => (bool) env('OPTICAL_MIKROTIK_OPTICAL_BRIDGE', true),

    'webhook_secret' => env('OPTICAL_WEBHOOK_SECRET'),

    /** Create ONU device rows from webhook when no match (requires olt_id in payload or one OLT in DB). */
    'webhook_auto_create_onu' => (bool) env('OPTICAL_WEBHOOK_AUTO_CREATE', true),

    /**
     * Auto-create/link an ONU row per subscriber (for panel RX/TX + scheduled optical sync).
     * Requires at least one OLT in the tenant (or OPTICAL_DEFAULT_OLT_ID).
     */
    'auto_provision_customer_onu' => (bool) env('OPTICAL_AUTO_PROVISION_ONU', true),

    /** When true, do not create SUB-* placeholder ONUs on subscriber view if BDCOM SNMP inventory exists. */
    'prefer_bdcom_snmp_inventory' => (bool) env('OPTICAL_PREFER_BDCOM_SNMP', true),

    /** After BDCOM SNMP sync, auto-link ONUs to subscribers by MAC + PPP login. */
    'auto_link_on_bdcom_sync' => (bool) env('OPTICAL_AUTO_LINK_ON_BDCOM_SYNC', true),

    /** Minimum match score (0–100) to auto-link. */
    'smart_link_min_score' => (int) env('OPTICAL_SMART_LINK_MIN_SCORE', 90),

    /** Keep existing link only if re-score is at least this. */
    'smart_link_min_keep_score' => (int) env('OPTICAL_SMART_LINK_MIN_KEEP_SCORE', 85),

    /** Required gap between best and second-best candidate. */
    'smart_link_min_gap' => (int) env('OPTICAL_SMART_LINK_MIN_GAP', 10),

    /** Skip SUB-* auto placeholders when tenant already has this many MAC-based ONUs from OLT sync. */
    'skip_placeholder_when_bdcom_onus' => (int) env('OPTICAL_SKIP_PLACEHOLDER_WHEN_BDCOM_ONUS', 50),

    'default_olt_id' => env('OPTICAL_DEFAULT_OLT_ID') ? (int) env('OPTICAL_DEFAULT_OLT_ID') : null,

    /** Poll interval for isp:collect-onu-signals (minutes). */
    'poll_interval_minutes' => (int) env('OPTICAL_POLL_INTERVAL', 10),

    /** When ≤60, scheduler runs collect-onu-signals every minute (small ISP). */
    'poll_interval_seconds' => (int) env('OPTICAL_POLL_INTERVAL_SECONDS', 60),

    /** Keep raw signal snapshots (days). Hourly rollups kept longer. */
    'snapshot_retention_days' => (int) env('OPTICAL_SNAPSHOT_RETENTION_DAYS', 14),

    'hourly_retention_days' => (int) env('OPTICAL_HOURLY_RETENTION_DAYS', 90),

    /**
     * RX bands (dBm). Higher (less negative) = stronger signal.
     * Excellent: -8 to -15 · Good: -15 to -22 · Weak: -22 to -27 · Critical: below -27
     */
    'rx_thresholds' => [
        'excellent_max' => (float) env('OPTICAL_RX_EXCELLENT_MAX', -8),
        'excellent_min' => (float) env('OPTICAL_RX_EXCELLENT_MIN', -15),
        'good_min' => (float) env('OPTICAL_RX_GOOD_MIN', -22),
        'weak_min' => (float) env('OPTICAL_RX_WEAK_MIN', -27),
        // Legacy keys (Filament filters / older code)
        'excellent' => (float) env('OPTICAL_RX_EXCELLENT_MAX', -8),
        'good' => (float) env('OPTICAL_RX_EXCELLENT_MIN', -15),
        'warning' => (float) env('OPTICAL_RX_GOOD_MIN', -22),
        'critical' => (float) env('OPTICAL_RX_WEAK_MIN', -27),
    ],

    'normalization' => [
        'default' => ['mode' => 'auto', 'min_dbm' => -60, 'max_dbm' => 10],
        'profiles' => [
            'bdcom_epon' => ['mode' => 'tenth_dbm', 'motion' => 10, 'divisor' => 10, 'min_dbm' => -60, 'max_dbm' => 10],
            'huawei_gpon' => ['mode' => 'tenth_dbm', 'divisor' => 10, 'min_dbm' => -60, 'max_dbm' => 10],
            'zte_gpon' => ['mode' => 'tenth_dbm', 'divisor' => 10, 'min_dbm' => -60, 'max_dbm' => 10],
            'vsol_gpon' => ['mode' => 'tenth_dbm', 'divisor' => 10, 'min_dbm' => -60, 'max_dbm' => 10],
            'fiberhome_gpon' => ['mode' => 'tenth_dbm', 'divisor' => 10, 'min_dbm' => -60, 'max_dbm' => 10],
            'generic_gpon' => ['mode' => 'auto', 'min_dbm' => -60, 'max_dbm' => 10],
        ],
    ],

    'smoothing' => [
        'window_size' => (int) env('OPTICAL_SMOOTH_WINDOW', 5),
        'min_samples' => (int) env('OPTICAL_SMOOTH_MIN_SAMPLES', 1),
        'spike_threshold_db' => (float) env('OPTICAL_SPIKE_THRESHOLD_DB', 5.0),
        'trim_extremes' => (int) env('OPTICAL_SMOOTH_TRIM', 0),
        'unstable_stddev_db' => (float) env('OPTICAL_UNSTABLE_STDDEV_DB', 2.5),
    ],

    /**
     * TX power normal range (dBm).
     */
    'tx_normal_min' => (float) env('OPTICAL_TX_MIN', 0.5),
    'tx_normal_max' => (float) env('OPTICAL_TX_MAX', 5.5),

    /**
     * Laser "too high" — RX above this (less negative) triggers Laser high status + optional alert.
     * Typical EPON: -8 dBm; short patch leads may read -1 to 0 dBm.
     */
    'rx_high_warn_above' => (float) env('OPTICAL_RX_HIGH_WARN_ABOVE', -8),

    /** TX above this dBm → Laser high on ONU transmit side. */
    'tx_high_warn_above' => (float) env('OPTICAL_TX_HIGH_WARN_ABOVE', 5.5),

    /** Create alerts when laser power exceeds high thresholds. */
    'alert_on_high_rx' => (bool) env('OPTICAL_ALERT_ON_HIGH_RX', true),
    'alert_on_high_tx' => (bool) env('OPTICAL_ALERT_ON_HIGH_TX', true),

    /** Sudden RX drop (dB) within one poll → alert. */
    'sudden_drop_db' => (float) env('OPTICAL_SUDDEN_DROP_DB', 3.0),

    /** Fiber cut: fraction of ONUs on OLT going LOS/offline at once. */
    'fiber_cut_onu_fraction' => (float) env('OPTICAL_FIBER_CUT_FRACTION', 0.3),

    'fiber_cut_min_onus' => (int) env('OPTICAL_FIBER_CUT_MIN_ONUS', 5),

    /** Auto-create support tickets for critical optical alerts. */
    'auto_ticket_enabled' => (bool) env('OPTICAL_AUTO_TICKET', true),

    /** Notify customer on weak signal (SMS/email when configured). */
    'notify_customer_weak_signal' => (bool) env('OPTICAL_NOTIFY_CUSTOMER', false),

    /** Notify ops via Telegram template. */
    'notify_ops' => (bool) env('OPTICAL_NOTIFY_OPS', true),

    'telegram' => [
        'enabled' => (bool) env('OPTICAL_TELEGRAM_ENABLED', true),
        'cooldown_minutes' => (int) env('OPTICAL_TELEGRAM_COOLDOWN_MINUTES', 15),
        'olt_health_enabled' => (bool) env('OPTICAL_TELEGRAM_OLT_HEALTH', true),
        'severities' => ['warning', 'critical', 'emergency'],
    ],

    /**
     * UI colors (Tailwind / Filament).
     */
    'colors' => [
        'excellent' => 'success',
        'good' => 'success',
        'warning' => 'warning',
        'critical' => 'danger',
        'high' => 'warning',
        'offline' => 'gray',
        'unknown' => 'gray',
    ],

    /**
     * Root cause hints from heuristics (not full AI).
     */
    'root_cause_rules' => [
        'los' => 'fiber_break',
        'power_fail' => 'onu_power',
        'auth_fail' => 'onu_registration',
        'mass_offline' => 'splitter_or_pon_failure',
        'weak_rx_cluster' => 'patch_cord_or_splitter',
        'tx_abnormal' => 'onu_laser_fault',
    ],
];
