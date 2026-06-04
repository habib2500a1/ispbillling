<?php

use App\Models\AutomaticProcess;
use App\Models\Tenant;
use App\Services\Automation\AutomaticProcessScheduler;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automatic_processes')) {
            return;
        }

        $tenantId = (int) (Tenant::query()->withoutGlobalScopes()->value('id') ?? 1);
        $scheduler = app(AutomaticProcessScheduler::class);

        $process = AutomaticProcess::query()->withoutGlobalScopes()->updateOrCreate(
            ['slug' => 'sync-aveis-gpon-onus'],
            [
                'tenant_id' => $tenantId,
                'name' => 'Sync Aveis GPON ONUs + auto-link',
                'description' => 'SNMP sync from Aveis XE08 OLT, then FDB/PPP auto-link subscribers and MikroTik VLAN.',
                'artisan_command' => 'isp:sync-aveis-gpon-onus',
                'command_options' => [],
                'execute_at' => '00:00',
                'interval' => 'every_five_minutes',
                'without_overlapping_minutes' => 8,
                'when_config_key' => 'optical.enabled',
                'enabled' => true,
                'sort_order' => 119,
            ],
        );

        if ($process->next_run_at === null) {
            $process->forceFill([
                'next_run_at' => $scheduler->computeNextRunAt($process),
            ])->save();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('automatic_processes')) {
            return;
        }

        AutomaticProcess::query()->withoutGlobalScopes()->where('slug', 'sync-aveis-gpon-onus')->delete();
    }
};
