<?php

namespace App\Console\Commands;

use App\Services\Import\IspDigitalCustomerImporter;
use App\Services\Import\IspDigitalSessionClient;
use Illuminate\Console\Command;

class ImportIspDigitalClientsCommand extends Command
{
    protected $signature = 'isp:import-isp-digital
                            {--limit=10 : How many clients to import from ISP Digital}
                            {--start=0 : Offset in remote list}
                            {--force : Update existing customers with same customer_code}
                            {--url= : Override ISP_DIGITAL_URL}
                            {--user= : Override ISP_DIGITAL_USERNAME}
                            {--password= : Override ISP_DIGITAL_PASSWORD}';

    protected $description = 'Import subscribers from ISP Digital (pay.anetbd.com) via authenticated API';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('isp_digital.base_url'));
        $username = (string) ($this->option('user') ?: config('isp_digital.username'));
        $password = (string) ($this->option('password') ?: config('isp_digital.password'));

        if ($password === '') {
            $this->error('Set ISP_DIGITAL_PASSWORD in .env or pass --password=');

            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $start = max(0, (int) $this->option('start'));
        $force = (bool) $this->option('force');

        $this->info("Logging in to {$baseUrl} as {$username}…");

        $client = new IspDigitalSessionClient($baseUrl, $username, $password);
        $client->login();

        $this->info('Login OK — fetching client list…');

        $page = $client->fetchCustomerPage($start, $limit);
        $rows = $page['aaData'];
        $total = $page['iTotalDisplayRecords'];

        if ($rows === []) {
            $this->warn('No clients returned from ISP Digital.');

            return self::FAILURE;
        }

        $this->info("Remote total: {$total} · importing ".count($rows)." row(s)…");

        $importer = new IspDigitalCustomerImporter;
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code = (string) ($row['CustomerId'] ?? '?');
            try {
                $existed = \App\Models\Customer::query()->where('customer_code', $code)->exists();
                $customer = $importer->importRow($row, $force);
                if ($existed && ! $force) {
                    $skipped++;
                    $this->line("  · {$code} — exists (use --force to update)");
                } else {
                    $imported++;
                    $this->line("  ✓ {$code} — {$customer->name} ({$customer->phone})");
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ {$code} — {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->table(['', 'Count'], [
            ['Imported/updated', $imported],
            ['Skipped', $skipped],
            ['View', '/admin/subscribers'],
        ]);

        return self::SUCCESS;
    }
}
