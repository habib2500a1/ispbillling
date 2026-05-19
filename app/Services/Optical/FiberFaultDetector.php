<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\FiberFaultLog;
use App\Models\SignalAlert;
use App\Support\OnuSignalLevel;
use Carbon\Carbon;

final class FiberFaultDetector
{
    public function evaluateOlt(Device $olt, Carbon $now): int
    {
        $onus = $olt->onus()->withoutGlobalScopes()->get();
        $total = $onus->count();
        if ($total === 0) {
            return 0;
        }

        $offline = $onus->filter(fn (Device $o): bool => in_array(
            strtolower((string) ($o->onu_oper_status ?? '')),
            ['offline', 'los', 'power_fail'],
            true,
        ))->count();

        $fraction = $offline / $total;
        $minOnus = (int) config('optical.fiber_cut_min_onus', 5);
        $threshold = (float) config('optical.fiber_cut_onu_fraction', 0.3);

        if ($offline < $minOnus || $fraction < $threshold) {
            return 0;
        }

        $open = FiberFaultLog::query()
            ->where('olt_id', $olt->id)
            ->whereNull('resolved_at')
            ->where('detected_at', '>=', $now->copy()->subHours(6))
            ->exists();

        if ($open) {
            return 0;
        }

        $zones = $onus
            ->filter(fn (Device $o): bool => in_array(strtolower((string) ($o->onu_oper_status ?? '')), ['offline', 'los'], true))
            ->load('customer.zone')
            ->pluck('customer.zone.name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $description = sprintf(
            '%d of %d ONUs (%.0f%%) offline/LOS on OLT %s — possible fiber cut or PON failure.',
            $offline,
            $total,
            $fraction * 100,
            $olt->adminLabel(),
        );

        FiberFaultLog::query()->create([
            'tenant_id' => $olt->tenant_id,
            'olt_id' => $olt->id,
            'fault_type' => 'mass_offline',
            'severity' => 'critical',
            'affected_onu_count' => $offline,
            'affected_customer_count' => $onus->whereNotNull('customer_id')->count(),
            'description' => $description,
            'affected_zones' => $zones,
            'detected_at' => $now,
        ]);

        SignalAlert::query()->create([
            'tenant_id' => $olt->tenant_id,
            'olt_id' => $olt->id,
            'alert_type' => SignalAlert::TYPE_FIBER_CUT,
            'severity' => 'critical',
            'title' => 'Possible fiber cut / PON failure',
            'message' => $description,
            'status' => 'open',
            'detected_at' => $now,
        ]);

        return 1;
    }

    /**
     * @return array{total: int, critical: int, warning: int, offline: int, excellent: int}
     */
    public static function summarizeOnus(iterable $onus): array
    {
        $stats = ['total' => 0, 'critical' => 0, 'warning' => 0, 'offline' => 0, 'excellent' => 0, 'good' => 0];

        foreach ($onus as $onu) {
            $stats['total']++;
            $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
            $level = OnuSignalLevel::classifyRx($rx, strtolower((string) ($onu->onu_oper_status ?? '')));
            match ($level) {
                OnuSignalLevel::CRITICAL => $stats['critical']++,
                OnuSignalLevel::WARNING => $stats['warning']++,
                OnuSignalLevel::OFFLINE => $stats['offline']++,
                OnuSignalLevel::EXCELLENT => $stats['excellent']++,
                OnuSignalLevel::GOOD => $stats['good']++,
                default => null,
            };
        }

        return $stats;
    }
}
