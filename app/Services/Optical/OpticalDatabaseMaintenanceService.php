<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\OltHealthLog;
use App\Models\OnuSignalLog;
use App\Models\SignalAlert;
use App\Models\SignalPrediction;
use App\Models\SnmpPollLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Optical DB housekeeping: status reports and retention pruning.
 */
final class OpticalDatabaseMaintenanceService
{
    /**
     * @return array<string, mixed>
     */
    public function status(?int $tenantId = null): array
    {
        $retention = config('optical_database.retention', []);

        $onuQuery = Device::query()->withoutGlobalScopes()->where('type', 'onu');
        $oltQuery = Device::query()->withoutGlobalScopes()->where('type', 'olt');

        if ($tenantId !== null) {
            $onuQuery->where('tenant_id', $tenantId);
            $oltQuery->where('tenant_id', $tenantId);
        }

        $withRx = (clone $onuQuery)->whereNotNull('rx_power_dbm')->count();
        $withTx = (clone $onuQuery)->whereNotNull('tx_power_dbm')->count();

        return [
            'database' => config('database.connections.'.config('database.default').'.database'),
            'tenant_id' => $tenantId,
            'retention' => $retention,
            'counts' => [
                'olts' => $oltQuery->count(),
                'onus' => $onuQuery->count(),
                'onus_with_rx_dbm' => $withRx,
                'onus_with_tx_dbm' => $withTx,
                'onu_signal_logs' => $this->countTable('onu_signal_logs', $tenantId),
                'onu_signal_logs_snapshot' => $this->countTable('onu_signal_logs', $tenantId, ['granularity' => 'snapshot']),
                'onu_signal_logs_hourly' => $this->countTable('onu_signal_logs', $tenantId, ['granularity' => 'hourly']),
                'onu_health_scores' => $this->countTable('onu_health_scores', $tenantId),
                'signal_alerts_open' => $this->countAlerts('open', $tenantId),
                'signal_alerts_resolved' => $this->countAlerts('resolved', $tenantId),
                'olt_health_logs' => $this->countTable('olt_health_logs', $tenantId),
                'snmp_poll_logs' => $this->countTable('snmp_poll_logs', $tenantId),
                'signal_predictions' => $this->countTable('signal_predictions', $tenantId),
                'pon_signal_stats' => $this->countTable('pon_signal_stats', $tenantId),
                'fiber_fault_logs' => $this->countTable('fiber_fault_logs', $tenantId),
            ],
            'latest' => [
                'onu_signal_log' => $this->latestTimestamp('onu_signal_logs', 'sampled_at', $tenantId),
                'olt_health_log' => $this->latestTimestamp('olt_health_logs', 'sampled_at', $tenantId),
                'onu_polled' => (clone $onuQuery)->max('last_polled_at'),
            ],
            'tables' => config('optical_database.tables', []),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function prune(?int $tenantId = null): array
    {
        $retention = config('optical_database.retention', []);
        $deleted = [
            'snapshots' => 0,
            'hourly' => 0,
            'olt_health' => 0,
            'snmp_polls' => 0,
            'resolved_alerts' => 0,
            'predictions' => 0,
            'fiber_faults' => 0,
        ];

        if (Schema::hasTable('onu_signal_logs')) {
            $deleted['snapshots'] = $this->deleteLogs(
                OnuSignalLog::query(),
                $tenantId,
                'snapshot',
                (int) ($retention['snapshot_days'] ?? 14),
            );
            $deleted['hourly'] = $this->deleteLogs(
                OnuSignalLog::query(),
                $tenantId,
                'hourly',
                (int) ($retention['hourly_days'] ?? 90),
            );
        }

        if (Schema::hasTable('olt_health_logs')) {
            $q = OltHealthLog::query()->where('sampled_at', '<', now()->subDays((int) ($retention['olt_health_days'] ?? 30)));
            if ($tenantId !== null) {
                $q->where('tenant_id', $tenantId);
            }
            $deleted['olt_health'] = $q->delete();
        }

        if (Schema::hasTable('snmp_poll_logs')) {
            $q = SnmpPollLog::query()->where('polled_at', '<', now()->subDays((int) ($retention['snmp_poll_days'] ?? 14)));
            if ($tenantId !== null) {
                $q->where('tenant_id', $tenantId);
            }
            $deleted['snmp_polls'] = $q->delete();
        }

        if (Schema::hasTable('signal_alerts')) {
            $q = SignalAlert::query()
                ->where('status', 'resolved')
                ->where('resolved_at', '<', now()->subDays((int) ($retention['resolved_alert_days'] ?? 90)));
            if ($tenantId !== null) {
                $q->where('tenant_id', $tenantId);
            }
            $deleted['resolved_alerts'] = $q->delete();
        }

        if (Schema::hasTable('signal_predictions')) {
            $q = SignalPrediction::query()
                ->where(function ($sub): void {
                    $sub->where('expires_at', '<', now())
                        ->orWhere('predicted_at', '<', now()->subDays((int) config('optical_database.retention.prediction_days', 7)));
                });
            if ($tenantId !== null) {
                $q->where('tenant_id', $tenantId);
            }
            $deleted['predictions'] = $q->delete();
        }

        if (Schema::hasTable('fiber_fault_logs')) {
            $q = DB::table('fiber_fault_logs')
                ->where('detected_at', '<', now()->subDays((int) ($retention['fiber_fault_days'] ?? 180)));
            if ($tenantId !== null) {
                $q->where('tenant_id', $tenantId);
            }
            $deleted['fiber_faults'] = $q->delete();
        }

        return $deleted;
    }

    private function deleteLogs($query, ?int $tenantId, string $granularity, int $days): int
    {
        $q = (clone $query)
            ->where('granularity', $granularity)
            ->where('sampled_at', '<', now()->subDays($days));

        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->delete();
    }

    private function countTable(string $table, ?int $tenantId, array $where = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $q = DB::table($table);
        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        }
        foreach ($where as $col => $val) {
            $q->where($col, $val);
        }

        return (int) $q->count();
    }

    private function countAlerts(string $status, ?int $tenantId): int
    {
        if (! Schema::hasTable('signal_alerts')) {
            return 0;
        }

        $q = SignalAlert::query()->where('status', $status);
        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->count();
    }

    private function latestTimestamp(string $table, string $column, ?int $tenantId): ?string
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $q = DB::table($table);
        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        }

        $val = $q->max($column);

        return $val !== null ? (string) $val : null;
    }
}
