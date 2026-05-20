<?php

namespace App\Console\Commands;

use App\Services\Dashboard\DashboardPreferencesService;
use Illuminate\Console\Command;

class RepairDashboardPreferencesCommand extends Command
{
    protected $signature = 'isp:repair-dashboard-prefs';

    protected $description = 'Remove legacy widget classes from stored dashboard preferences (safe, no billing data touched).';

    public function handle(DashboardPreferencesService $service): int
    {
        $updated = $service->migrateStoredPreferences();

        $this->info("Repaired {$updated} user dashboard preference record(s).");

        return self::SUCCESS;
    }
}
