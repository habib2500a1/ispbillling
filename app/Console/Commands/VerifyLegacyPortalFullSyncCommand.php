<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LegacyPortalMirrorRun;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Reseller;
use App\Services\Import\LegacyPortalBillingImporter;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class VerifyLegacyPortalFullSyncCommand extends Command
{
    protected $signature = 'isp:verify-legacy-portal-full-sync
                            {--sample=25 : Customers to sample for payment/SMS history}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Verify pay.anetbd.com full mirror/import coverage before enabling continuous sync';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));
        $sampleSize = max(1, (int) $this->option('sample'));

        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env or pass --password=');

            return self::FAILURE;
        }

        $this->info("Logging in to {$baseUrl} for source count verification…");
        $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $client->login();

        $remote = [
            'customers' => $client->fetchCustomerPage(0, 10)['iTotalDisplayRecords'],
            'employees' => $client->fetchEmployeesPage(0, 10)['iTotalDisplayRecords'],
            'billing_grid' => $client->fetchCustomerBillListPage(0, 10)['iTotalDisplayRecords'],
            'service_invoices' => $client->fetchServiceInvoicePage(0, 10)['iTotalDisplayRecords'],
            'mac_resellers' => $client->fetchMacResellersPage(0, 10)['iTotalDisplayRecords'],
            'application_users' => $client->fetchApplicationUsersPage(0, 10)['iTotalDisplayRecords'],
        ];

        $local = [
            'customers' => Customer::query()->fromLegacyPortal()->count(),
            'employees' => Employee::query()->count(),
            'payments' => Payment::query()->where('meta->import_source', 'legacy_portal')->count(),
            'invoices' => Invoice::query()->count(),
            'sms_logs' => NotificationLog::query()->where('meta->import_source', 'legacy_portal')->orWhereNotNull('meta->legacy_portal_sms_log_id')->count(),
            'mac_resellers' => Reseller::query()->where('meta->import_source', 'legacy_portal')->orWhere('meta->source', 'legacy_portal')->count(),
            'details_synced' => Customer::query()->fromLegacyPortal()->whereNotNull('meta->legacy_portal_details_synced_at')->count(),
            'with_onu_meta' => Customer::query()->fromLegacyPortal()->where(function ($q): void {
                $q->whereNotNull('meta->onu_mac')
                    ->orWhereNotNull('meta->legacy_portal_network')
                    ->orWhereNotNull('meta->mac_binding')
                    ->orWhereNotNull('meta->epon_port');
            })->count(),
        ];

        $latestMirror = LegacyPortalMirrorRun::query()
            ->where('base_url', rtrim($baseUrl, '/'))
            ->latest('id')
            ->first();

        $mirrorCounts = [];
        if ($latestMirror !== null) {
            $mirrorCounts = $latestMirror->records()
                ->selectRaw('domain, count(*) as aggregate')
                ->groupBy('domain')
                ->pluck('aggregate', 'domain')
                ->map(fn ($v): int => (int) $v)
                ->all();
        }

        $rows = [
            ['Subscribers', $remote['customers'], $local['customers'], $this->ok($local['customers'] >= $remote['customers'])],
            ['Employees/staff', $remote['employees'], $local['employees'], $this->ok($local['employees'] >= $remote['employees'])],
            ['Billing grid rows', $remote['billing_grid'], $mirrorCounts['billing_grid'] ?? 0, $latestMirror ? 'mirrored pages' : 'NO MIRROR'],
            ['Service invoices', $remote['service_invoices'], $local['invoices'], $local['invoices'] > 0 ? 'check sample' : 'MISSING'],
            ['MAC resellers', $remote['mac_resellers'], $local['mac_resellers'], $this->ok($local['mac_resellers'] >= $remote['mac_resellers'])],
            ['Application users', $remote['application_users'], $mirrorCounts['application_users'] ?? 0, $latestMirror ? 'mirrored pages' : 'NO MIRROR'],
            ['SMS logs imported', 'sampled per customer', $local['sms_logs'], $local['sms_logs'] > 0 ? 'sample' : 'not seen yet'],
            ['Details synced', $remote['customers'], $local['details_synced'], $this->ok($local['details_synced'] >= $remote['customers'])],
            ['ONU/network meta', 'available details', $local['with_onu_meta'], $local['with_onu_meta'] > 0 ? 'present' : 'not found'],
            ['Raw mirror run', $latestMirror?->run_uuid ?? 'none', $latestMirror?->status ?? 'none', $latestMirror ? 'OK' : 'RUN MIRROR'],
        ];
        $this->table(['Metric', 'Source', 'Local / Mirror', 'Status'], $rows);

        if ($latestMirror !== null) {
            $expectedDomains = [
                'customers', 'billing_grid', 'service_invoices', 'employees', 'application_users',
                'mac_resellers', 'billing_kpi', 'tickets', 'zones', 'olt_onu',
            ];
            $this->table(['Raw mirror domain', 'Records', 'Status'], array_map(
                fn (string $domain): array => [$domain, $mirrorCounts[$domain] ?? 0, ($mirrorCounts[$domain] ?? 0) > 0 ? 'captured/probed' : 'MISSING'],
                $expectedDomains,
            ));

            $fieldSummary = $this->mirrorFieldSummary($latestMirror);
            if ($fieldSummary !== []) {
                $this->table(['Raw domain', 'Top-level source keys'], $fieldSummary);
            }
        }

        $mismatches = $this->sampleCustomerHistories($client, $sampleSize);
        if ($mismatches === []) {
            $this->info("Sample verification passed for {$sampleSize} customer(s).");
        } else {
            $this->warn('Sample mismatches:');
            $this->table(['Customer', 'Field', 'Source', 'Local'], $mismatches);
        }

        $hardFailures = [];
        if ($local['customers'] < $remote['customers']) {
            $hardFailures[] = 'customers';
        }
        if ($local['mac_resellers'] < $remote['mac_resellers']) {
            $hardFailures[] = 'mac_resellers';
        }
        if ($local['details_synced'] < $remote['customers']) {
            $hardFailures[] = 'details_synced';
        }
        if ($latestMirror === null) {
            $hardFailures[] = 'raw_mirror_missing';
        }
        if ($latestMirror !== null && ($mirrorCounts['customers'] ?? 0) < 1) {
            $hardFailures[] = 'raw_customers_missing';
        }
        if ($mismatches !== []) {
            $hardFailures[] = 'sample_mismatch';
        }

        if ($hardFailures !== []) {
            $this->error('Verification failed: '.implode(', ', $hardFailures));

            return self::FAILURE;
        }

        $this->info('Verification passed. Continuous sync can remain enabled.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function mirrorFieldSummary(LegacyPortalMirrorRun $run): array
    {
        return $run->records()
            ->whereNotNull('payload_json')
            ->whereIn('domain', ['customers', 'billing_grid', 'service_invoices', 'payment_history', 'sms_history', 'mac_resellers', 'tickets', 'zones', 'olt_onu'])
            ->orderBy('domain')
            ->limit(20)
            ->get()
            ->map(function ($record): array {
                $payload = $record->payload_json ?? [];
                $rows = $payload['aaData'] ?? $payload['data'] ?? null;
                $firstRow = is_array($rows) ? ($rows[0] ?? []) : [];
                $keys = array_keys(is_array($firstRow) ? $firstRow : (is_array($payload) ? $payload : []));

                return [
                    (string) $record->domain,
                    implode(', ', array_slice($keys, 0, 18)) ?: '(html/non-tabular)',
                ];
            })
            ->unique(fn (array $row): string => $row[0].':'.$row[1])
            ->values()
            ->all();
    }

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: int}>
     */
    private function sampleCustomerHistories(LegacyPortalSessionClient $client, int $sampleSize): array
    {
        $mismatches = [];
        /** @var Collection<int, Customer> $customers */
        $customers = (new LegacyPortalBillingImporter)->customersByLegacyHeaderId()->shuffle()->take($sampleSize);

        foreach ($customers as $headerId => $customer) {
            try {
                $remotePayments = $client->fetchPaymentHistoryPage((int) $headerId, 0, 10)['iTotalDisplayRecords'];
                $localPayments = $customer->payments()->count();
                if ($localPayments < $remotePayments) {
                    $mismatches[] = [$customer->customer_code, 'payments', $remotePayments, $localPayments];
                }

                $remoteSms = $client->fetchCustomerMessagesHistoryPage((int) $headerId, 0, 10)['iTotalDisplayRecords'];
                $localSms = $customer->notificationLogs()->where(function ($q): void {
                    $q->where('meta->import_source', 'legacy_portal')
                        ->orWhereNotNull('meta->legacy_portal_sms_log_id');
                })->count();
                if ($localSms < $remoteSms) {
                    $mismatches[] = [$customer->customer_code, 'sms', $remoteSms, $localSms];
                }
            } catch (Throwable $e) {
                $mismatches[] = [$customer->customer_code, 'sample_error', 1, 0];
            }
        }

        return $mismatches;
    }

    private function ok(bool $ok): string
    {
        return $ok ? 'OK' : 'MISSING';
    }
}
