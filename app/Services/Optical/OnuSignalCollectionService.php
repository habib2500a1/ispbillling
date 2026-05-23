<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\OnuSignalLog;
use App\Services\Network\GponIntelligenceService;
use App\Support\OnuSignalLevel;
use Carbon\Carbon;

final class OnuSignalCollectionService
{
    public function __construct(
        private readonly GponIntelligenceService $gpon,
        private readonly OnuSignalAlertService $alerts,
        private readonly FiberFaultDetector $fiberFaults,
        private readonly PonPortHealthService $ponHealth,
        private readonly OpticalReadingPipeline $pipeline,
    ) {}

    /**
     * @return array{onus: int, logged: int, alerts: int, fiber_faults: int}
     */
    public function collectForTenant(int $tenantId): array
    {
        $now = now();
        $logged = 0;
        $alertCount = 0;

        $olts = Device::query()
            ->withoutGlobalScopes()
            ->olts()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'decommissioned')
            ->get();

        foreach ($olts as $olt) {
            $this->gpon->syncAllOnuOpticalForOlt($olt);

            $olt->onus()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->select(['id', 'rx_power_dbm', 'tx_power_dbm', 'onu_oper_status', 'olt_id', 'tenant_id', 'type'])
                ->chunkById(50, function ($onus) use ($now, &$logged, &$alertCount): void {
                    foreach ($onus as $onu) {
                        if ($onu->rx_power_dbm === null && $onu->tx_power_dbm === null) {
                            continue;
                        }

                        $this->logSnapshot($onu, $now);
                        $logged++;
                        $alertCount += $this->alerts->evaluateOnu($onu->fresh(), $now);
                    }
                });

            $alertCount += $this->fiberFaults->evaluateOlt($olt, $now);
            $this->ponHealth->aggregateForOlt($olt, $now);
        }

        $this->rollupHourlyLogs($tenantId, $now);
        app(OpticalDatabaseMaintenanceService::class)->prune($tenantId);

        return [
            'onus' => Device::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('type', 'onu')->count(),
            'logged' => $logged,
            'alerts' => $alertCount,
            'fiber_faults' => 0,
        ];
    }

    public function ingestOnuReading(Device $onu, ?float $rxDbm, ?float $txDbm, ?string $operStatus = null, array $meta = []): void
    {
        if ($onu->type !== 'onu') {
            return;
        }

        $this->pipeline->ingest($onu, [
            'rx_raw' => $rxDbm,
            'tx_raw' => $txDbm,
            'oper_status' => $operStatus,
            'temperature' => $meta['temperature'] ?? $meta['temperature_c'] ?? null,
            'voltage' => $meta['voltage'] ?? $meta['voltage_v'] ?? null,
            'vendor_profile' => $meta['vendor_profile'] ?? $onu->gpon_profile ?? $onu->olt?->olt_driver ?? 'generic_gpon',
            'source' => $meta['source'] ?? 'webhook',
        ]);

        $this->alerts->evaluateOnu($onu->fresh(), now());
    }

    private function logSnapshot(Device $onu, Carbon $at): void
    {
        $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
        $tx = $onu->tx_power_dbm !== null ? (float) $onu->tx_power_dbm : null;
        $oper = strtolower((string) ($onu->onu_oper_status ?? 'unknown'));
        $rxLevel = OnuSignalLevel::classifyRx($rx, $oper);

        OnuSignalLog::query()->create([
            'tenant_id' => $onu->tenant_id,
            'device_id' => $onu->id,
            'olt_id' => $onu->olt_id,
            'olt_port_id' => $onu->olt_port_id,
            'rx_power_dbm' => $rx,
            'tx_power_dbm' => $tx,
            'raw_rx_power_dbm' => $rx,
            'raw_tx_power_dbm' => $tx,
            'rx_level' => $rxLevel,
            'tx_level' => OnuSignalLevel::classifyTx($tx),
            'onu_oper_status' => $oper,
            'health_score' => OnuSignalLevel::healthScoreFromRxLevel($rxLevel),
            'granularity' => 'snapshot',
            'poll_source' => 'scheduled',
            'is_spike' => false,
            'sampled_at' => $at,
        ]);
    }

    private function rollupHourlyLogs(int $tenantId, Carbon $now): void
    {
        if ($now->minute >= 10) {
            return;
        }

        $hourStart = $now->copy()->startOfHour()->subHour();

        $onus = OnuSignalLog::query()
            ->where('tenant_id', $tenantId)
            ->where('granularity', 'snapshot')
            ->where('is_spike', false)
            ->where('sampled_at', '>=', $hourStart)
            ->where('sampled_at', '<', $hourStart->copy()->addHour())
            ->selectRaw('device_id, AVG(rx_power_dbm) as avg_rx, AVG(tx_power_dbm) as avg_tx, MAX(health_score) as max_health')
            ->groupBy('device_id')
            ->get();

        foreach ($onus as $row) {
            $device = Device::query()->withoutGlobalScopes()->find($row->device_id);
            if ($device === null) {
                continue;
            }

            $rx = $row->avg_rx !== null ? round((float) $row->avg_rx, 3) : null;
            $oper = strtolower((string) ($device->onu_oper_status ?? 'unknown'));
            $rxLevel = OnuSignalLevel::classifyRx($rx, $oper);

            OnuSignalLog::query()->create([
                'tenant_id' => $tenantId,
                'device_id' => $device->id,
                'olt_id' => $device->olt_id,
                'olt_port_id' => $device->olt_port_id,
                'rx_power_dbm' => $rx,
                'tx_power_dbm' => $row->avg_tx !== null ? round((float) $row->avg_tx, 3) : null,
                'rx_level' => $rxLevel,
                'tx_level' => OnuSignalLevel::classifyTx($row->avg_tx !== null ? (float) $row->avg_tx : null),
                'onu_oper_status' => $oper,
                'health_score' => (int) $row->max_health,
                'granularity' => 'hourly',
                'sampled_at' => $hourStart,
                'is_spike' => false,
            ]);
        }
    }

}
