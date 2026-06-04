<?php

namespace App\Console\Commands;

use App\Services\Import\ShebaFiJsonImporter;
use App\Support\TenantResolver;
use Illuminate\Console\Command;

class ImportShebaFiJsonCommand extends Command
{
    protected $signature = 'isp:import-sheba-fi-json
        {path : Path to JSON export file}
        {--tenant= : Tenant ID (defaults to first tenant)}
        {--dry-run : Validate only, no writes}';

    protected $description = 'Import subscribers from a Sheba-Fi JSON export (not live demo scraping)';

    public function handle(ShebaFiJsonImporter $importer): int
    {
        $path = (string) $this->argument('path');
        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant') !== null
            ? (int) $this->option('tenant')
            : (int) (TenantResolver::currentTenantId() ?? \App\Models\Tenant::query()->value('id'));

        if ($tenantId <= 0) {
            $this->error('No tenant found. Pass --tenant=ID');

            return self::FAILURE;
        }

        $stats = $importer->import($path, $tenantId, (bool) $this->option('dry-run'));

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all(),
        );

        return self::SUCCESS;
    }
}
