<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\PonSignalStat;
use App\Support\OnuSignalLevel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class PonPortHealthService
{
    public function aggregateForOlt(Device $olt, Carbon $now): void
    {
        $onus = $olt->onus()->withoutGlobalScopes()->get();

        $groups = $onus->groupBy(fn (Device $o): string => (string) ($o->olt_port_id ?? 'card-'.($o->card_no ?? 0).'-pon-'.($o->pon_no ?? 0)));

        foreach ($groups as $portKey => $group) {
            /** @var Collection<int, Device> $group */
            $first = $group->first();
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

            PonSignalStat::query()->create([
                'tenant_id' => $olt->tenant_id,
                'olt_id' => $olt->id,
                'olt_port_id' => is_numeric($portKey) ? (int) $portKey : $first?->olt_port_id,
                'card_no' => $first?->card_no,
                'pon_no' => $first?->pon_no,
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
            ]);
        }
    }
}
