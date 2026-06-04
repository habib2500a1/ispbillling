<?php

namespace App\Console\Commands;

use App\Services\Import\LegacyPortalEmployeeImporter;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;

class ImportLegacyPortalEmployeesCommand extends Command
{
    protected $signature = 'isp:import-legacy-portal-employees
                            {--force : Update existing employees with same employee_code}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Import HR staff (employees) from legacy portal Employee module';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));

        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $this->info("Logging in to {$baseUrl}…");
        $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $client->login();

        $stats = (new LegacyPortalEmployeeImporter)->importAll($client, (bool) $this->option('force'));

        $this->table(['', 'Count'], [
            ['Imported', $stats['imported']],
            ['Updated', $stats['updated']],
            ['Skipped', $stats['skipped']],
            ['Total local', \App\Models\Employee::query()->count()],
        ]);

        return self::SUCCESS;
    }
}
