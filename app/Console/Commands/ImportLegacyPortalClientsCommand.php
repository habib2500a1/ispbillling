<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Import\LegacyPortalCustomerImporter;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacyPortalClientsCommand extends Command
{
    protected $signature = 'isp:import-legacy-portal
                            {--limit=10 : How many clients per page (max 500)}
                            {--start=0 : Offset in remote list}
                            {--all : Import every client from legacy portal (paginated)}
                            {--batch=100 : Page size when using --all}
                            {--force : Update existing customers with same customer_code}
                            {--query=alloverclients : legacy portal list filter (e.g. alloverclients)}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Import subscribers from legacy portal (pay.anetbd.com) via authenticated API';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));

        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env or pass --password=');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $query = (string) $this->option('query');
        $importAll = (bool) $this->option('all');
        $batch = max(1, min(500, (int) ($importAll ? $this->option('batch') : $this->option('limit'))));
        $start = max(0, (int) $this->option('start'));

        $this->info("Logging in to {$baseUrl} as {$username}…");

        $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $client->login();

        $this->info('Login OK — fetching client list…');

        $probe = $client->fetchCustomerPage(0, 1, $query);
        $total = $probe['iTotalDisplayRecords'];

        if ($total < 1) {
            $this->warn('No clients returned from legacy portal.');

            return self::FAILURE;
        }

        $this->info("Remote total: {$total} · query={$query}");

        $importer = new LegacyPortalCustomerImporter;
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $online = 0;
        $offline = 0;

        $offsets = $importAll
            ? range($start, max($start, $total - 1), $batch)
            : [$start];

        foreach ($offsets as $offset) {
            $length = $importAll ? min($batch, $total - $offset) : $batch;
            if ($length < 1) {
                break;
            }

            $page = $client->fetchCustomerPage($offset, $length, $query);
            $rows = $page['aaData'];

            if ($rows === []) {
                break;
            }

            $this->info('Batch '.($offset + 1).'–'.($offset + count($rows))." of {$total}…");

            foreach ($rows as $row) {
                $code = (string) ($row['CustomerId'] ?? '?');
                try {
                    $existed = Customer::query()->where('customer_code', $code)->exists();
                    $customer = $importer->importRow($row, $force);
                    if ($customer->isPppOnline()) {
                        $online++;
                    } else {
                        $offline++;
                    }
                    if ($existed && ! $force) {
                        $skipped++;
                    } else {
                        $imported++;
                    }
                } catch (Throwable $e) {
                    $failed++;
                    $this->error("  ✗ {$code} — {$e->getMessage()}");
                }
            }

            if (! $importAll) {
                break;
            }
        }

        $this->newLine();
        $this->table(['', 'Count'], [
            ['Imported/updated', $imported],
            ['Skipped (exists, no --force)', $skipped],
            ['Failed', $failed],
            ['PPP online snapshot', $online],
            ['PPP offline snapshot', $offline],
            ['In database now', Customer::query()->count()],
            ['View', '/admin/subscribers'],
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
