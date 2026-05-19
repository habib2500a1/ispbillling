<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Mikrotik\MikrotikSessionIntegrityService;
use Illuminate\Console\Command;

class DetectMikrotikSessionMismatchesCommand extends Command
{
    protected $signature = 'isp:mikrotik-session-integrity {--tenant= : Limit to tenant id}';

    protected $description = 'Detect PPP session mismatches (multi-router, wrong router, overdue online).';

    public function handle(MikrotikSessionIntegrityService $service): int
    {
        $tenantFilter = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;

        $query = Tenant::query()->orderBy('id');
        if ($tenantFilter !== null) {
            $query->where('id', $tenantFilter);
        }

        $totalCreated = 0;
        $totalResolved = 0;

        foreach ($query->cursor() as $tenant) {
            $result = $service->scanTenant((int) $tenant->id);
            $totalCreated += $result['alerts_created'];
            $totalResolved += $result['alerts_resolved'];
            $this->line("Tenant {$tenant->id}: {$result['sessions']} sessions, +{$result['alerts_created']} alerts, resolved {$result['alerts_resolved']}");
        }

        $this->info("Done. Created {$totalCreated}, resolved {$totalResolved}.");

        return self::SUCCESS;
    }
}
