<?php

/**
 * OLT management "driver" / product line (legacy panel style: BDCOM_EPON, etc.).
 * Used for UI + future automation; SNMP test still uses standard sysDescr (v2c).
 */
return [

    /** Aveis web UI default port (AV-OLT-XE08 — usually :8506). */
    'aveis_web_port' => (int) env('AVEIS_OLT_WEB_PORT', 8506),

    'drivers' => [
        'bdcom_epon' => [
            'label' => 'BDCOM EPON',
            'vendor' => 'bdcom',
        ],
        'bdcom_gpon' => [
            'label' => 'BDCOM GPON',
            'vendor' => 'bdcom',
        ],
        'zte_epon' => [
            'label' => 'ZTE EPON',
            'vendor' => 'zte',
        ],
        'zte_gpon' => [
            'label' => 'ZTE GPON',
            'vendor' => 'zte',
        ],
        'huawei_gpon' => [
            'label' => 'Huawei GPON',
            'vendor' => 'huawei',
        ],
        'fiberhome_gpon' => [
            'label' => 'Fiberhome GPON',
            'vendor' => 'fiberhome',
        ],
        'aveis_gpon' => [
            'label' => 'Aveis GPON (AV-OLT-XE08)',
            'vendor' => 'aveis',
        ],
        'aveis_epon' => [
            'label' => 'Aveis EPON (AVEIS_EPON)',
            'vendor' => 'aveis',
        ],
        'vsol_gpon' => [
            'label' => 'VSOL GPON',
            'vendor' => 'vsol',
        ],
        'ecom_gpon' => [
            'label' => 'Ecom GPON',
            'vendor' => 'ecom',
        ],
        'ecom_epon' => [
            'label' => 'Ecom EPON',
            'vendor' => 'ecom',
        ],
        'cdata_gpon' => [
            'label' => 'C-Data GPON',
            'vendor' => 'cdata',
        ],
        'generic_snmp' => [
            'label' => 'Generic SNMP (sysDescr only)',
            'vendor' => null,
        ],
    ],
];
