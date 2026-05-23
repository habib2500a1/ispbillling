<?php

/**
 * OLT health SNMP OID profiles (HOST-RESOURCES-MIB + vendor hints).
 * Values are merged with devices.meta overrides (cpu_percent, memory_percent, etc.).
 */
return [

    'default_profile' => 'host_resources',

    'vendor_to_profile' => [
        'huawei' => 'huawei',
        'zte' => 'zte',
        'bdcom' => 'bdcom',
        'fiberhome' => 'host_resources',
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
            'label' => 'BDCOM (HOST-RESOURCES fallback)',
            'extends' => 'host_resources',
        ],
    ],

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
