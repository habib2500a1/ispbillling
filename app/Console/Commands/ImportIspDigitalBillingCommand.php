<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Import\IspDigitalBillingImporter;
use App\Services\Import\IspDigitalSessionClient;
use Illuminate\Console\Command;
use Throwable;

class ImportIspDigitalBillingCommand extends Command
{
    protected $signature = 'isp:import-isp-digital-billing
                            {--force : Re-import existing invoices/payments (by invoice_number / receipt_number)}
                            {--customer= : Only this customer_code (e.g. 0757)}
                            {--url= : Override ISP_DIGITAL_URL}
                            {--user= : Override ISP_DIGITAL_USERNAME}
                            {--password= : Override ISP_DIGITAL_PASSWORD}';

    protected $description = 'Import bill & payment history from ISP Digital (pay.anetbd.com)';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('isp_digital.base_url'));
        $username = (string) ($this->option('user') ?: config('isp_digital.username'));
        $password = (string) ($this->option('password') ?: config('isp_digital.password'));

        if ($password === '') {
            $this->error('Set ISP_DIGITAL_PASSWORD in .env or pass --password=');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $codeFilter = trim((string) $this->option('customer'));

        $this->info("Logging in to {$baseUrl}…");
        $client = new IspDigitalSessionClient($baseUrl, $username, $password);
        $client->login();

        $importer = new IspDigitalBillingImporter;
        $customers = $importer->customersByLegacyHeaderId();

        if ($codeFilter !== '') {
            $match = $customers->first(fn (Customer $c): bool => $c->customer_code === $codeFilter);
            if ($match === null) {
                $this->error("Customer {$codeFilter} not found (run isp:import-isp-digital first).");

                return self::FAILURE;
            }
            $headerId = (int) ($match->meta['legacy_id'] ?? 0);
            $this->info("Importing billing for {$match->name} ({$codeFilter})…");
            $stats = $importer->importCustomerPayments($client, $match, $headerId, $force);
            $invoiceClient = new IspDigitalSessionClient($baseUrl, $username, $password);
            $invoiceClient->login();
            $stats = array_merge(
                $stats,
                $importer->importServiceInvoices($invoiceClient, collect([(string) $headerId => $match]), $force),
            );
            $stats['customers'] = 1;
        } else {
            $this->info('Importing service invoices + collection history for '.$customers->count().' customers…');
            $bar = $this->output->createProgressBar($customers->count());
            $bar->start();

            $paymentStats = ['invoices' => 0, 'payments' => 0, 'skipped' => 0];

            $i = 0;
            foreach ($customers as $headerId => $customer) {
                if ($i > 0 && $i % 40 === 0) {
                    $client = new IspDigitalSessionClient($baseUrl, $username, $password);
                    $client->login();
                }
                $i++;

                try {
                    $row = $importer->importCustomerPayments($client, $customer, (int) $headerId, $force);
                    $paymentStats['payments'] += $row['payments'];
                    $paymentStats['skipped'] += $row['skipped'];
                } catch (Throwable $e) {
                    $this->newLine();
                    $this->warn("  {$customer->customer_code}: {$e->getMessage()}");
                    if (str_contains($e->getMessage(), '431') || str_contains($e->getMessage(), '400')) {
                        $client = new IspDigitalSessionClient($baseUrl, $username, $password);
                        $client->login();
                    }
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            $this->info('Importing service invoices (separate session)…');
            $invoiceClient = new IspDigitalSessionClient($baseUrl, $username, $password);
            $invoiceClient->login();
            $invoiceStats = $importer->importServiceInvoices($invoiceClient, $customers, $force);

            $stats = [
                'invoices' => $invoiceStats['invoices'],
                'payments' => $paymentStats['payments'],
                'skipped' => $paymentStats['skipped'] + $invoiceStats['skipped'],
                'customers' => $customers->count(),
            ];
        }

        $this->newLine();
        $this->table(['', 'Count'], [
            ['Customers scanned', $stats['customers'] ?? 0],
            ['Invoices imported', $stats['invoices'] ?? 0],
            ['Payments imported', $stats['payments'] ?? 0],
            ['Skipped (duplicate/canceled)', $stats['skipped'] ?? 0],
            ['View', '/admin/subscribers'],
        ]);

        return self::SUCCESS;
    }
}
