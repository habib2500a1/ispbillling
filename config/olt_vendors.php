<?php

/**
 * OLT vendor capabilities & CLI/SNMP command hints (documentation — not executed by the panel).
 * BDCom / VSOL / C-Data / others: extend with your NMS scripts or future drivers.
 */
return [

    'vendors' => [
        'huawei' => [
            'label' => 'Huawei',
            'support' => 'partial',
            'snmp' => ['IF-MIB', 'HUAWEI-XPON-MIB (vendor)'],
            'cli' => ['display ont info summary', 'display ont optical-info'],
            'notes' => 'MA5800 series: GPON OMCI via U2000/NCE or direct MML; SNMP OIDs vendor-specific.',
        ],
        'zte' => [
            'label' => 'ZTE',
            'support' => 'partial',
            'snmp' => ['IF-MIB', 'ZTE-AN-GPON-MIB'],
            'cli' => ['show gpon onu state', 'show pon power onu'],
            'notes' => 'C300/C600: CLI varies by firmware; use EMS for bulk.',
        ],
        'fiberhome' => [
            'label' => 'Fiberhome',
            'support' => 'partial',
            'snmp' => ['AN-GPON-MIB (varies)'],
            'cli' => ['show onu', 'show pon onu'],
            'notes' => 'AN5516 etc.: EMS preferred for provisioning.',
        ],
        'vsol' => [
            'label' => 'VSOL',
            'support' => 'stub',
            'snmp' => ['Standard IF-MIB where exposed', 'Vendor private MIB (firmware-dependent)'],
            'cli' => ['show onu', 'show interface gpon', 'onu reboot (varies)'],
            'notes' => 'VSOL OLT: store community in panel; poll ONUs via SNMP walk of vendor tree or TR-069 ACS.',
        ],
        'bdcom' => [
            'label' => 'BDCom',
            'support' => 'stub',
            'snmp' => ['IF-MIB', 'private OLT MIB per model'],
            'cli' => ['show onu', 'show pon', 'reboot onu (syntax per OS)'],
            'notes' => 'BDCom GPON: CLI reference in vendor manual; panel stores credentials only until a driver is added.',
        ],
        'cdata' => [
            'label' => 'C-Data',
            'support' => 'stub',
            'snmp' => ['IF-MIB', 'vendor ONU tables'],
            'cli' => ['show onu', 'show pon power'],
            'notes' => 'C-Data / FD / similar: use Web/CLI export; SNMP index maps to PON/ONU.',
        ],
        'alcatel' => [
            'label' => 'Alcatel-Lucent / Nokia',
            'support' => 'partial',
            'snmp' => ['TIMETRA-*', 'ALU GPON MIBs'],
            'cli' => ['show router interface', 'show gpon'],
            'notes' => 'ISAM FX: use 5620 SAM / NSP for provisioning.',
        ],
        'nokia' => [
            'label' => 'Nokia',
            'support' => 'partial',
            'snmp' => ['TIMETRA-*'],
            'cli' => ['show gpon', 'show ont'],
            'notes' => 'Same family as ALU ISAM in many deployments.',
        ],
        'other' => [
            'label' => 'Other',
            'support' => 'stub',
            'snmp' => [],
            'cli' => [],
            'notes' => 'Use devices.meta for custom OID / CLI snippets.',
        ],
    ],

    /**
     * When true, `isp:sync-onu-status-from-meta` copies meta → DB columns (see device_meta_portal_keys).
     */
    'meta_sync_enabled' => (bool) env('ONU_META_SYNC_ENABLED', true),

    /**
     * Keys on ONU `devices.meta` consumed by isp:sync-onu-status-from-meta (set from your NMS / poller).
     *
     * @var array{oper_status: string, offline_reason: string}
     */
    'device_meta_portal_keys' => [
        'oper_status' => 'portal_onu_oper_status',
        'offline_reason' => 'portal_offline_reason',
    ],
];
