<?php

/**
 * OLT health SNMP OID profiles (HOST-RESOURCES-MIB + vendor hints).
 * Values are merged with devices.meta overrides (cpu_percent, memory_percent, etc.).
 */
return [

    'default_profile' => 'host_resources',

    /** Maps devices.olt_driver → health profile (auto on each OLT SNMP poll). */
    'driver_to_profile' => [
        'huawei_gpon' => 'huawei',
        'zte_gpon' => 'zte',
        'zte_epon' => 'zte',
        'bdcom_gpon' => 'bdcom',
        'bdcom_epon' => 'bdcom',
        'aveis_gpon' => 'aveis',
        'aveis_epon' => 'aveis',
        'vsol_gpon' => 'host_resources',
        'ecom_gpon' => 'host_resources',
        'cdata_gpon' => 'host_resources',
    ],

    'vendor_to_profile' => [
        'huawei' => 'huawei',
        'zte' => 'zte',
        'bdcom' => 'bdcom',
        'fiberhome' => 'host_resources',
        'aveis' => 'aveis',
        'ecom' => 'host_resources',
        'vsol' => 'host_resources',
        'nokia' => 'host_resources',
        'alcatel' => 'host_resources',
        'cdata' => 'host_resources',
        'other' => 'host_resources',
    ],

    'profiles' => [
        'host_resources' => [
            'label' => 'HOST-RESOURCES-MIB',
            'hr_processor_load' => '1.3.6.1.2.1.25.3.3.1.2',
            'hr_storage_descr' => '1.3.6.1.2.1.25.2.3.1.3',
            'hr_storage_allocation_units' => '1.3.6.1.2.1.25.2.3.1.4',
            'hr_storage_size' => '1.3.6.1.2.1.25.2.3.1.5',
            'hr_storage_used' => '1.3.6.1.2.1.25.2.3.1.6',
            'memory_descr_match' => ['physical memory', 'memory', 'ram'],
        ],
        'huawei' => [
            'label' => 'Huawei entity MIB',
            'extends' => 'host_resources',
            'cpu_usage' => '1.3.6.1.4.1.2011.6.3.4.1.1.2',
            'memory_usage' => '1.3.6.1.4.1.2011.6.3.4.1.1.3',
            'temperature' => '1.3.6.1.4.1.2011.6.3.4.1.1.4',
        ],
        'zte' => [
            'label' => 'ZTE (HOST-RESOURCES fallback)',
            'extends' => 'host_resources',
            'cpu_usage' => '1.3.6.1.4.1.3902.1082.500.20.2.1.3',
            'memory_usage' => '1.3.6.1.4.1.3902.1082.500.20.2.1.4',
        ],
        'bdcom' => [
            'label' => 'BDCOM EPON/GPON',
            'extends' => 'host_resources',
            /** BDCOM-PROCESS-MIB — 1-minute CPU busy % (scalar .1) */
            'cpu_usage' => '1.3.6.1.4.1.3320.9.109.1.1.1.1.4',
            'cpu_scalars' => [
                '1.3.6.1.4.1.3320.9.109.1.1.1.1.4.1',
                '1.3.6.1.4.1.3320.9.109.1.1.1.1.3.1',
            ],
            /** nmsCardMemoryUsage / nmscardTemperature (%) */
            'memory_usage' => '1.3.6.1.4.1.3320.3.6.10.1.12',
            'temperature' => '1.3.6.1.4.1.3320.3.6.10.1.13',
            'temperature_fallback' => '1.3.6.1.4.1.3320.3.6.14',
        ],
        'aveis' => [
            'label' => 'Aveis AV-OLT (HOST-RESOURCES + ENTITY-SENSOR)',
            'extends' => 'host_resources',
            'entity_sensor_value' => '1.3.6.1.2.1.99.1.1.1.1.4',
        ],
    ],

    /** ENTITY-SENSOR-MIB fallback for any OLT when vendor temp OID is empty */
    'entity_sensor_value' => '1.3.6.1.2.1.99.1.1.1.1.4',

    /** Meta keys on OLT devices.meta from external NMS */
    'meta_keys' => [
        'cpu_percent' => ['cpu_percent', 'cpu_usage', 'olt_cpu'],
        'memory_percent' => ['memory_percent', 'mem_usage', 'olt_memory'],
        'temperature_c' => ['temperature_c', 'temperature', 'olt_temp'],
        'fan_status' => ['fan_status', 'fan_ok', 'fans_ok'],
        'power_supply_status' => ['power_supply_status', 'power_ok', 'psu_status'],
    ],

    'thresholds' => [
        'cpu_warning' => (int) env('OLT_CPU_WARN', 75),
        'cpu_critical' => (int) env('OLT_CPU_CRIT', 90),
        'memory_warning' => (int) env('OLT_MEM_WARN', 80),
        'memory_critical' => (int) env('OLT_MEM_CRIT', 92),
        'temperature_warning' => (float) env('OLT_TEMP_WARN', 55),
        'temperature_critical' => (float) env('OLT_TEMP_CRIT', 70),
    ],
];
