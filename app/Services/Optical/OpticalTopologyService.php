<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Support\OnuSignalLevel;

/**
 * GPON topology tree for Optical NOC (OLT → PON → ONU) with signal health highlighting.
 */
final class OpticalTopologyService
{
    private const int ONU_PREVIEW_LIMIT = 16;

    /**
     * @return array{summary: array<string, int>, olts: list<array<string, mixed>>}
     */
    public function buildForTenant(int $tenantId): array
    {
        $onlineStatuses = ['online', 'active', 'up'];
        $olts = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->olts()
            ->where('status', '!=', 'decommissioned')
            ->with([
                'ports' => fn ($q) => $q->orderBy('card_index')->orderBy('pon_index'),
                'ports.onus' => fn ($q) => $q->with('customer:id,name,customer_code')->orderBy('serial_number'),
            ])
            ->orderBy('display_name')
            ->get();

        $totalOnus = 0;
        $weakOnus = 0;
        $offlineOnus = 0;

        $oltRows = $olts->map(function (Device $olt) use ($onlineStatuses, &$totalOnus, &$weakOnus, &$offlineOnus): array {
            $health = is_array($olt->olt_health) ? $olt->olt_health : [];
            $ports = $olt->ports->map(function ($port) use ($onlineStatuses, &$totalOnus, &$weakOnus, &$offlineOnus): array {
                $onus = $port->onus;
                $mapped = $this->mapOnus($onus, $onlineStatuses);
                $totalOnus += $mapped['total'];
                $weakOnus += $mapped['weak'];
                $offlineOnus += $mapped['offline'];

                $pathHealth = match (true) {
                    $mapped['critical'] > 0 => 'critical',
                    $mapped['weak'] > ($mapped['total'] / 2) && $mapped['total'] > 0 => 'degraded',
                    $mapped['offline'] === $mapped['total'] && $mapped['total'] > 0 => 'down',
                    default => 'healthy',
                };

                return [
                    'id' => $port->id,
                    'label' => $port->label ?? "{$port->card_index}/{$port->pon_index}",
                    'onu_total' => $mapped['total'],
                    'onu_online' => $mapped['online'],
                    'onu_weak' => $mapped['weak'],
                    'onu_critical' => $mapped['critical'],
                    'path_health' => $pathHealth,
                    'onus' => $mapped,
                ];
            })->values()->all();

            $loose = Device::query()
                ->where('tenant_id', $olt->tenant_id)
                ->where('olt_id', $olt->id)
                ->where('type', 'onu')
                ->whereNull('olt_port_id')
                ->with('customer:id,name,customer_code')
                ->orderBy('serial_number')
                ->limit(self::ONU_PREVIEW_LIMIT + 1)
                ->get();

            $looseMapped = $this->mapOnus($loose, $onlineStatuses);
            $totalOnus += $looseMapped['total'];
            $weakOnus += $looseMapped['weak'];
            $offlineOnus += $looseMapped['offline'];

            $oltOnus = Device::query()->where('olt_id', $olt->id)->where('type', 'onu')->get();
            $oltOnline = $oltOnus->filter(fn (Device $o) => in_array((string) $o->onu_oper_status, $onlineStatuses, true))->count();

            $oltHealthScore = isset($health['health_score']) ? (int) $health['health_score'] : null;

            return [
                'id' => $olt->id,
                'label' => $olt->adminLabel(),
                'management_ip' => $olt->management_ip,
                'status' => $olt->status,
                'cpu_percent' => $health['cpu_percent'] ?? null,
                'memory_percent' => $health['memory_percent'] ?? null,
                'health_score' => $oltHealthScore,
                'onu_total' => $oltOnus->count(),
                'onu_online' => $oltOnline,
                'ports' => $ports,
                'loose_onus' => $looseMapped,
            ];
        })->values()->all();

        return [
            'summary' => [
                'olts' => count($oltRows),
                'onus' => $totalOnus,
                'weak_onus' => $weakOnus,
                'offline_onus' => $offlineOnus,
            ],
            'olts' => $oltRows,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Device>  $onus
     * @param  list<string>  $onlineStatuses
     * @return array{items: list<array<string, mixed>>, total: int, online: int, weak: int, critical: int, offline: int, truncated: bool}
     */
    private function mapOnus($onus, array $onlineStatuses): array
    {
        $total = $onus->count();
        $weak = 0;
        $critical = 0;
        $offline = 0;
        $online = 0;

        $items = $onus->take(self::ONU_PREVIEW_LIMIT)->map(function (Device $onu) use ($onlineStatuses, &$weak, &$critical, &$offline, &$online): array {
            $oper = strtolower((string) ($onu->onu_oper_status ?? ''));
            $isOnline = in_array($oper, $onlineStatuses, true);
            $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
            $level = OnuSignalLevel::classifyRx($rx, $oper);

            if ($isOnline) {
                $online++;
            } else {
                $offline++;
            }
            if ($level === OnuSignalLevel::WARNING || $level === OnuSignalLevel::HIGH) {
                $weak++;
            }
            if ($level === OnuSignalLevel::CRITICAL || $level === OnuSignalLevel::OFFLINE) {
                $critical++;
            }

            return [
                'id' => $onu->id,
                'serial' => $onu->serial_number,
                'label' => $onu->adminLabel(),
                'online' => $isOnline,
                'rx_dbm' => $rx,
                'signal_level' => $level,
                'customer' => $onu->customer?->name,
            ];
        })->values()->all();

        return [
            'items' => $items,
            'total' => $total,
            'online' => $online,
            'weak' => $weak,
            'critical' => $critical,
            'offline' => $offline,
            'truncated' => $total > self::ONU_PREVIEW_LIMIT,
        ];
    }
}
