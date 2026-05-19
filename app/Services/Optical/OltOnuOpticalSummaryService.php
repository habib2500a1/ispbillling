<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Support\OnuSignalLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OltOnuOpticalSummaryService
{
    /**
     * @return array{
     *   total: int,
     *   with_rx: int,
     *   avg_rx: ?float,
     *   min_rx: ?float,
     *   max_rx: ?float,
     *   excellent: int,
     *   good: int,
     *   warning: int,
     *   critical: int,
     *   offline: int,
     *   no_data: int
     * }
     */
    public function forOlt(Device $olt): array
    {
        $agg = DB::table('devices')
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(rx_power_dbm) as with_rx')
            ->selectRaw('AVG(rx_power_dbm) as avg_rx')
            ->selectRaw('MIN(rx_power_dbm) as min_rx')
            ->selectRaw('MAX(rx_power_dbm) as max_rx')
            ->first();

        $stats = [
            'total' => (int) ($agg->total ?? 0),
            'with_rx' => (int) ($agg->with_rx ?? 0),
            'avg_rx' => $agg->avg_rx !== null ? round((float) $agg->avg_rx, 2) : null,
            'min_rx' => $agg->min_rx !== null ? round((float) $agg->min_rx, 2) : null,
            'max_rx' => $agg->max_rx !== null ? round((float) $agg->max_rx, 2) : null,
            'excellent' => 0,
            'good' => 0,
            'warning' => 0,
            'critical' => 0,
            'offline' => 0,
            'no_data' => max(0, (int) ($agg->total ?? 0) - (int) ($agg->with_rx ?? 0)),
        ];

        Device::query()
            ->withoutGlobalScopes()
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->select(['rx_power_dbm', 'onu_oper_status'])
            ->orderBy('id')
            ->chunk(100, function ($chunk) use (&$stats): void {
                foreach ($chunk as $onu) {
                    $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
                    $level = OnuSignalLevel::classifyRx($rx, strtolower((string) ($onu->onu_oper_status ?? '')));

                    match ($level) {
                        OnuSignalLevel::EXCELLENT => $stats['excellent']++,
                        OnuSignalLevel::GOOD => $stats['good']++,
                        OnuSignalLevel::WARNING => $stats['warning']++,
                        OnuSignalLevel::CRITICAL => $stats['critical']++,
                        OnuSignalLevel::OFFLINE => $stats['offline']++,
                        default => null,
                    };
                }
            });

        return $stats;
    }

    /**
     * @param  Collection<int, Device>  $onus
     * @return array<string, int|float|null>
     */
    public function summarize(Collection $onus): array
    {
        $stats = [
            'total' => $onus->count(),
            'with_rx' => 0,
            'avg_rx' => null,
            'min_rx' => null,
            'max_rx' => null,
            'excellent' => 0,
            'good' => 0,
            'warning' => 0,
            'critical' => 0,
            'offline' => 0,
            'no_data' => 0,
        ];

        $rxValues = [];

        foreach ($onus as $onu) {
            $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
            $oper = strtolower((string) ($onu->onu_oper_status ?? ''));
            $level = OnuSignalLevel::classifyRx($rx, $oper);

            if ($rx !== null) {
                $stats['with_rx']++;
                $rxValues[] = $rx;
            } else {
                $stats['no_data']++;
            }

            match ($level) {
                OnuSignalLevel::EXCELLENT => $stats['excellent']++,
                OnuSignalLevel::GOOD => $stats['good']++,
                OnuSignalLevel::WARNING => $stats['warning']++,
                OnuSignalLevel::CRITICAL => $stats['critical']++,
                OnuSignalLevel::OFFLINE => $stats['offline']++,
                default => null,
            };
        }

        if ($rxValues !== []) {
            $stats['avg_rx'] = round(array_sum($rxValues) / count($rxValues), 2);
            $stats['min_rx'] = round(min($rxValues), 2);
            $stats['max_rx'] = round(max($rxValues), 2);
        }

        return $stats;
    }
}
