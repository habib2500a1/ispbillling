<?php

namespace App\Console\Commands;

use App\Models\AutomaticProcess;
use App\Models\Tenant;
use App\Services\Automation\AutomaticProcessScheduler;
use App\Services\Optical\OpticalDatabaseMaintenanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupOpticalDatabaseCommand extends Command
{
    protected $signature = 'isp:setup-optical-database
                            {--poll : Run OLT SNMP poll after setup}
                            {--collect : Run ONU signal collect after setup}';

    protected $description = 'Setup optical DB: migrate tables, register cron jobs, show status';

    public function handle(OpticalDatabaseMaintenanceService $db): int
    {
        $this->info('Setting up optical monitoring database…');

        $this->call('migrate', ['--force' => true]);

        $this->registerAutomaticProcesses();

        $status = $db->status();
        $this->newLine();
        $this->info('Database: '.($status['database'] ?? '—'));
        $this->table(
            ['Metric', 'Count'],
            [
                ['OLTs', $status['counts']['olts']],
                ['ONUs', $status['counts']['onus']],
                ['ONUs with RX dBm', $status['counts']['onus_with_rx_dbm']],
                ['Signal log rows', $status['counts']['onu_signal_logs']],
            ],
        );

        if ($this->option('poll')) {
            $this->call('isp:poll-olt-intelligence');
        }

        if ($this->option('collect')) {
            $this->call('isp:collect-onu-signals');
        }

        $this->newLine();
        $this->comment('Live dBm: devices.rx_power_dbm / tx_power_dbm');
        $this->comment('Panel: /admin/optical-noc');
        $this->comment('Status: php artisan isp:optical-db-status');

        return self::SUCCESS;
    }

    private function registerAutomaticProcesses(): void
    {
        $tenantId = (int) (Tenant::query()->value('id') ?? 1);
        $scheduler = app(AutomaticProcessScheduler::class);

        $opticalJobs = [
            [
                'slug' => 'prune-optical-database',
                'name' => 'Prune optical DB history (retention)',
                'artisan_command' => 'isp:prune-optical-database',
                'command_options' => [],
                'execute_at' => '03:30',
                'interval' => 'daily',
                'when_config_key' => 'optical.enabled',
                'without_overlapping_minutes' => 30,
                'sort_order' => 131,
            ],
        ];

        foreach ($opticalJobs as $row) {
            $process = AutomaticProcess::query()->withoutGlobalScopes()->updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['tenant_id' => $tenantId, 'enabled' => true]),
            );

            if ($process->next_run_at === null) {
                $process->forceFill([
                    'next_run_at' => $scheduler->computeNextRunAt($process),
                ])->save();
            }

            $this->line("  ✓ Automatic process: {$row['slug']}");
        }
    }
}
