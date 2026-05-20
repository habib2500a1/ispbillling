<?php

namespace App\Console\Commands;

use App\Models\Package;
use Illuminate\Console\Command;

class CleanupMikrotikImportedPackagesCommand extends Command
{
    protected $signature = 'packages:cleanup-mikrotik-imports
                            {--tenant= : Tenant ID}
                            {--dry-run : List only, do not delete}';

    protected $description = 'Remove unused packages that were auto-imported from MikroTik (name = profile name, no subscribers)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $query = Package::query()
            ->withoutGlobalScopes()
            ->whereNotNull('mikrotik_synced_at')
            ->whereColumn('name', 'mikrotik_profile_name')
            ->whereDoesntHave('customers');

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', (int) $tenantId);
        }

        $packages = $query->orderBy('id')->get();

        if ($packages->isEmpty()) {
            $this->info('No unused MikroTik-imported packages to remove.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name (profile)', 'Server'],
            $packages->map(fn (Package $p): array => [
                $p->id,
                $p->name,
                (string) $p->mikrotik_server_id,
            ])->all(),
        );

        if ($dryRun) {
            $this->warn('Dry run — nothing deleted.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($packages as $package) {
            $package->delete();
            $deleted++;
        }

        $this->info("Deleted {$deleted} unused MikroTik-import package(s).");

        return self::SUCCESS;
    }
}
