<?php

/**
 * OLT management "driver" / product line (legacy panel style: BDCOM_EPON, etc.).
 * Used for UI + SNMP sync routing; dedicated MIB syncers override config-driven walks.
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
            'label' => 'ZTE GPON (C300/C320)',
            'vendor' => 'zte',
        ],
        'huawei_gpon' => [
            'label' => 'Huawei GPON (MA5600/MA5800)',
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
            'label' => 'Aveis EPON',
            'vendor' => 'aveis',
        ],
        'vsol_gpon' => [
            'label' => 'VSOL / V-Solution GPON',
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
        'nokia_gpon' => [
            'label' => 'Nokia / Alcatel GPON',
            'vendor' => 'nokia',
        ],
        'raisecom_gpon' => [
            'label' => 'Raisecom GPON',
            'vendor' => 'raisecom',
        ],
        'raisecom_epon' => [
            'label' => 'Raisecom EPON',
            'vendor' => 'raisecom',
        ],
        'generic_snmp' => [
            'label' => 'Other / Custom SNMP (any model)',
            'vendor' => null,
        ],
    ],
];
