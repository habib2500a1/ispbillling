<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Import\LegacyPortalBillingImporter;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AuditLegacyPortalImportCommand extends Command
{
    protected $signature = 'isp:audit-legacy-portal-import
                            {--sample=25 : How many subscribers to compare payment counts}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Compare legacy portal vs local counts (subscribers, staff, bills, ONU meta)';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));
        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $sampleSize = max(5, (int) $this->option('sample'));

        $this->info("Logging in to {$baseUrl} (separate sessions per API)…");

        $employeeClient = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $employeeClient->login();
        $remoteEmployees = $employeeClient->fetchEmployeesPage(0, 10)['iTotalDisplayRecords'];

        $customerClient = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $customerClient->login();
        $remoteCustomers = $customerClient->fetchCustomerPage(0, 10)['iTotalDisplayRecords'];

        $invoiceClient = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $invoiceClient->login();
        $serviceInvoices = $invoiceClient->fetchServiceInvoicePage(0, 10)['iTotalDisplayRecords'];

        $localCustomers = Customer::query()->fromLegacyPortal()->count();
        $localEmployees = Employee::query()->count();
        $localInvoices = Invoice::query()->count();
        $localPayments = Payment::query()->count();
        $onuMeta = Customer::query()
            ->fromLegacyPortal()
            ->where(function ($q): void {
                $q->whereNotNull('meta->onu_mac')
                    ->orWhereNotNull('meta->onu_rent')
                    ->orWhereNotNull('meta->legacy_portal_network');
            })
            ->count();
        $detailsSynced = Customer::query()
            ->fromLegacyPortal()
            ->whereNotNull('meta->legacy_portal_details_synced_at')
            ->count();

        $this->table(['Metric', 'legacy portal', 'Local', 'OK?'], [
            ['Subscribers', $remoteCustomers, $localCustomers, $remoteCustomers <= $localCustomers ? 'yes' : 'MISSING'],
            ['HR employees', $remoteEmployees, $localEmployees, $remoteEmployees <= $localEmployees ? 'yes' : 'MISSING'],
            ['Service invoices (remote list)', $serviceInvoices, $localInvoices, '—'],
            ['Payments (all)', '—', $localPayments, '—'],
            ['Details/ONU synced', '—', $detailsSynced.' / '.$localCustomers, $detailsSynced >= $localCustomers ? 'yes' : 'run isp:sync-legacy-portal-details'],
            ['With ONU/network meta', '—', $onuMeta, '—'],
        ]);

        $this->info("Sampling {$sampleSize} subscribers for payment count match…");
        $paymentClient = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $paymentClient->login();
        $importer = new LegacyPortalBillingImporter;
        /** @var Collection<int, Customer> $byHeader */
        $byHeader = $importer->customersByLegacyHeaderId();
        $sample = $byHeader->shuffle()->take($sampleSize);
        $mismatches = [];

        foreach ($sample as $headerId => $customer) {
            try {
                $remote = $paymentClient->fetchPaymentHistoryPage((int) $headerId, 0, 10)['iTotalDisplayRecords'];
            } catch (\Throwable) {
                continue;
            }
            $local = $customer->payments()->count();
            if ($remote !== $local) {
                $mismatches[] = [
                    'code' => $customer->customer_code,
                    'remote_payments' => $remote,
                    'local_payments' => $local,
                ];
            }
        }

        if ($mismatches === []) {
            $this->info('Payment counts match for sampled subscribers.');
        } else {
            $this->warn(count($mismatches).' payment mismatches in sample:');
            $this->table(['Code', 'Remote', 'Local'], array_map(
                fn (array $r) => [$r['code'], $r['remote_payments'], $r['local_payments']],
                array_slice($mismatches, 0, 15),
            ));
            $this->line('Re-run: php artisan isp:import-legacy-portal-billing --force');
        }

        return self::SUCCESS;
    }
}
