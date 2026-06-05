<?php

namespace App\Support;

use App\Models\Device;

final class OltManagementHelper
{
    public const META_WEB_URL = 'olt_web_url';

    public const META_WEB_USERNAME = 'olt_web_username';

    public const META_WEB_PASSWORD = 'olt_web_password';

    /** Written by Aveis SNMP sync — must not appear in KeyValue "Extra metadata". */
    public const META_AVEIS_SNMP_COLUMN_MAP = 'aveis_snmp_column_map';

    public const META_PPTP_ENABLED = 'olt_pptp_enabled';

    public const META_PPTP_SERVER = 'olt_pptp_server';

    public const META_PPTP_USERNAME = 'olt_pptp_username';

    public const META_PPTP_PASSWORD = 'olt_pptp_password';

    public const META_PPTP_SUBNET = 'olt_pptp_subnet';

    public const META_VPN_TYPE = 'olt_vpn_type';

    public const VPN_NONE = 'none';

    public const VPN_PPTP = 'pptp';

    public const VPN_OPENVPN = 'openvpn';

    /**
     * @return list<string>
     */
    public static function metaKeysHiddenFromExtraForm(): array
    {
        return [
            self::META_WEB_URL,
            self::META_WEB_USERNAME,
            self::META_WEB_PASSWORD,
            self::META_VPN_TYPE,
            self::META_PPTP_ENABLED,
            self::META_PPTP_SERVER,
            self::META_PPTP_USERNAME,
            self::META_PPTP_PASSWORD,
            self::META_PPTP_SUBNET,
            'snmp_onu_oids',
            self::META_AVEIS_SNMP_COLUMN_MAP,
        ];
    }

    public static function vpnType(Device $olt): string
    {
        $meta = is_array($olt->meta) ? $olt->meta : [];
        $type = strtolower(trim((string) ($meta[self::META_VPN_TYPE] ?? '')));
        if (in_array($type, [self::VPN_PPTP, self::VPN_OPENVPN], true)) {
            return $type;
        }

        if ((bool) ($meta[self::META_PPTP_ENABLED] ?? false)) {
            return self::VPN_PPTP;
        }

        return self::VPN_NONE;
    }

    public static function vpnEnabled(Device $olt): bool
    {
        return self::vpnType($olt) !== self::VPN_NONE;
    }

    /** True when Test VPN can try direct + any configured OpenVPN/PPTP material. */
    public static function hasVpnCompareData(Device $olt): bool
    {
        if (self::vpnEnabled($olt)) {
            return true;
        }

        return self::openVpnConfigFromFile($olt) !== null
            || self::pptpConfigFromMeta($olt) !== null;
    }

    public static function pptpEnabled(Device $olt): bool
    {
        return self::vpnType($olt) === self::VPN_PPTP;
    }

    public static function openVpnEnabled(Device $olt): bool
    {
        return self::vpnType($olt) === self::VPN_OPENVPN;
    }

    public static function openVpnConfigPath(Device $olt): ?string
    {
        $path = storage_path('app/private/olt-vpn/'.$olt->id.'.ovpn');

        return is_file($path) ? $path : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function clearVpnFromMeta(array $meta): array
    {
        unset(
            $meta[self::META_VPN_TYPE],
            $meta[self::META_PPTP_ENABLED],
            $meta[self::META_PPTP_SERVER],
            $meta[self::META_PPTP_USERNAME],
            $meta[self::META_PPTP_PASSWORD],
            $meta[self::META_PPTP_SUBNET],
        );

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function mergeVpnIntoMeta(
        array $meta,
        ?string $vpnType,
        ?bool $pptpLegacyEnabled = null,
        ?string $pptpServer = null,
        ?string $pptpUsername = null,
        ?string $pptpPassword = null,
        ?string $pptpSubnet = null,
    ): array {
        if ($vpnType !== null) {
            $vpnType = strtolower(trim($vpnType));
            if ($vpnType === self::VPN_NONE || $vpnType === '') {
                return self::clearVpnFromMeta($meta);
            }
            if ($vpnType === self::VPN_OPENVPN) {
                $meta[self::META_VPN_TYPE] = self::VPN_OPENVPN;

                return self::mergePptpIntoMeta(
                    $meta,
                    false,
                    $pptpServer,
                    $pptpUsername,
                    $pptpPassword,
                    $pptpSubnet,
                );
            }

            if ($vpnType === self::VPN_PPTP) {
                $meta[self::META_VPN_TYPE] = self::VPN_PPTP;

                return self::mergePptpIntoMeta(
                    $meta,
                    true,
                    $pptpServer,
                    $pptpUsername,
                    $pptpPassword,
                    $pptpSubnet,
                );
            }
        } elseif ($pptpLegacyEnabled === false) {
            return self::clearVpnFromMeta($meta);
        }

        return self::mergePptpIntoMeta(
            $meta,
            $pptpLegacyEnabled,
            $pptpServer,
            $pptpUsername,
            $pptpPassword,
            $pptpSubnet,
        );
    }

    /**
     * OpenVPN when .ovpn file exists (ignores active VPN type — for auto-compare).
     *
     * @return array{subnet: string, olt_ip: string, config_path: string}|null
     */
    public static function openVpnConfigFromFile(Device $olt): ?array
    {
        $path = self::openVpnConfigPath($olt);
        if ($path === null) {
            return null;
        }

        $oltIp = trim((string) ($olt->management_ip ?? ''));
        $meta = is_array($olt->meta) ? $olt->meta : [];
        $subnet = trim((string) ($meta[self::META_PPTP_SUBNET] ?? ''));
        if ($subnet === '' && $oltIp !== '') {
            $subnet = self::defaultPptpSubnet($oltIp) ?? '';
        }

        if ($subnet === '') {
            return null;
        }

        return [
            'subnet' => $subnet,
            'olt_ip' => $oltIp,
            'config_path' => $path,
        ];
    }

    /**
     * @return array{subnet: string, olt_ip: string, config_path: string}|null
     */
    public static function openVpnConfig(Device $olt): ?array
    {
        if (self::vpnType($olt) !== self::VPN_OPENVPN) {
            return null;
        }

        return self::openVpnConfigFromFile($olt);
    }

    /**
     * PPTP creds from meta (ignores active VPN type — for auto-compare).
     *
     * @return array{server: string, username: string, password: string, subnet: string, olt_ip: string}|null
     */
    public static function pptpConfigFromMeta(Device $olt): ?array
    {
        $meta = is_array($olt->meta) ? $olt->meta : [];
        $server = trim((string) ($meta[self::META_PPTP_SERVER] ?? ''));
        $username = trim((string) ($meta[self::META_PPTP_USERNAME] ?? ''));
        $password = self::pptpPasswordFromMeta($meta);
        $oltIp = trim((string) ($olt->management_ip ?? ''));
        $subnet = trim((string) ($meta[self::META_PPTP_SUBNET] ?? ''));
        if ($subnet === '' && $oltIp !== '') {
            $subnet = self::defaultPptpSubnet($oltIp) ?? '';
        }

        if ($server === '' || $username === '' || $password === null || $password === '' || $subnet === '') {
            return null;
        }

        return [
            'server' => $server,
            'username' => $username,
            'password' => $password,
            'subnet' => $subnet,
            'olt_ip' => $oltIp,
        ];
    }

    /**
     * @return array{server: string, username: string, password: string, subnet: string, olt_ip: string}|null
     */
    public static function pptpConfig(Device $olt): ?array
    {
        if (self::vpnType($olt) !== self::VPN_PPTP) {
            return null;
        }

        return self::pptpConfigFromMeta($olt);
    }

    public static function defaultPptpSubnet(string $managementIp): ?string
    {
        if (! filter_var($managementIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $parts = explode('.', $managementIp);
        if (count($parts) !== 4) {
            return null;
        }

        return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function pptpPasswordFromMeta(array $meta): ?string
    {
        $enc = $meta[self::META_PPTP_PASSWORD] ?? null;
        if (! is_string($enc) || $enc === '') {
            return null;
        }

        try {
            return decrypt($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function mergePptpIntoMeta(
        array $meta,
        ?bool $enabled,
        ?string $server,
        ?string $username,
        ?string $password,
        ?string $subnet,
    ): array {
        if ($enabled !== null) {
            $meta[self::META_PPTP_ENABLED] = $enabled;
        }

        if ($server !== null && trim($server) !== '') {
            $meta[self::META_PPTP_SERVER] = trim($server);
        }

        if ($username !== null && trim($username) !== '') {
            $meta[self::META_PPTP_USERNAME] = trim($username);
        }

        if ($password !== null && $password !== '') {
            $meta[self::META_PPTP_PASSWORD] = encrypt($password);
        }

        if ($subnet !== null && trim($subnet) !== '') {
            $meta[self::META_PPTP_SUBNET] = trim($subnet);
        }

        return $meta;
    }

    /**
     * Strip scheme/path from pasted URLs (e.g. http://103.29.127.94:8506).
     */
    public static function normalizeManagementIp(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^https?://([^/]+)#i', $value, $m)) {
            $value = $m[1];
        }

        if (str_contains($value, ':') && ! filter_var($value, FILTER_VALIDATE_IP)) {
            $value = explode(':', $value, 2)[0];
        }

        return $value;
    }

    public static function defaultAveisWebUrl(string $managementIp): string
    {
        $port = (int) config('olt_drivers.aveis_web_port', 8506);

        return $managementIp.':'.$port;
    }

    /**
     * Store host:port only (e.g. http://103.29.127.94:8506/ → 103.29.127.94:8506).
     */
    public static function normalizeWebUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('~^https?://([^/?#]+)~i', $value, $m)) {
            $value = $m[1];
        } else {
            $value = rtrim($value, '/');
        }

        return $value;
    }

    public static function isAveisDriver(?string $oltDriver): bool
    {
        $driver = strtolower((string) $oltDriver);

        return str_starts_with($driver, 'aveis_');
    }

    public static function isConfigDrivenDriver(?string $oltDriver): bool
    {
        if ($oltDriver === null || $oltDriver === '') {
            return false;
        }

        return in_array(strtolower($oltDriver), GponSnmpProfile::configDrivenDrivers(), true);
    }

    public static function webUiUrl(Device $olt): ?string
    {
        $meta = is_array($olt->meta) ? $olt->meta : [];
        $raw = trim((string) ($meta[self::META_WEB_URL] ?? ''));

        if ($raw === '' && filled($olt->management_ip) && self::isAveisDriver($olt->olt_driver)) {
            $raw = self::defaultAveisWebUrl((string) $olt->management_ip);
        }

        if ($raw === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        $scheme = 'http';
        if (preg_match('/:(443|8443)$/', $raw)) {
            $scheme = 'https';
        }

        return $scheme.'://'.$raw;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function mergeWebCredentialsIntoMeta(array $meta, ?string $url, ?string $username, ?string $password): array
    {
        $normalized = self::normalizeWebUrl($url);
        if ($normalized !== null) {
            $meta[self::META_WEB_URL] = $normalized;
        }

        if ($username !== null && trim($username) !== '') {
            $meta[self::META_WEB_USERNAME] = trim($username);
        }

        if ($password !== null && $password !== '') {
            $meta[self::META_WEB_PASSWORD] = encrypt($password);
        }

        return $meta;
    }

    public static function webPasswordFromMeta(array $meta): ?string
    {
        $enc = $meta[self::META_WEB_PASSWORD] ?? null;
        if (! is_string($enc) || $enc === '') {
            return null;
        }

        try {
            return decrypt($enc);
        } catch (\Throwable) {
            return null;
        }
    }
}
