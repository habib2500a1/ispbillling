<?php

namespace App\Support;

use App\Services\Olt\OltSnmpProbeService;

/**
 * PHP ext-snmp wrapper aligned with net-snmp client settings.
 *
 * @see https://github.com/net-snmp/net-snmp — snmp.conf, FAQ (value_output plain, numeric OIDs)
 */
final class SnmpClient
{
    private static bool $configured = false;

    public static function available(): bool
    {
        return OltSnmpProbeService::isSnmpExtensionAvailable();
    }

    /**
     * net-snmp recommended client settings for programmatic polling:
     * - SNMP_VALUE_PLAIN: raw octet strings (BDCOM MAC = 6 bytes)
     * - SNMP_OID_OUTPUT_NUMERIC: stable OID keys for suffix parsing
     */
    public static function configure(): void
    {
        if (self::$configured || ! self::available()) {
            return;
        }

        if (
            config('snmp.use_plain_values', true)
            && function_exists('snmp_set_valueretrieval')
            && defined('SNMP_VALUE_PLAIN')
        ) {
            snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        }

        if (function_exists('snmp_set_oid_output_format') && defined('SNMP_OID_OUTPUT_NUMERIC')) {
            snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
        }

        if (function_exists('snmp_set_quick_print')) {
            snmp_set_quick_print(true);
        }

        self::$configured = true;
    }

    public static function get(string $peer, string $community, string $oid, ?int $timeoutUs = null, ?int $retries = null): ?string
    {
        if (! self::available()) {
            return null;
        }

        self::configure();

        $timeoutUs ??= (int) config('snmp.timeout_us', 2000000);
        $retries ??= (int) config('snmp.retries', 1);

        /** @var string|false $result */
        $result = @snmp2_get($peer, $community, $oid, $timeoutUs, $retries);

        if ($result === false) {
            return null;
        }

        return self::cleanScalar((string) $result);
    }

    /**
     * @return array<string, string>
     */
    public static function walk(string $peer, string $community, string $oid, ?int $timeoutUs = null, ?int $retries = null): array
    {
        if (! self::available() || ! function_exists('snmp2_walk')) {
            return [];
        }

        self::configure();

        $timeoutUs ??= (int) config('snmp.timeout_us', 2000000);
        $retries ??= (int) config('snmp.retries', 1);

        /** @var array<string, string>|false $result */
        $result = @snmp2_walk($peer, $community, $oid, $timeoutUs, $retries);

        return is_array($result) ? $result : [];
    }

    /**
     * Walk preserving OID suffix keys (required for BDCOM EPON indexed tables).
     *
     * @return array<string, string>
     */
    public static function realWalk(string $peer, string $community, string $oid, ?int $timeoutUs = null, ?int $retries = null): array
    {
        if (! self::available() || ! function_exists('snmp2_real_walk')) {
            return self::walk($peer, $community, $oid, $timeoutUs, $retries);
        }

        self::configure();

        $timeoutUs ??= (int) config('snmp.timeout_us', 2000000);
        $retries ??= (int) config('snmp.retries', 1);

        /** @var array<string, string>|false $result */
        $result = @snmp2_real_walk($peer, $community, $oid, $timeoutUs, $retries);

        return is_array($result) ? $result : [];
    }

    /**
     * Walk a table whose OIDs are NOT monotonically increasing (e.g. BDCOM dot1qTpFdbPort, which
     * returns per-VLAN FDB rows out of order). The default GETNEXT walk aborts early on such tables
     * ("OID not increasing"), so this uses the OO SNMP session with the increasing check disabled —
     * the equivalent of `snmpbulkwalk -Cc`. Falls back to realWalk() when ext-snmp / the OO class
     * is unavailable.
     *
     * @return array<string, string>
     */
    public static function realWalkUnchecked(string $peer, string $community, string $oid, ?int $timeoutUs = null, ?int $retries = null): array
    {
        if (! self::available() || ! class_exists(\SNMP::class)) {
            return self::realWalk($peer, $community, $oid, $timeoutUs, $retries);
        }

        $timeoutUs ??= (int) config('snmp.timeout_us', 2000000);
        $retries ??= (int) config('snmp.retries', 1);

        try {
            $session = new \SNMP(\SNMP::VERSION_2c, $peer, $community, $timeoutUs, $retries);
            $session->oid_increasing_check = false;
            if (config('snmp.use_plain_values', true) && defined('SNMP_VALUE_PLAIN')) {
                $session->valueretrieval = SNMP_VALUE_PLAIN;
            }
            if (defined('SNMP_OID_OUTPUT_NUMERIC')) {
                $session->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
            }

            /** @var array<string, string>|false $result */
            $result = @$session->walk($oid);
            $session->close();

            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return self::realWalk($peer, $community, $oid, $timeoutUs, $retries);
        }
    }

    public static function suffixFromOidKey(string $oidKey, string $baseOid): ?string
    {
        $normalized = ltrim($oidKey, '.');
        $normalized = preg_replace('/^iso\.3\.6\.1/', '1.3.6.1', $normalized) ?? $normalized;
        $base = rtrim($baseOid, '.');

        if (str_starts_with($normalized, $base.'.')) {
            return substr($normalized, strlen($base) + 1) ?: null;
        }

        if (preg_match('/\.'.preg_quote($base, '/').'\.(.+)$/', '.'.$normalized, $m)) {
            return $m[1] !== '' ? $m[1] : null;
        }

        $baseTail = substr($base, strrpos($base, '.') !== false ? (int) strrpos($base, '.') + 1 : 0);
        if ($baseTail !== '' && preg_match('/\.'.preg_quote($baseTail, '/').'\.(.+)$/', $normalized, $m)) {
            return $m[1] !== '' ? $m[1] : null;
        }

        return null;
    }

    public static function parseTimeticks(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/\((\d+)\)/', $value, $m)) {
            return (int) $m[1];
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function cleanScalar(string $raw): string
    {
        $raw = trim($raw, " \t\n\r\0\x0B\"");
        $raw = preg_replace('/^[A-Za-z-]+:\s*/', '', $raw) ?? $raw;

        return trim($raw, "\" \t");
    }
}
