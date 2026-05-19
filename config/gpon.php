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
            'notes' => 'Use U2000/NCE export to meta or HUAWEI-XPON-MIB walk when available.',
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
        'vsol_gpon' => [
            'label' => 'VSOL GPON',
            'extends' => 'generic_gpon',
        ],
        'cdata_gpon' => [
            'label' => 'C-Data GPON',
            'extends' => 'generic_gpon',
        ],
    ],

    /** BDCOM EPON SNMP walks can take 30–120s on busy OLTs. */
    'bdcom_epon_walk_timeout_us' => (int) env('BDCOM_EPON_SNMP_TIMEOUT_US', 8000000),

    'driver_to_profile' => [
        'huawei_gpon' => 'huawei_gpon',
        'zte_gpon' => 'zte_gpon',
        'zte_epon' => 'zte_gpon',
        'bdcom_gpon' => 'bdcom_gpon',
        'bdcom_epon' => 'bdcom_epon',
        'fiberhome_gpon' => 'fiberhome_gpon',
        'vsol_gpon' => 'vsol_gpon',
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
