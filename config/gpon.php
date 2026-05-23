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
            'label' => 'ZTE GPON',
            'extends' => 'generic_gpon',
        ],
        'bdcom_gpon' => [
            'label' => 'BDCOM GPON',
            'extends' => 'generic_gpon',
        ],
        'bdcom_epon' => [
            'label' => 'BDCOM EPON',
            'extends' => 'generic_gpon',
            'bdcom_epon_onu_mac' => '1.3.6.1.4.1.3320.101.10.1.1.3',
            'bdcom_epon_onu_rx' => '1.3.6.1.4.1.3320.101.10.5.1.5',
            'bdcom_epon_onu_tx' => '1.3.6.1.4.1.3320.101.10.5.1.6',
            'bdcom_epon_onu_status' => '1.3.6.1.4.1.3320.101.11.4.1.5',
            /** ONU description / subscriber name set on OLT (for auto-link by PPP login). */
            'bdcom_epon_onu_desc' => '1.3.6.1.4.1.3320.101.10.1.1.2',
            'if_descr' => '1.3.6.1.2.1.2.2.1.2',
        ],
        'fiberhome_gpon' => [
            'label' => 'Fiberhome GPON',
            'extends' => 'generic_gpon',
        ],
        'aveis_gpon' => [
            'label' => 'Aveis GPON (AV-OLT-XE08-L3)',
            'extends' => 'generic_gpon',
            'enterprise' => '50224',
            'aveis_onu_table' => '1.3.6.1.4.1.50224.3.3.2.1',
            'aveis_pon_table' => '1.3.6.1.4.1.50224.3.2.1.1',
        ],
        'aveis_epon' => [
            'label' => 'Aveis EPON',
            'extends' => 'aveis_gpon',
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
            'vsol_onu_sn' => env('VSOL_SNMP_ONU_SN_OID', ''),
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
        ],
    ],

    /** BDCOM EPON SNMP walks can take 30–120s on busy OLTs. */
    'bdcom_epon_walk_timeout_us' => (int) env('BDCOM_EPON_SNMP_TIMEOUT_US', 8000000),

    /** Huawei GPON optical walk timeout (µs). */
    'huawei_gpon_walk_timeout_us' => (int) env('HUAWEI_GPON_SNMP_TIMEOUT_US', 12000000),

    'aveis_gpon_walk_timeout_us' => (int) env('AVEIS_GPON_SNMP_TIMEOUT_US', 10000000),

    'vsol_gpon_walk_timeout_us' => (int) env('VSOL_GPON_SNMP_TIMEOUT_US', 10000000),

    /** Aveis ONU table column for receive power (MIB …3.3.2.1.{col}). XE08 uses col 15. */
    'aveis_onu_rx_column' => (int) env('AVEIS_ONU_RX_COLUMN', 15),

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
        'zte_epon' => 'zte_gpon',
        'bdcom_gpon' => 'bdcom_gpon',
        'bdcom_epon' => 'bdcom_epon',
        'fiberhome_gpon' => 'fiberhome_gpon',
        'aveis_gpon' => 'aveis_gpon',
        'aveis_epon' => 'aveis_gpon',
        'vsol_gpon' => 'vsol_gpon',
        'ecom_gpon' => 'ecom_gpon',
        'ecom_epon' => 'ecom_gpon',
        'cdata_gpon' => 'cdata_gpon',
        'generic_snmp' => 'generic_gpon',
    ],

    /** Meta keys (external NMS → devices.meta) for ONU optical levels */
    'onu_meta_keys' => [
        'rx_power_dbm' => ['onu_rx_dbm', 'rx_power', 'rx_power_dbm', 'optical_rx'],
        'tx_power_dbm' => ['onu_tx_dbm', 'tx_power', 'tx_power_dbm', 'optical_tx'],
        'onu_oper_status' => ['onu_status', 'oper_status', 'portal_onu_oper_status'],
    ],
];
