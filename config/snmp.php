<?php

return [

    /** Microseconds (PHP snmp2_get timeout argument; net-snmp defTimeout is in seconds — we set per-request). */
    'timeout_us' => (int) env('SNMP_TIMEOUT_US', 2000000),

    /** net-snmp default retries = 5; keep lower for panel responsiveness unless OLT is slow. */
    'retries' => (int) env('SNMP_RETRIES', 1),

    /**
     * Apply net-snmp client settings via PHP ext-snmp (SNMP_VALUE_PLAIN + numeric OIDs).
     * Required for BDCOM ONU MAC (6-byte octet string). See github.com/net-snmp/net-snmp FAQ.
     */
    'use_plain_values' => (bool) env('SNMP_USE_PLAIN_VALUES', true),

    /**
     * Panel "Test SNMP" requires ext-snmp linked to net-snmp libs.
     * Ubuntu: sudo apt install snmp php8.3-snmp && sudo systemctl restart php8.3-fpm
     * CLI test: snmpwalk -v2c -c COMMUNITY OLT_IP 1.3.6.1.2.1.1.1.0
     */
    'requires_php_extension' => 'snmp',
];
