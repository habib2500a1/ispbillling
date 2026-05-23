<?php

namespace App\Console\Commands;

use App\Services\Optical\OpticalDatabaseMaintenanceService;
use Illuminate\Console\Command;

class OpticalDatabaseStatusCommand extends Command
{
    protected $signature = 'isp:optical-db-status {--tenant= : Tenant id} {--json : JSON output}';

    protected $description = 'Show optical monitoring database table counts and retention settings';

    public function handle(OpticalDatabaseMaintenanceService $db): int
    {
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $status = $db->status($tenantId);

        if ($this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('Optical DB: '.($status['database'] ?? '—'));
        if ($tenantId !== null) {
            $this->line("Tenant: #{$tenantId}");
        }

        $this->newLine();
        $this->comment('── Live (devices) ──');
        $c = $status['counts'];
        $this->table(
            ['Metric', 'Count'],
            [
                ['OLTs', $c['olts']],
                ['ONUs', $c['onus']],
                ['ONUs with RX dBm', $c['onus_with_rx_dbm']],
                ['ONUs with TX dBm', $c['onus_with_tx_dbm']],
            ],
        );

        $this->comment('── History & analytics ──');
        $this->table(
            ['Table', 'Rows'],
            [
                ['onu_signal_logs (snapshot)', $c['onu_signal_logs_snapshot']],
                ['onu_signal_logs (hourly)', $c['onu_signal_logs_hourly']],
                ['onu_health_scores', $c['onu_health_scores']],
                ['olt_health_logs', $c['olt_health_logs']],
                ['snmp_poll_logs', $c['snmp_poll_logs']],
                ['pon_signal_stats', $c['pon_signal_stats']],
                ['signal_predictions', $c['signal_predictions']],
            ],
        );

        $this->comment('── Alerts ──');
        $this->table(
            ['Table', 'Rows'],
            [
                ['signal_alerts (open)', $c['signal_alerts_open']],
                ['signal_alerts (resolved)', $c['signal_alerts_resolved']],
                ['fiber_fault_logs', $c['fiber_fault_logs']],
            ],
        );

        $this->comment('── Retention (days) ──');
        foreach ($status['retention'] as $key => $days) {
            $this->line("  {$key}: {$days}");
        }

        $this->newLine();
        $this->line('Live RX/TX: devices.rx_power_dbm / tx_power_dbm');
        $this->line('Prune: php artisan isp:prune-optical-database');

        return self::SUCCESS;
    }
}
