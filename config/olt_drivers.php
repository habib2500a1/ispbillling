<?php

/**
 * OLT management "driver" / product line (legacy panel style: BDCOM_EPON, etc.).
 * Used for UI + future automation; SNMP test still uses standard sysDescr (v2c).
 */
return [

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
        'vsol_gpon' => [
            'label' => 'VSOL GPON',
            'vendor' => 'vsol',
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
