<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Support\MacAddress;
use App\Support\OnuSignalLevel;
use App\Support\OpticalThresholds;
use Carbon\Carbon;

/**
 * Builds OLT-style optical power rows for subscriber admin view (legacy panel layout).
 */
final class SubscriberOpticalPowerPresenter
{
    /**
     * @return array{
     *   linked: bool,
     *   rows: list<array<string, mixed>>,
     *   hint: ?string,
     *   onu_billing: array<string, string>,
     *   isp_digital_synced_at: ?string,
     * }
     */
    public function forCustomer(Customer $customer): array
    {
        $customer->loadMissing(['activePppSession', 'devices']);
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $onuBilling = $this->onuBillingSummary($meta);

        $onus = $customer->devices
            ->where('type', 'onu')
            ->sortByDesc(fn (Device $d): float => (float) ($d->rx_power_dbm ?? -999))
            ->values();

        if ($onus->isEmpty()) {
            $onu = $customer->onuDevice()->with(['olt', 'oltPort'])->first();
            if ($onu !== null) {
                $onus = collect([$onu]);
            }
        } else {
            $onus = $onus->map(fn (Device $d) => $d->loadMissing(['olt', 'oltPort']));
        }

        if ($onus->isEmpty()) {
            $suggestions = CustomerOnuMatcher::suggestOnusForCustomer($customer, 6);
            $login = $customer->pppLoginName();

            return [
                'linked' => false,
                'rows' => [],
                'ppp_login' => $login,
                'hint' => $this->unlinkHint($customer, $login, $suggestions),
                'onu_billing' => $onuBilling,
                'isp_digital_synced_at' => $meta['isp_digital_details_synced_at'] ?? null,
                'suggestions' => array_map(fn (array $s): array => [
                    'id' => $s['onu']->id,
                    'label' => trim(sprintf(
                        '%s · %s · RX %s',
                        $s['onu']->display_name ?: 'ONU',
                        $s['onu']->mac_address ?: $s['onu']->serial_number,
                        $s['onu']->rx_power_dbm !== null ? number_format((float) $s['onu']->rx_power_dbm, 2).' dBm' : '—',
                    )),
                    'reason' => $s['reason'],
                ], $suggestions),
            ];
        }

        $rows = $onus->values()->map(
            fn (Device $onu, int $index): array => $this->row($customer, $onu, $index + 1),
        )->all();

        return [
            'linked' => true,
            'rows' => $rows,
            'ppp_login' => $customer->pppLoginName(),
            'hint' => null,
            'onu_billing' => $onuBilling,
            'isp_digital_synced_at' => $meta['isp_digital_details_synced_at'] ?? null,
            'suggestions' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function onuBillingSummary(array $meta): array
    {
        $fmt = static fn ($v): string => isset($v) && (float) $v > 0
            ? number_format((float) $v, 2).' BDT/mo'
            : '—';

        return [
            'ONU rent' => $fmt($meta['onu_rent'] ?? null),
            'ONU deposit' => $fmt($meta['onu_deposit'] ?? null),
            'Router rent' => $fmt($meta['router_rent'] ?? null),
            'Device' => (string) ($meta['device'] ?? '—'),
            'ONU MAC (meta)' => (string) ($meta['onu_mac'] ?? '—'),
        ];
    }

    /**
     * @param  list<array{onu: Device, reason: string, score: int}>  $suggestions
     */
    private function unlinkHint(Customer $customer, string $login, array $suggestions): string
    {
        $oltCount = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('type', 'onu')
            ->count();

        if ($oltCount === 0) {
            return 'OLT inventory খালি — আগে OLT যোগ করুন ও BDCOM sync চালান।';
        }

        if ($suggestions !== []) {
            return 'নিচের suggested ONU থেকে বেছে নিন, অথবা header-এ «ONU সংযুক্ত করুন»। Router MAC ≠ ONU MAC — OLT-এ ONU description = «'.$login.'» রাখলে auto-link হবে।';
        }

        return 'OLT inventory-তে «'.$login.'» মিলে এমন ONU নেই। OLT-এ ONU description = PPP login সেট করুন → «Sync OLT & link ONU» চাপুন, অথবা «ONU সংযুক্ত করুন» দিয়ে EPON port বেছে নিন।';
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Customer $customer, Device $onu, int $index): array
    {
        $meta = is_array($onu->meta) ? $onu->meta : [];
        $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
        $oper = strtolower((string) ($onu->onu_oper_status ?? ''));
        $rxLevel = OnuSignalLevel::classifyRx($rx, $oper);

        $ppp = $customer->activePppSession;
        $router = $customer->devices->firstWhere('type', 'router')
            ?? $customer->devices->firstWhere('framed_ip_address', '!=', null);

        $customerMeta = is_array($customer->meta) ? $customer->meta : [];

        $clientMac = $this->firstFilled(
            $ppp?->caller_id,
            $customerMeta['mac_binding'] ?? null,
            $router?->mac_address,
        );

        $ip = $this->firstFilled(
            $ppp?->framed_ip,
            $onu->framed_ip_address,
            $router?->framed_ip_address,
            $customerMeta['static_ip'] ?? null,
        );

        $lastSync = $onu->last_polled_at
            ?? ($meta['last_bdcom_sync'] ?? null
                ? Carbon::parse((string) $meta['last_bdcom_sync'])
                : null);

        $distance = $meta['distance_m'] ?? $meta['bdcom_distance'] ?? null;
        if ($distance === null && $onu->olt_port_id) {
            $distance = $onu->oltPort?->fiber_distance_m;
        }

        return [
            'index' => $index,
            'client_code' => $customer->customer_code ?: (string) $customer->id,
            'username' => $customer->mikrotik_secret_name ?: $customer->radius_username ?: '—',
            'client_name' => $customer->name,
            'mac_address' => $clientMac ? (MacAddress::normalizeColon($clientMac) ?? $clientMac) : '—',
            'ip_address' => $ip ?: '—',
            'olt_name' => $onu->olt?->display_name ?? $onu->olt?->serial_number ?? '—',
            'optical_power' => $rx !== null ? number_format($rx, 4, '.', '') : '—',
            'optical_power_raw' => $rx,
            'optical_level' => $rxLevel,
            'optical_level_label' => OnuSignalLevel::labels()[$rxLevel] ?? $rxLevel,
            'optical_color' => OnuSignalLevel::filamentColor($rxLevel),
            'tx_power' => $onu->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 4, '.', '') : '—',
            'onu_mac' => $onu->mac_address
                ? (MacAddress::normalizeColon($onu->mac_address) ?? $onu->mac_address)
                : '—',
            'olt_port' => $this->oltPortLabel($onu),
            'onu_status' => $this->formatStatus((string) ($onu->onu_oper_status ?? 'unknown')),
            'description' => $this->firstFilled(
                $meta['bdcom_description'] ?? null,
                $meta['ppp_login'] ?? null,
                $onu->onu_external_id,
                $onu->notes,
            ) ?: '—',
            'last_deregister_time' => $this->formatDeregisterTime($meta, $oper),
            'distance' => $distance !== null && $distance !== '' ? (string) $distance : '—',
            'deregister_reason' => $this->formatDeregisterReason($onu, $meta, $oper),
            'last_synced_time' => $lastSync?->format('n/j/Y, g:i:s A') ?? '—',
            'is_high_laser' => OpticalThresholds::isHighRx($rx) || OpticalThresholds::isHighTx(
                $onu->tx_power_dbm !== null ? (float) $onu->tx_power_dbm : null,
            ),
        ];
    }

    private function oltPortLabel(Device $onu): string
    {
        if (filled($onu->display_name)) {
            return (string) $onu->display_name;
        }

        $parts = array_filter([
            $onu->card_no !== null ? 'C'.$onu->card_no : null,
            $onu->pon_no !== null ? 'P'.$onu->pon_no : null,
            $onu->onu_index !== null ? ':'.$onu->onu_index : null,
        ]);

        return $parts !== [] ? implode('', $parts) : '—';
    }

    private function formatStatus(string $status): string
    {
        return match (strtolower($status)) {
            'online', 'active', 'up' => 'Online',
            'offline', 'down' => 'Offline',
            'los' => 'LOS',
            default => ucfirst($status),
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function formatDeregisterTime(array $meta, string $oper): string
    {
        if (in_array($oper, ['online', 'active', 'up'], true)) {
            return 'Not Down Before';
        }

        $raw = $meta['last_deregister_at'] ?? $meta['bdcom_last_down'] ?? null;
        if ($raw) {
            try {
                return Carbon::parse((string) $raw)->format('n/j/Y, g:i:s A');
            } catch (\Throwable) {
                return (string) $raw;
            }
        }

        return '—';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function formatDeregisterReason(Device $onu, array $meta, string $oper): string
    {
        if (in_array($oper, ['online', 'active', 'up'], true)) {
            return '—';
        }

        return $this->firstFilled(
            $onu->offline_reason,
            $meta['deregister_reason'] ?? null,
            $meta['bdcom_deregister_reason'] ?? null,
            $oper === 'los' ? 'LOS' : null,
            $oper === 'offline' ? 'Power Off' : null,
        ) ?: '—';
    }

    private function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
