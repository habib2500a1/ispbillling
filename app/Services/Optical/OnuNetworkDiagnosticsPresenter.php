<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Network\CustomerConnectionStatusService;
use App\Support\MacAddress;
use App\Support\OnuEnvironmentalMetrics;
use App\Support\OnuSignalLevel;
use App\Support\OpticalThresholds;
use App\Support\SubscriberNetworkLabels;
use Illuminate\Support\Str;

/**
 * Router + ONU diagnostic cards (legacy portal / VSOL NMS style) for subscriber network tab.
 */
final class OnuNetworkDiagnosticsPresenter
{
    public function __construct(
        private readonly CustomerConnectionStatusService $connection,
    ) {}

    /**
     * @return array{router: array<string, mixed>, onu: array<string, mixed>}|null
     */
    public function forCustomer(Customer $customer): ?array
    {
        $customer->loadMissing(['devices', 'activePppSession', 'mikrotikServer']);

        $onu = $customer->devices->firstWhere('type', 'onu')
            ?? $customer->onuDevice()->with(['olt', 'oltPort'])->first();

        if ($onu === null) {
            return null;
        }

        $router = $customer->devices->firstWhere('type', 'router');
        $conn = $this->connection->summary($customer);
        $meta = is_array($onu->meta) ? $onu->meta : [];
        $env = OnuEnvironmentalMetrics::fromDevice($onu);
        $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
        $tx = $onu->tx_power_dbm !== null ? (float) $onu->tx_power_dbm : null;
        $oper = strtolower((string) ($onu->onu_oper_status ?? 'unknown'));
        $rxLevel = OnuSignalLevel::classifyRx($rx, $oper);
        $driver = strtolower((string) ($onu->olt?->olt_driver ?? $onu->gpon_profile ?? ''));
        $network = SubscriberNetworkLabels::forCustomer($customer, $onu);

        return [
            'network' => $network,
            'router' => [
                'name' => $router?->display_name
                    ?? $customer->mikrotikServer?->name
                    ?? 'Router',
                'ip' => $this->firstFilled(
                    $conn['framed_ip'] ?? null,
                    $router?->framed_ip_address,
                    $customer->activePppSession?->framed_ip,
                ) ?: '—',
                'mac' => $this->formatMac($this->firstFilled(
                    $customer->activePppSession?->caller_id,
                    $router?->mac_address,
                    is_array($customer->meta) ? ($customer->meta['mac_binding'] ?? null) : null,
                )),
                'uptime' => $conn['connection_duration'] ?? '—',
                'mac_unlock' => (bool) (is_array($customer->meta) ? ($customer->meta['mac_unlock'] ?? false) : false),
            ],
            'onu' => [
                'vendor_label' => $this->vendorLabel($driver, $onu),
                'status' => $this->formatOperStatus($oper),
                'status_online' => in_array($oper, ['online', 'active', 'up'], true),
                'rx_dbm' => $rx,
                'rx_display' => $rx !== null ? number_format($rx, 4).' dBm' : '—',
                'rx_tone' => OnuSignalLevel::filamentColor($rxLevel),
                'tx_display' => $tx !== null ? number_format($tx, 4).' dBm' : '—',
                'temperature' => OnuEnvironmentalMetrics::formatTemperature($env['temperature_c']),
                'temperature_c' => $env['temperature_c'],
                'temperature_tone' => OnuEnvironmentalMetrics::temperatureTone($env['temperature_c']),
                'voltage' => OnuEnvironmentalMetrics::formatVoltage($env['voltage_v']),
                'voltage_v' => $env['voltage_v'],
                'port' => $network['pon_port'],
                'pon_port_name' => $network['pon_port'],
                'mikrotik_name' => $network['mikrotik'],
                'vlan' => $network['vlan'],
                'distance' => $this->formatDistanceShort(
                    $meta['distance_m'] ?? $meta['bdcom_distance'] ?? $onu->oltPort?->fiber_distance_m,
                ),
                'serial' => $onu->serial_number ?: '—',
                'olt_name' => $network['olt_name'],
                'high_laser' => OpticalThresholds::isHighRx($rx) || OpticalThresholds::isHighTx($tx),
            ],
        ];
    }

    private function vendorLabel(string $driver, Device $onu): string
    {
        if ($driver !== '') {
            $label = config("olt_drivers.drivers.{$driver}.label");
            if (is_string($label) && $label !== '') {
                return Str::upper(Str::before($label, ' '));
            }
        }

        $vendor = strtolower((string) ($onu->vendor ?? $onu->olt?->vendor ?? ''));

        return $vendor !== '' ? Str::upper($vendor) : 'GPON';
    }

    private function formatOperStatus(string $oper): string
    {
        return match ($oper) {
            'online', 'active', 'up' => 'ONLINE',
            'offline', 'down' => 'OFFLINE',
            'los' => 'LOS',
            default => Str::upper($oper),
        };
    }

    private function formatMac(mixed $mac): string
    {
        if ($mac === null || $mac === '') {
            return '—';
        }

        return MacAddress::normalizeColon((string) $mac) ?? (string) $mac;
    }

    private function formatDistanceShort(mixed $distance): string
    {
        if ($distance === null || $distance === '' || ! is_numeric($distance)) {
            return '—';
        }

        $m = (int) round((float) $distance);

        if ($m >= 1000) {
            return round($m / 1000, 1).'km';
        }

        return $m.'m';
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
