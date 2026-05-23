<?php

/**
 * Optical monitoring — database layout, retention, and table registry.
 * All tables live in the default Laravel connection (DB_DATABASE).
 */
return [

    'connection' => env('DB_CONNECTION', 'mysql'),

    /**
     * Live readings → devices (type=onu). History → onu_signal_logs.
     */
    'tables' => [
        'live' => [
            'devices' => 'ONU/OLT inventory + current rx_power_dbm, tx_power_dbm, onu_oper_status, olt_health (JSON)',
            'olt_ports' => 'PON port definitions per OLT',
        ],
        'history' => [
            'onu_signal_logs' => 'RX/TX snapshots (granularity: snapshot|hourly)',
            'olt_health_logs' => 'OLT CPU/RAM/temperature time series',
            'snmp_poll_logs' => 'OLT SNMP poll audit trail',
        ],
        'analytics' => [
            'onu_health_scores' => 'Latest health % per ONU (1 row per device)',
            'pon_signal_stats' => 'Aggregated PON port RX stats',
            'signal_predictions' => 'AI/heuristic risk rows',
        ],
        'alerts' => [
            'signal_alerts' => 'Weak RX, LOS, sudden drop (open|resolved)',
            'fiber_fault_logs' => 'Mass outage / fiber cut events',
        ],
    ],

    'retention' => [
        'snapshot_days' => (int) env('OPTICAL_SNAPSHOT_RETENTION_DAYS', 14),
        'hourly_days' => (int) env('OPTICAL_HOURLY_RETENTION_DAYS', 90),
        'olt_health_days' => (int) env('OPTICAL_OLT_HEALTH_RETENTION_DAYS', 30),
        'snmp_poll_days' => (int) env('OPTICAL_SNMP_POLL_RETENTION_DAYS', 14),
        'resolved_alert_days' => (int) env('OPTICAL_RESOLVED_ALERT_RETENTION_DAYS', 90),
        'prediction_days' => (int) env('OPTICAL_PREDICTION_RETENTION_DAYS', 7),
        'fiber_fault_days' => (int) env('OPTICAL_FIBER_FAULT_RETENTION_DAYS', 180),
    ],

    /** Key columns for SQL / reporting */
    'columns' => [
        'onu_live_rx' => 'devices.rx_power_dbm',
        'onu_live_tx' => 'devices.tx_power_dbm',
        'onu_status' => 'devices.onu_oper_status',
        'olt_health_json' => 'devices.olt_health',
    ],
];
