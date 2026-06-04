<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\OltPort;
use App\Models\PonSignalStat;
use App\Services\Olt\OltPortCatalogService;
use App\Support\OnuSignalLevel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class PonPortHealthService
{
    public function __construct(
        private readonly OltPortCatalogService $portCatalog,
    ) {}

    public function aggregateForOlt(Device $olt, Carbon $now): void
    {
        $this->portCatalog->ensureForOlt($olt);

        $onus = $olt->onus()->withoutGlobalScopes()->get();
        $groups = $onus->groupBy(fn (Device $o): string => ((int) ($o->card_no ?? 0)).'-'.((int) ($o->pon_no ?? 0)));

        $ports = OltPort::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $olt->tenant_id)
            ->where('device_id', $olt->id)
            ->orderBy('card_index')
            ->orderBy('pon_index')
            ->get();

        foreach ($ports as $port) {
            $key = ((int) $port->card_index).'-'.((int) $port->pon_index);
            /** @var Collection<int, Device> $group */
            $group = $groups->get($key, collect());

            $this->persistPortStat($olt, $port, $group, $now);
        }
    }

    /**
     * @param  Collection<int, Device>  $group
     */
    private function persistPortStat(Device $olt, OltPort $port, Collection $group, Carbon $now): void
    {
        $rxValues = $group->map(fn (Device $o) => $o->rx_power_dbm !== null ? (float) $o->rx_power_dbm : null)->filter();

        $online = $group->filter(fn (Device $o): bool => in_array(
            strtolower((string) ($o->onu_oper_status ?? '')),
            ['online', 'active', 'up'],
            true,
        ))->count();

        $critical = $group->filter(function (Device $o): bool {
            $rx = $o->rx_power_dbm !== null ? (float) $o->rx_power_dbm : null;

            return OnuSignalLevel::classifyRx($rx, strtolower((string) ($o->onu_oper_status ?? ''))) === OnuSignalLevel::CRITICAL;
        })->count();

        $warning = $group->filter(function (Device $o): bool {
            $rx = $o->rx_power_dbm !== null ? (float) $o->rx_power_dbm : null;

            return OnuSignalLevel::classifyRx($rx, strtolower((string) ($o->onu_oper_status ?? ''))) === OnuSignalLevel::WARNING;
        })->count();

        $total = $group->count();
        $faultPercent = $total > 0 ? round((($critical + $warning) / $total) * 100, 2) : 0;

        $operStatus = match (true) {
            $total === 0 => 'empty',
            $online === 0 => 'down',
            default => 'up',
        };

        $port->forceFill(['oper_status' => $operStatus])->saveQuietly();

        PonSignalStat::query()->updateOrCreate(
            [
                'tenant_id' => $olt->tenant_id,
                'olt_id' => $olt->id,
                'card_no' => $port->card_index,
                'pon_no' => $port->pon_index,
            ],
            [
                'olt_port_id' => $port->id,
                'onu_total' => $total,
                'onu_online' => $online,
                'onu_offline' => $total - $online,
                'onu_critical' => $critical,
                'onu_warning' => $warning,
                'avg_rx_dbm' => $rxValues->isNotEmpty() ? round($rxValues->avg(), 3) : null,
                'min_rx_dbm' => $rxValues->isNotEmpty() ? round($rxValues->min(), 3) : null,
                'max_rx_dbm' => $rxValues->isNotEmpty() ? round($rxValues->max(), 3) : null,
                'fault_percent' => $faultPercent,
                'computed_at' => $now,
            ],
        );
    }
}
