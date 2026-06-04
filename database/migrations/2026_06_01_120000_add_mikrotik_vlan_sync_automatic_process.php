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
            ['slug' => 'mikrotik-sync-vlan'],
            [
                'tenant_id' => $tenantId,
                'name' => 'MikroTik VLAN sync (PPP secrets → subscribers)',
                'description' => 'Pull VLAN from MikroTik PPPoE interface bindings into subscriber meta for NOC tables.',
                'artisan_command' => 'isp:sync-mikrotik-vlan',
                'command_options' => [],
                'execute_at' => '00:00',
                'interval' => 'every_thirty_minutes',
                'without_overlapping_minutes' => 15,
                'when_config_key' => 'mikrotik.auto_sync_vlan',
                'enabled' => true,
                'sort_order' => 81,
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

        AutomaticProcess::query()->withoutGlobalScopes()->where('slug', 'mikrotik-sync-vlan')->delete();
    }
};
