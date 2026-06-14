<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Reseller;
use App\Services\Import\LegacyPortalMacResellerImporter;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;

class ImportLegacyPortalResellersCommand extends Command
{
    protected $signature = 'isp:import-legacy-portal-resellers
                            {--force : Update existing resellers and re-link clients}
                            {--reset : Clear MAC reseller links and merge Sm* duplicates before import}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Import MAC resellers and link subscribers from legacy portal';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));

        if ($password === '' || $password === 'KEEP_CURRENT') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env or pass --password=');

            return self::FAILURE;
        }

        $this->info("Logging in to {$baseUrl}…");
        $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $client->login();

        $importer = new LegacyPortalMacResellerImporter;

        if ($this->option('reset')) {
            $merged = $importer->mergeDuplicateMacClients();
            $this->info("Merged/retired {$merged} duplicate Sm* MAC client row(s).");
        }

        $stats = $importer->importAll($client, (bool) $this->option('force'), (bool) $this->option('reset'));

        $this->table(['', 'Count'], [
            ['Imported', $stats['imported']],
            ['Updated', $stats['updated']],
            ['Skipped', $stats['skipped']],
            ['Subscribers linked', $stats['linked']],
            ['Subscribers imported (MAC)', $stats['clients_imported']],
            ['Package assignments synced', $stats['packages_synced']],
            ['Links cleared (reset)', $stats['duplicates_removed']],
            ['Total resellers', Reseller::query()->count()],
            ['Subscribers with reseller', Customer::query()->whereNotNull('reseller_id')->count()],
        ]);

        Reseller::query()->orderBy('name')->get(['code', 'name', 'wallet_balance', 'meta'])->each(function (Reseller $r): void {
            $macId = $r->meta['legacy_portal_mac_reseller_id'] ?? '—';
            $clients = $r->meta['number_of_clients'] ?? Customer::query()->where('reseller_id', $r->id)->count();
            $this->line("  {$r->code} · {$r->name} · wallet {$r->wallet_balance} BDT · ISP clients {$clients} (MAC #{$macId})");
        });

        return self::SUCCESS;
    }
}
