<?php

/**
 * GPON / EPON SNMP OID profiles (IF-MIB + vendor hints).
 * Walk requires PHP ext-snmp. Vendor-specific optical OIDs often need EMS export → devices.meta.
 */
return [

    'default_profile' => 'generic_gpon',

    'profiles' => [
        'generic_gpon' => [
            'label' => 'Generic GPON (IF-MIB)',
            'sys_descr' => '1.3.6.1.2.1.1.1.0',
            'sys_uptime' => '1.3.6.1.2.1.1.3.0',
            'if_table' => '1.3.6.1.2.1.2.2',
            'if_oper_status' => '1.3.6.1.2.1.2.2.1.8',
            'if_descr' => '1.3.6.1.2.1.2.2.1.2',
            'if_name' => '1.3.6.1.2.1.31.1.1.1.1',
            /** Standard BRIDGE-MIB FDB — vendor-neutral customer-MAC bridge (see OltFdbMacBridgeService). */
            'fdb_qbridge' => '1.3.6.1.2.1.17.7.1.2.2.1.2',
            'fdb_dot1d' => '1.3.6.1.2.1.17.4.3.1.2',
            'fdb_baseport_ifindex' => '1.3.6.1.2.1.17.1.4.1.2',
        ],
        'huawei_gpon' => [
            'label' => 'Huawei GPON',
            'extends' => 'generic_gpon',
            'notes' => 'SNMP walk via HUAWEI-GPON-MIB (MA5800 / MA5600 family).',
            /** Index suffix: frame.slot.port.onu */
            'huawei_gpon_onu_rx' => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.14',
            'huawei_gpon_onu_tx' => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15',
            'huawei_gpon_onu_run_state' => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.24',
            'huawei_gpon_onu_sn' => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.2',
            'huawei_gpon_onu_distance' => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.18',
        ],
        'zte_gpon' => [
            'label' => 'ZTE GPON (C300/C320)',
            'extends' => 'vsol_gpon',
            'notes' => 'ZTE / V-Solution clone MIB — set ZTE_SNMP_ONU_* or global VSOL_SNMP_ONU_* in .env.',
            'vsol_onu_desc' => env('ZTE_SNMP_ONU_DESC_OID', env('VSOL_SNMP_ONU_DESC_OID', '')),
            'vsol_onu_status' => env('ZTE_SNMP_ONU_STATUS_OID', env('VSOL_SNMP_ONU_STATUS_OID', '')),
            'vsol_onu_mac' => env('ZTE_SNMP_ONU_MAC_OID', env('VSOL_SNMP_ONU_MAC_OID', '')),
            'vsol_onu_rx' => env('ZTE_SNMP_ONU_RX_OID', env('VSOL_SNMP_ONU_RX_OID', '')),
            'vsol_onu_tx' => env('ZTE_SNMP_ONU_TX_OID', env('VSOL_SNMP_ONU_TX_OID', '')),
            'vsol_onu_temp' => env('ZTE_SNMP_ONU_TEMP_OID', env('VSOL_SNMP_ONU_TEMP_OID', '')),
            'vsol_onu_voltage' => env('ZTE_SNMP_ONU_VOLTAGE_OID', env('VSOL_SNMP_ONU_VOLTAGE_OID', '')),
            'vsol_onu_sn' => env('ZTE_SNMP_ONU_SN_OID', env('VSOL_SNMP_ONU_SN_OID', '')),
        ],
        'zte_epon' => [
            'label' => 'ZTE EPON',
            'extends' => 'zte_gpon',
        ],
        'bdcom_gpon' => [
            'label' => 'BDCOM GPON',
            'extends' => 'bdcom_epon',
            'notes' => 'Many BDCOM GPON OLTs share the same enterprise 3320 EPON-style ONU MIB.',
        ],
        'bdcom_epon' => [
            'label' => 'BDCOM EPON',
            'extends' => 'generic_gpon',
            'bdcom_epon_onu_mac' => '1.3.6.1.4.1.3320.101.10.1.1.3',
            'bdcom_epon_onu_rx' => '1.3.6.1.4.1.3320.101.10.5.1.5',
            'bdcom_epon_onu_tx' => '1.3.6.1.4.1.3320.101.10.5.1.6',
            'bdcom_epon_onu_status' => '1.3.6.1.4.1.3320.101.11.4.1.5',
            /** ONU ranging distance in metres, indexed by pon_ifindex.<6 mac octets>. */
            'bdcom_epon_onu_distance' => '1.3.6.1.4.1.3320.101.11.1.1.7',
            /** ONU description / subscriber name set on OLT (for auto-link by PPP login). */
            'bdcom_epon_onu_desc' => '1.3.6.1.4.1.3320.101.10.1.1.2',
            'if_descr' => '1.3.6.1.2.1.2.2.1.2',
            /**
             * Forwarding (FDB) bridge — learns the CUSTOMER router MAC behind each ONU, which
             * matches MikroTik PPPoE caller_id. This is the reliable ONU→subscriber auto-detect key
             * (the ONU's own MAC never matches the PPP caller MAC). Walk requires the OID-increasing
             * check disabled (BDCOM returns per-VLAN FDB out of order) — see SnmpClient::realWalkUnchecked.
             */
            'bdcom_epon_fdb_qbridge' => '1.3.6.1.2.1.17.7.1.2.2.1.2', // dot1qTpFdbPort  (MAC → bridgePort)
            'bdcom_epon_fdb_dot1d' => '1.3.6.1.2.1.17.4.3.1.2',       // dot1dTpFdbPort  (legacy fallback)
            'bdcom_epon_baseport_ifindex' => '1.3.6.1.2.1.17.1.4.1.2', // dot1dBasePortIfIndex (bridgePort → ifIndex)
            /** NMS-EPON-ONU opModuleTemp / opModuleVolt (1/256 °C, 100 µV). */
            'bdcom_onu_temperature' => env('BDCOM_ONU_TEMP_OID', '1.3.6.1.4.1.3320.101.10.5.1.2'),
            'bdcom_onu_voltage' => env('BDCOM_ONU_VOLTAGE_OID', '1.3.6.1.4.1.3320.101.10.5.1.3'),
        ],
        'fiberhome_gpon' => [
            'label' => 'Fiberhome GPON (AN55xx)',
            'extends' => 'vsol_gpon',
            'vsol_onu_desc' => env('FIBERHOME_SNMP_ONU_DESC_OID', env('VSOL_SNMP_ONU_DESC_OID', '')),
            'vsol_onu_status' => env('FIBERHOME_SNMP_ONU_STATUS_OID', env('VSOL_SNMP_ONU_STATUS_OID', '')),
            'vsol_onu_mac' => env('FIBERHOME_SNMP_ONU_MAC_OID', env('VSOL_SNMP_ONU_MAC_OID', '')),
            'vsol_onu_rx' => env('FIBERHOME_SNMP_ONU_RX_OID', env('VSOL_SNMP_ONU_RX_OID', '')),
            'vsol_onu_tx' => env('FIBERHOME_SNMP_ONU_TX_OID', env('VSOL_SNMP_ONU_TX_OID', '')),
            'vsol_onu_temp' => env('FIBERHOME_SNMP_ONU_TEMP_OID', env('VSOL_SNMP_ONU_TEMP_OID', '')),
            'vsol_onu_voltage' => env('FIBERHOME_SNMP_ONU_VOLTAGE_OID', env('VSOL_SNMP_ONU_VOLTAGE_OID', '')),
            'vsol_onu_sn' => env('FIBERHOME_SNMP_ONU_SN_OID', env('VSOL_SNMP_ONU_SN_OID', '')),
        ],
        'nokia_gpon' => [
            'label' => 'Nokia / Alcatel GPON',
            'extends' => 'vsol_gpon',
            'vsol_onu_desc' => env('NOKIA_SNMP_ONU_DESC_OID', env('VSOL_SNMP_ONU_DESC_OID', '')),
            'vsol_onu_status' => env('NOKIA_SNMP_ONU_STATUS_OID', env('VSOL_SNMP_ONU_STATUS_OID', '')),
            'vsol_onu_mac' => env('NOKIA_SNMP_ONU_MAC_OID', env('VSOL_SNMP_ONU_MAC_OID', '')),
            'vsol_onu_rx' => env('NOKIA_SNMP_ONU_RX_OID', env('VSOL_SNMP_ONU_RX_OID', '')),
            'vsol_onu_tx' => env('NOKIA_SNMP_ONU_TX_OID', env('VSOL_SNMP_ONU_TX_OID', '')),
            'vsol_onu_temp' => env('NOKIA_SNMP_ONU_TEMP_OID', env('VSOL_SNMP_ONU_TEMP_OID', '')),
            'vsol_onu_voltage' => env('NOKIA_SNMP_ONU_VOLTAGE_OID', env('VSOL_SNMP_ONU_VOLTAGE_OID', '')),
            'vsol_onu_sn' => env('NOKIA_SNMP_ONU_SN_OID', env('VSOL_SNMP_ONU_SN_OID', '')),
        ],
        'raisecom_gpon' => [
            'label' => 'Raisecom GPON',
            'extends' => 'vsol_gpon',
            'vsol_onu_desc' => env('RAISECOM_SNMP_ONU_DESC_OID', env('VSOL_SNMP_ONU_DESC_OID', '')),
            'vsol_onu_status' => env('RAISECOM_SNMP_ONU_STATUS_OID', env('VSOL_SNMP_ONU_STATUS_OID', '')),
            'vsol_onu_mac' => env('RAISECOM_SNMP_ONU_MAC_OID', env('VSOL_SNMP_ONU_MAC_OID', '')),
            'vsol_onu_rx' => env('RAISECOM_SNMP_ONU_RX_OID', env('VSOL_SNMP_ONU_RX_OID', '')),
            'vsol_onu_tx' => env('RAISECOM_SNMP_ONU_TX_OID', env('VSOL_SNMP_ONU_TX_OID', '')),
            'vsol_onu_temp' => env('RAISECOM_SNMP_ONU_TEMP_OID', env('VSOL_SNMP_ONU_TEMP_OID', '')),
            'vsol_onu_voltage' => env('RAISECOM_SNMP_ONU_VOLTAGE_OID', env('VSOL_SNMP_ONU_VOLTAGE_OID', '')),
            'vsol_onu_sn' => env('RAISECOM_SNMP_ONU_SN_OID', env('VSOL_SNMP_ONU_SN_OID', '')),
        ],
        'raisecom_epon' => [
            'label' => 'Raisecom EPON',
            'extends' => 'raisecom_gpon',
        ],
        'aveis_gpon' => [
            'label' => 'Aveis GPON (AV-OLT-XE08-L3)',
            'extends' => 'generic_gpon',
            'enterprise' => '50224',
            'aveis_onu_table' => '1.3.6.1.4.1.50224.3.3.2.1',
            'aveis_pon_table' => '1.3.6.1.4.1.50224.3.2.1.1',
            /** ONU table 1.3.6.1.4.1.50224.3.3.2.1.{col} identity columns. */
            'aveis_onu_label_column' => (int) env('AVEIS_ONU_LABEL_COLUMN', 2),
            'aveis_onu_status_column' => (int) env('AVEIS_ONU_STATUS_COLUMN', 3),
            'aveis_onu_mac_column' => (int) env('AVEIS_ONU_MAC_COLUMN', 7),
            'aveis_onu_name_column' => (int) env('AVEIS_ONU_NAME_COLUMN', 12),
            /** ONU table 1.3.6.1.4.1.50224.3.3.2.1.{col} — set 0 to disable column walk. */
            'aveis_onu_tx_column' => (int) env('AVEIS_ONU_TX_COLUMN', 0),
            'aveis_onu_temp_column' => (int) env('AVEIS_ONU_TEMP_COLUMN', 18),
            'aveis_onu_voltage_column' => (int) env('AVEIS_ONU_VOLTAGE_COLUMN', 19),
            'aveis_onu_distance_column' => (int) env('AVEIS_ONU_DISTANCE_COLUMN', 0),
        ],
        'aveis_epon' => [
            'label' => 'Aveis EPON',
            'extends' => 'aveis_gpon',
            /**
             * EPON ONUs are MAC-registered. Same enterprise 50224 ONU table as GPON by default,
             * but every column is overridable via AVEIS_EPON_* env (falls back to AVEIS_* / GPON
             * defaults) for EPON firmware that lays the table out differently.
             */
            'aveis_onu_table' => env('AVEIS_EPON_ONU_TABLE', '1.3.6.1.4.1.50224.3.3.2.1'),
            'aveis_onu_label_column' => (int) env('AVEIS_EPON_ONU_LABEL_COLUMN', env('AVEIS_ONU_LABEL_COLUMN', 2)),
            'aveis_onu_status_column' => (int) env('AVEIS_EPON_ONU_STATUS_COLUMN', env('AVEIS_ONU_STATUS_COLUMN', 3)),
            'aveis_onu_mac_column' => (int) env('AVEIS_EPON_ONU_MAC_COLUMN', env('AVEIS_ONU_MAC_COLUMN', 7)),
            'aveis_onu_name_column' => (int) env('AVEIS_EPON_ONU_NAME_COLUMN', env('AVEIS_ONU_NAME_COLUMN', 12)),
            'aveis_onu_rx_column' => (int) env('AVEIS_EPON_ONU_RX_COLUMN', env('AVEIS_ONU_RX_COLUMN', 15)),
            'aveis_onu_tx_column' => (int) env('AVEIS_EPON_ONU_TX_COLUMN', env('AVEIS_ONU_TX_COLUMN', 0)),
            'aveis_onu_temp_column' => (int) env('AVEIS_EPON_ONU_TEMP_COLUMN', env('AVEIS_ONU_TEMP_COLUMN', 18)),
            'aveis_onu_voltage_column' => (int) env('AVEIS_EPON_ONU_VOLTAGE_COLUMN', env('AVEIS_ONU_VOLTAGE_COLUMN', 19)),
            'aveis_onu_distance_column' => (int) env('AVEIS_EPON_ONU_DISTANCE_COLUMN', env('AVEIS_ONU_DISTANCE_COLUMN', 0)),
        ],
        'vsol_gpon' => [
            'label' => 'VSOL GPON',
            'extends' => 'generic_gpon',
            'enterprise' => '37950',
            /** Set after snmpwalk on your VSOL OLT (firmware-dependent). */
            'vsol_onu_desc' => env('VSOL_SNMP_ONU_DESC_OID', ''),
            'vsol_onu_status' => env('VSOL_SNMP_ONU_STATUS_OID', ''),
            'vsol_onu_mac' => env('VSOL_SNMP_ONU_MAC_OID', ''),
            'vsol_onu_rx' => env('VSOL_SNMP_ONU_RX_OID', ''),
            'vsol_onu_tx' => env('VSOL_SNMP_ONU_TX_OID', ''),
            'vsol_onu_temp' => env('VSOL_SNMP_ONU_TEMP_OID', ''),
            'vsol_onu_voltage' => env('VSOL_SNMP_ONU_VOLTAGE_OID', ''),
            'vsol_onu_sn' => env('VSOL_SNMP_ONU_SN_OID', ''),
            /** RX/TX SNMP integer scale (10 = 0.1 dBm per step). */
            'vsol_optical_scale' => (int) env('VSOL_OPTICAL_SCALE', 10),
        ],
        'ecom_gpon' => [
            'label' => 'Ecom GPON',
            'extends' => 'vsol_gpon',
            'notes' => 'Many Ecom OLTs share VSOL/ZTE MIB — set VSOL_SNMP_* env or snmpwalk enterprise tree.',
        ],
        'ecom_epon' => [
            'label' => 'Ecom EPON',
            'extends' => 'ecom_gpon',
        ],
        'cdata_gpon' => [
            'label' => 'C-Data GPON',
            'extends' => 'vsol_gpon',
            'vsol_onu_desc' => env('CDATA_SNMP_ONU_DESC_OID', env('VSOL_SNMP_ONU_DESC_OID', '')),
            'vsol_onu_status' => env('CDATA_SNMP_ONU_STATUS_OID', env('VSOL_SNMP_ONU_STATUS_OID', '')),
            'vsol_onu_mac' => env('CDATA_SNMP_ONU_MAC_OID', env('VSOL_SNMP_ONU_MAC_OID', '')),
            'vsol_onu_rx' => env('CDATA_SNMP_ONU_RX_OID', env('VSOL_SNMP_ONU_RX_OID', '')),
            'vsol_onu_tx' => env('CDATA_SNMP_ONU_TX_OID', env('VSOL_SNMP_ONU_TX_OID', '')),
            'vsol_onu_temp' => env('CDATA_SNMP_ONU_TEMP_OID', env('VSOL_SNMP_ONU_TEMP_OID', '')),
            'vsol_onu_voltage' => env('CDATA_SNMP_ONU_VOLTAGE_OID', env('VSOL_SNMP_ONU_VOLTAGE_OID', '')),
            'vsol_onu_sn' => env('CDATA_SNMP_ONU_SN_OID', env('VSOL_SNMP_ONU_SN_OID', '')),
        ],
    ],

    /**
     * Drivers using config-driven ONU SNMP walks (VsolGponOnuSyncService).
     * Dedicated syncers (BDCOM/Huawei/Aveis) take priority in OltOnuSyncCoordinator.
     */
    'config_driven_drivers' => [
        'vsol_gpon', 'ecom_gpon', 'ecom_epon', 'cdata_gpon',
        'zte_gpon', 'zte_epon',
        'fiberhome_gpon', 'nokia_gpon', 'raisecom_gpon', 'raisecom_epon',
        'generic_snmp',
    ],

    'config_driven_vendors' => [
        'vsol', 'v-solution', 'ecom', 'cdata', 'c-data',
        'zte', 'fiberhome', 'nokia', 'alcatel', 'raisecom',
    ],

    /** When true, SNMP sysDescr may reset olt_driver if it is in auto_driver_overwritable. */
    'auto_driver_from_snmp' => (bool) env('OLT_AUTO_DRIVER_FROM_SNMP', true),

    'auto_driver_overwritable' => [
        '', 'generic_snmp', 'generic_gpon',
        'vsol_gpon', 'ecom_gpon', 'ecom_epon', 'cdata_gpon',
        'zte_gpon', 'zte_epon', 'fiberhome_gpon', 'nokia_gpon', 'raisecom_gpon', 'raisecom_epon',
    ],

    'vsol_optical_scale' => (int) env('VSOL_OPTICAL_SCALE', 10),

    /** BDCOM EPON SNMP walks can take 30–120s on busy OLTs. */
    'bdcom_epon_walk_timeout_us' => (int) env('BDCOM_EPON_SNMP_TIMEOUT_US', 15000000),

    /** Huawei GPON optical walk timeout (µs). */
    'huawei_gpon_walk_timeout_us' => (int) env('HUAWEI_GPON_SNMP_TIMEOUT_US', 20000000),

    'aveis_gpon_walk_timeout_us' => (int) env('AVEIS_GPON_SNMP_TIMEOUT_US', 20000000),

    /** Auto-probe Aveis ONU table columns (1..N) on first sync; cached on OLT meta. */
    'aveis_column_probe_max' => (int) env('AVEIS_COLUMN_PROBE_MAX', 22),

    'aveis_column_probe_min_score' => (float) env('AVEIS_COLUMN_PROBE_MIN_SCORE', 0.35),

    'aveis_column_map_ttl_days' => (int) env('AVEIS_COLUMN_MAP_TTL_DAYS', 30),

    /** Aveis tables may return OIDs out of order — use snmpbulkwalk -Cc style walk. */
    'aveis_snmp_use_unchecked_walk' => (bool) env('AVEIS_SNMP_UNCHECKED_WALK', true),

    /** When SNMP walk returns 0 rows but GET works, probe PON×ONU indices via GET. */
    'aveis_index_scan_enabled' => (bool) env('AVEIS_INDEX_SCAN_ENABLED', true),

    'aveis_index_scan_max_pon' => (int) env('AVEIS_INDEX_SCAN_MAX_PON', 8),

    'aveis_index_scan_max_onu' => (int) env('AVEIS_INDEX_SCAN_MAX_ONU', 64),

    'aveis_index_get_timeout_us' => (int) env('AVEIS_INDEX_GET_TIMEOUT_US', 600000),

    'vsol_gpon_walk_timeout_us' => (int) env('VSOL_GPON_SNMP_TIMEOUT_US', 15000000),

    /** Aveis ONU table column for receive power (MIB …3.3.2.1.{col}). XE08 uses col 15. */
    'aveis_onu_rx_column' => (int) env('AVEIS_ONU_RX_COLUMN', 15),

    /** Aveis voltage decode: hundredth_v (÷100) | tenth_v (÷10) | raw */
    'aveis_voltage_mode' => env('AVEIS_VOLTAGE_MODE', 'hundredth_v'),

    /**
     * Aveis RX decode: col15_divisor (default, matches OLT “Receive Power”) | negative_tenth | tenth_dbm | skip
     * col15_divisor: RX dBm ≈ −(raw / aveis_rx_divisor), e.g. 841 → −14.67 dBm
     */
    'aveis_rx_mode' => env('AVEIS_RX_MODE', 'col15_divisor'),

    'aveis_rx_divisor' => (float) env('AVEIS_RX_DIVISOR', 57.3),

    /** Ignore col15 below/above valid window (outside OLT “Receive Power” range). */
    'aveis_rx_raw_min' => (int) env('AVEIS_RX_RAW_MIN', 400),

    /** Ignore col15 above this (OLT “N/A” / fault codes — not real dBm). */
    'aveis_rx_raw_max' => (int) env('AVEIS_RX_RAW_MAX', 2000),

    /** Reject decoded RX weaker than this (below typical ONU sensitivity). */
    'aveis_rx_dbm_floor' => (float) env('AVEIS_RX_DBM_FLOOR', -35),

    'driver_to_profile' => [
        'huawei_gpon' => 'huawei_gpon',
        'zte_gpon' => 'zte_gpon',
        'zte_epon' => 'zte_epon',
        'bdcom_gpon' => 'bdcom_gpon',
        'bdcom_epon' => 'bdcom_epon',
        'fiberhome_gpon' => 'fiberhome_gpon',
        'aveis_gpon' => 'aveis_gpon',
        'aveis_epon' => 'aveis_epon',
        'vsol_gpon' => 'vsol_gpon',
        'ecom_gpon' => 'ecom_gpon',
        'ecom_epon' => 'ecom_epon',
        'cdata_gpon' => 'cdata_gpon',
        'nokia_gpon' => 'nokia_gpon',
        'raisecom_gpon' => 'raisecom_gpon',
        'raisecom_epon' => 'raisecom_epon',
        'generic_snmp' => 'generic_gpon',
    ],

    /**
     * Default PON slots per OLT vendor (card + PON index range). New OLTs get these ports automatically.
     * Aveis XE08 = 8 PON on card 1; BDCOM = 8 PON on card 0 → 16 rows for a typical two-OLT tenant.
     */
    'olt_pon_catalog' => [
        'aveis' => ['cards' => [1], 'pon_min' => 1, 'pon_max' => 8],
        'bdcom' => ['cards' => [0], 'pon_min' => 1, 'pon_max' => 8],
        'huawei' => ['cards' => [0], 'pon_min' => 1, 'pon_max' => 16],
        'zte' => ['cards' => [0], 'pon_min' => 1, 'pon_max' => 8],
        'default' => ['cards' => [0], 'pon_min' => 1, 'pon_max' => 8],
    ],

    /** Meta keys (external NMS → devices.meta) for ONU optical levels */
    'onu_meta_keys' => [
        'rx_power_dbm' => ['onu_rx_dbm', 'rx_power', 'rx_power_dbm', 'optical_rx'],
        'tx_power_dbm' => ['onu_tx_dbm', 'tx_power', 'tx_power_dbm', 'optical_tx'],
        'onu_oper_status' => ['onu_status', 'oper_status', 'portal_onu_oper_status'],
        'temperature_c' => ['temperature_c', 'temperature', 'onu_temperature', 'temp_c', 'temp'],
        'voltage_v' => ['voltage_v', 'voltage', 'onu_voltage', 'supply_voltage'],
    ],
];
