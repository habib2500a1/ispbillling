<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Models\OltPort;
use App\Models\PonSignalStat;
use App\Services\Optical\CustomerOnuMatcher;
use App\Support\MikrotikVlanParser;
use App\Support\SubscriberNetworkLabels;
use Illuminate\Support\Collection;

/**
 * PON port row labels for NOC tables (staff port name, technical C/P, MikroTik, VLAN).
 */
final class PonPortNetworkSummary
{
    /**
     * @return array{
     *   olt_name: string,
     *   port_index: string,
     *   port_name: string,
     *   port_display: string,
     *   mikrotik: string,
     *   mikrotik_detail: string,
     *   vlan: string,
     *   vlan_detail: string,
     *   onu_online: int,
     *   onu_total: int,
     *   avg_rx_dbm: ?string,
     *   onu_critical: int,
     *   onu_warning: int,
     *   fault_percent: ?string,
     *   line_status: string,
     *   line_status_label: string,
     * }
     */
    public static function toRow(PonSignalStat $stat): array
    {
        $stat->loadMissing(['olt:id,display_name,serial_number', 'oltPort:id,device_id,card_index,pon_index,label']);

        $card = (int) ($stat->card_no ?? 0);
        $pon = (int) ($stat->pon_no ?? 0);
        $portIndex = sprintf('C%d/P%d', $card, $pon);

        $onus = self::onusOnPort($stat);
        $portName = self::resolvePortName($stat, $portIndex, $onus);
        $network = self::summarizeSubscriberNetwork($onus, (int) ($stat->pon_no ?? 0), $stat->olt);
        $line = self::lineStatus((int) $stat->onu_total, (int) $stat->onu_online);

        return [
            'olt_name' => $stat->olt?->display_name ?? $stat->olt?->serial_number ?? 'OLT',
            'port_index' => $portIndex,
            'port_name' => $portName,
            'port_display' => $portName !== $portIndex ? "{$portName} · {$portIndex}" : $portIndex,
            'mikrotik' => $network['mikrotik'],
            'mikrotik_detail' => $network['mikrotik_detail'],
            'vlan' => $network['vlan'],
            'vlan_detail' => $network['vlan_detail'],
            'onu_online' => (int) $stat->onu_online,
            'onu_total' => (int) $stat->onu_total,
            'avg_rx_dbm' => $stat->avg_rx_dbm !== null ? number_format((float) $stat->avg_rx_dbm, 2).' dBm' : '—',
            'onu_critical' => (int) ($stat->onu_critical ?? 0),
            'onu_warning' => (int) ($stat->onu_warning ?? 0),
            'fault_percent' => $stat->fault_percent !== null ? number_format((float) $stat->fault_percent, 1).'%' : null,
            'line_status' => $line['status'],
            'line_status_label' => $line['label'],
        ];
    }

    /**
     * @return array{status: string, label: string}
     */
    private static function lineStatus(int $total, int $online): array
    {
        if ($total === 0) {
            return ['status' => 'empty', 'label' => 'No ONUs'];
        }

        if ($online === 0) {
            return ['status' => 'offline', 'label' => 'Offline'];
        }

        if ($online < $total) {
            return ['status' => 'partial', 'label' => 'Partial'];
        }

        return ['status' => 'online', 'label' => 'Online'];
    }

    private static function resolvePortName(PonSignalStat $stat, string $portIndex, Collection $onus): string
    {
        $port = $stat->oltPort;
        if ($port === null && $stat->olt_id) {
            $port = OltPort::query()
                ->where('device_id', $stat->olt_id)
                ->where('card_index', (int) ($stat->card_no ?? 0))
                ->where('pon_index', (int) ($stat->pon_no ?? 0))
                ->first(['id', 'label', 'card_index', 'pon_index', 'meta']);
        }

        if ($port !== null && self::portMatchesStat($port, $stat) && filled($port->label)) {
            $label = trim((string) $port->label);
            if ($label !== '' && ! SubscriberNetworkLabels::isTechnicalIndexOnly($label)) {
                return $label;
            }
        }

        $portMeta = is_array($port?->meta) ? $port->meta : [];
        $fromPortMeta = trim((string) ($portMeta['mikrotik_interface'] ?? ''));
        if ($fromPortMeta !== ''
            && ! SubscriberNetworkLabels::isTechnicalIndexOnly($fromPortMeta)
            && self::interfaceMatchesPonIndex($fromPortMeta, (int) ($stat->pon_no ?? 0))) {
            return $fromPortMeta;
        }

        $fromMk = self::bestMikrotikInterfaceName($onus, (int) ($stat->pon_no ?? 0));
        if ($fromMk !== null) {
            return $fromMk;
        }

        $fromOnu = self::topOnuDisplayLabel($onus);
        if ($fromOnu !== null) {
            return $fromOnu;
        }

        $oltLabel = trim((string) ($stat->olt?->display_name ?? ''));
        if ($oltLabel !== '' && (int) ($stat->pon_no ?? 0) > 0) {
            return $oltLabel.' P'.(int) $stat->pon_no;
        }

        return $portIndex;
    }

    /**
     * @param  Collection<int, Device>  $onus
     */
    /**
     * Same resolver as ONU database grid (customer_id or MAC/login/OLT description match).
     */
    private static function resolveCustomer(Device $onu): ?Customer
    {
        $onu->loadMissing(['customer:id,meta,mikrotik_server_id', 'customer.mikrotikServer:id,name']);

        if ($onu->customer !== null) {
            return $onu->customer;
        }

        return CustomerOnuMatcher::matchCustomerForOnuDevice((int) $onu->tenant_id, $onu);
    }

    private static function topOnuDisplayLabel(Collection $onus): ?string
    {
        $counts = [];
        foreach ($onus as $onu) {
            $label = trim((string) ($onu->display_name ?? ''));
            if ($label === '' || SubscriberNetworkLabels::isTechnicalIndexOnly($label)) {
                continue;
            }
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return array_key_first($counts);
    }

    /**
     * Prefer RouterOS interface whose -P-N- segment matches this PON index.
     */
    private static function bestMikrotikInterfaceName(Collection $onus, int $ponIndex): ?string
    {
        $counts = [];
        $matched = [];

        foreach ($onus as $onu) {
            $customer = self::resolveCustomer($onu);
            $iface = SubscriberNetworkLabels::mikrotikInterfaceName($customer);
            if ($iface === null) {
                continue;
            }
            $counts[$iface] = ($counts[$iface] ?? 0) + 1;
            if ($ponIndex > 0 && self::interfaceMatchesPonIndex($iface, $ponIndex)) {
                $matched[$iface] = ($matched[$iface] ?? 0) + 1;
            }
        }

        if ($matched !== []) {
            arsort($matched);

            return array_key_first($matched);
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return array_key_first($counts);
    }

    private static function interfaceMatchesPonIndex(string $interface, int $ponIndex): bool
    {
        if ($ponIndex <= 0) {
            return true;
        }

        return MikrotikVlanParser::ponIndexFromOltInterfaceLabel($interface) === $ponIndex;
    }

    private static function portMatchesStat(OltPort $port, PonSignalStat $stat): bool
    {
        return (int) $port->card_index === (int) ($stat->card_no ?? 0)
            && (int) $port->pon_index === (int) ($stat->pon_no ?? 0);
    }

    /**
     * @return Collection<int, Device>
     */
    private static function onusOnPort(PonSignalStat $stat): Collection
    {
        $query = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $stat->tenant_id)
            ->where('olt_id', $stat->olt_id)
            ->where('type', 'onu');

        if ($stat->olt_port_id) {
            $query->where('olt_port_id', $stat->olt_port_id);
        } else {
            $query->where('card_no', (int) ($stat->card_no ?? 0))
                ->where('pon_no', (int) ($stat->pon_no ?? 0));
        }

        return $query
            ->with(['customer:id,meta,mikrotik_server_id', 'customer.mikrotikServer:id,name'])
            ->get(['id', 'customer_id', 'olt_port_id', 'card_no', 'pon_no', 'display_name']);
    }

    /**
     * @param  Collection<int, Device>  $onus
     * @return array{mikrotik: string, mikrotik_detail: string, vlan: string, vlan_detail: string}
     */
    private static function summarizeSubscriberNetwork(Collection $onus, int $ponIndex = 0, ?Device $olt = null): array
    {
        $resolved = $onus->map(fn (Device $o): array => ['onu' => $o, 'customer' => self::resolveCustomer($o)])
            ->filter(fn (array $row): bool => $row['customer'] !== null);

        if ($resolved->isEmpty()) {
            $oltName = trim((string) ($olt?->display_name ?? 'OLT'));

            return [
                'mikrotik' => '—',
                'mikrotik_detail' => "{$oltName}: কোনো subscriber মিল পাওয়া যায়নি (MAC/PPPoE login)",
                'vlan' => '—',
                'vlan_detail' => 'ONU database-এ যেমন দেখায়, PON টেবিলেও মিলতে হলে MAC বা login মিল দরকার',
            ];
        }

        $mikrotikCounts = [];
        $vlanCounts = [];
        $vlanCountsOnPon = [];

        foreach ($resolved as $row) {
            $onu = $row['onu'];
            $customer = $row['customer'];
            if (! $customer instanceof Customer) {
                continue;
            }
            $mk = SubscriberNetworkLabels::mikrotikName($customer);
            if ($mk !== '—') {
                $mikrotikCounts[$mk] = ($mikrotikCounts[$mk] ?? 0) + 1;
            }
            $vlan = SubscriberNetworkLabels::vlan($customer);
            if ($vlan !== '—') {
                $vlanCounts[$vlan] = ($vlanCounts[$vlan] ?? 0) + 1;
                $iface = SubscriberNetworkLabels::mikrotikInterfaceName($customer);
                if ($ponIndex <= 0 || ($iface !== null && self::interfaceMatchesPonIndex($iface, $ponIndex))) {
                    $vlanCountsOnPon[$vlan] = ($vlanCountsOnPon[$vlan] ?? 0) + 1;
                }
            }
        }

        $vlanForCell = $vlanCountsOnPon !== [] ? $vlanCountsOnPon : $vlanCounts;

        return [
            'mikrotik' => self::formatPrimaryLabel($mikrotikCounts),
            'mikrotik_detail' => self::detailLine($mikrotikCounts, 'subscriber(s)'),
            'vlan' => self::formatPrimaryLabel($vlanForCell),
            'vlan_detail' => self::detailLine($vlanCounts, 'subscriber(s) with VLAN'),
        ];
    }

    /**
     * One value in the table cell; extras only as "(+N)" — avoids "517, 520" wrapping.
     *
     * @param  array<string, int>  $counts
     */
    private static function formatPrimaryLabel(array $counts): string
    {
        if ($counts === []) {
            return '—';
        }

        arsort($counts);
        $names = array_keys($counts);
        $primary = $names[0];
        $extra = count($names) - 1;

        if ($extra === 0) {
            return $primary;
        }

        return $primary.' (+'.$extra.')';
    }

    /**
     * @param  array<string, int>  $counts
     */
    private static function detailLine(array $counts, string $suffix): string
    {
        if ($counts === []) {
            return '—';
        }

        arsort($counts);
        $parts = [];
        foreach (array_slice($counts, 0, 4, true) as $name => $count) {
            $parts[] = "{$name} ({$count})";
        }
        $rest = count($counts) - count($parts);
        $line = implode(' · ', $parts);
        if ($rest > 0) {
            $line .= " · +{$rest} more";
        }

        return $line.' '.$suffix;
    }

}
