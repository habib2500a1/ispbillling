<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Device;

/**
 * Human-readable network labels configured by ISP staff (PON port name, MikroTik, VLAN).
 */
final class SubscriberNetworkLabels
{
    public static function ponPortLabel(Device $onu, ?Customer $customer = null): string
    {
        $onu->loadMissing(['oltPort', 'olt']);

        if ($customer === null && $onu->customer_id) {
            $customer = $onu->customer;
        }

        $customerMeta = is_array($customer?->meta) ? $customer->meta : [];
        $onuMeta = is_array($onu->meta) ? $onu->meta : [];

        if ($onu->oltPort !== null && filled($onu->oltPort->label)) {
            $label = trim((string) $onu->oltPort->label);
            if ($label !== '' && ! self::isTechnicalIndexOnly($label)) {
                return $label;
            }
        }

        $mkIface = self::mikrotikInterfaceName($customer);
        if ($mkIface !== null) {
            return $mkIface;
        }

        $epon = self::firstFilled(
            $customerMeta['epon_port'] ?? null,
            $onuMeta['epon_port'] ?? null,
        );
        if ($epon !== null) {
            return $epon;
        }

        if (filled($onu->display_name) && ! self::isTechnicalIndexOnly((string) $onu->display_name)) {
            return trim((string) $onu->display_name);
        }

        return self::technicalPonLabel($onu);
    }

    public static function customerEponPort(?Customer $customer): string
    {
        if ($customer === null) {
            return '—';
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $epon = trim((string) ($meta['epon_port'] ?? ''));

        return $epon !== '' ? $epon : '—';
    }

    public static function mikrotikName(?Customer $customer): string
    {
        if ($customer === null) {
            return '—';
        }

        $customer->loadMissing('mikrotikServer');
        $name = trim((string) ($customer->mikrotikServer?->name ?? ''));

        return $name !== '' ? $name : '—';
    }

    public static function vlan(?Customer $customer, ?Device $onu = null): string
    {
        if ($customer !== null) {
            $meta = is_array($customer->meta) ? $customer->meta : [];
            $vlan = trim((string) ($meta['vlan'] ?? ''));
            if ($vlan !== '') {
                return $vlan;
            }

            $fromIface = self::mikrotikInterfaceName($customer);
            if ($fromIface !== null) {
                $parsed = MikrotikVlanParser::fromInterfaceName($fromIface)
                    ?? MikrotikVlanParser::fromText($fromIface);
                if ($parsed !== null) {
                    return $parsed;
                }
            }

            $profile = trim((string) ($meta['mikrotik_profile'] ?? ''));
            if ($profile !== '') {
                $parsed = MikrotikVlanParser::fromText($profile);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        if ($onu !== null) {
            if (filled($onu->vlan_id)) {
                return (string) (int) $onu->vlan_id;
            }

            $fromOnu = self::vlanFromOnuMeta(is_array($onu->meta) ? $onu->meta : []);
            if ($fromOnu !== null) {
                return $fromOnu;
            }
        }

        return '—';
    }

    /**
     * VLAN learned on OLT FDB bridge (Device.meta.pon_mac_entries).
     *
     * @param  array<string, mixed>  $onuMeta
     */
    public static function vlanFromOnuMeta(array $onuMeta): ?string
    {
        $entries = $onuMeta['pon_mac_entries'] ?? null;
        if (! is_array($entries)) {
            return null;
        }

        $counts = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $vlan = MikrotikVlanParser::normalizeVlan($entry['vlan'] ?? null);
            if ($vlan === null) {
                continue;
            }
            $counts[$vlan] = ($counts[$vlan] ?? 0) + 1;
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * RouterOS VLAN / PPPoE uplink interface (e.g. KAJLA-BDCOM-OLT-P-3-BOROF GOLLI).
     */
    public static function mikrotikInterfaceName(?Customer $customer): ?string
    {
        if ($customer === null) {
            return null;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $iface = trim((string) ($meta['mikrotik_interface'] ?? ''));
        if ($iface === '' || self::isTechnicalIndexOnly($iface)) {
            return null;
        }

        return $iface;
    }

    /**
     * @return array{pon_port: string, mikrotik: string, vlan: string, olt_name: string}
     */
    public static function forCustomer(Customer $customer, ?Device $onu = null): array
    {
        if ($onu === null) {
            $onu = $customer->devices->firstWhere('type', 'onu')
                ?? $customer->onuDevice()->with(['olt', 'oltPort'])->first();
        }

        return [
            'pon_port' => $onu !== null ? self::ponPortLabel($onu, $customer) : self::customerEponPort($customer),
            'mikrotik' => self::mikrotikName($customer),
            'vlan' => self::vlan($customer, $onu),
            'olt_name' => trim((string) ($onu?->olt?->display_name ?? $onu?->olt?->serial_number ?? '')) ?: '—',
        ];
    }

    public static function technicalPonLabel(Device $onu): string
    {
        $parts = array_filter([
            $onu->card_no !== null ? 'C'.$onu->card_no : null,
            $onu->pon_no !== null ? 'P'.$onu->pon_no : null,
            $onu->onu_index !== null ? ':'.$onu->onu_index : null,
        ]);

        return $parts !== [] ? implode('', $parts) : '—';
    }

    public static function isTechnicalIndexOnly(string $label): bool
    {
        $label = trim($label);

        return $label === ''
            || preg_match('/^C?\d+\/P?\d+(:\d+)?$/i', $label) === 1
            || preg_match('/^GPON\d+\/\d+:\d+$/i', $label) === 1
            || preg_match('/^\d+\/\d+$/', $label) === 1;
    }

    private static function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
