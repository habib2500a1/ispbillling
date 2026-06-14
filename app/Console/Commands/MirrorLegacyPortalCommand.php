<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\LegacyPortalMirrorRun;
use App\Services\Import\LegacyPortalBillingImporter;
use App\Services\Import\LegacyPortalRawMirrorService;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;
use Throwable;

class MirrorLegacyPortalCommand extends Command
{
    protected $signature = 'isp:mirror-legacy-portal
                            {--query=alloverclients : legacy portal customer list filter}
                            {--batch=200 : DataTables page size}
                            {--with-customer-details : Mirror per-customer details HTML}
                            {--with-history : Mirror per-customer payment/invoice/SMS pages}
                            {--customer-limit=0 : Limit per-customer mirroring, 0 = all imported legacy customers}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Read-only raw mirror of pay.anetbd.com pages before normalized import/sync';

    public function handle(LegacyPortalRawMirrorService $mirror): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));
        $batch = max(25, min(500, (int) $this->option('batch')));

        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env or pass --password=');

            return self::FAILURE;
        }

        $run = $mirror->startRun($baseUrl, 'mirror', [
            'query' => $this->option('query'),
            'batch' => $batch,
            'with_customer_details' => (bool) $this->option('with-customer-details'),
            'with_history' => (bool) $this->option('with-history'),
            'customer_limit' => (int) $this->option('customer-limit'),
        ]);

        $this->info("Mirror run {$run->run_uuid}: logging in to {$baseUrl} as {$username}…");

        try {
            $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
            $client->login();

            $summary = [];
            $summary['customers'] = $this->mirrorPaged(
                $run,
                $mirror,
                'customers',
                fn (int $start, int $length): array => $client->fetchCustomerPage($start, $length, (string) $this->option('query')),
                $batch,
                '/Customer/AjaxCustomerList',
            );
            $summary['billing_grid'] = $this->mirrorPaged($run, $mirror, 'billing_grid', fn (int $start, int $length): array => $client->fetchCustomerBillListPage($start, $length), $batch, '/Billing/AjaxCustomerBillList');
            $summary['service_invoices'] = $this->mirrorPaged($run, $mirror, 'service_invoices', fn (int $start, int $length): array => $client->fetchServiceInvoicePage($start, $length), $batch, '/ServiceInvoice/AjaxInvoiceList');
            $summary['employees'] = $this->mirrorPaged($run, $mirror, 'employees', fn (int $start, int $length): array => $client->fetchEmployeesPage($start, $length), $batch, '/Employee/AjaxEmployees');
            $summary['application_users'] = $this->mirrorPaged($run, $mirror, 'application_users', fn (int $start, int $length): array => $client->fetchApplicationUsersPage($start, $length), $batch, '/ApplicationUsers/AjaxApplicationUsers');
            $summary['mac_resellers'] = $this->mirrorPaged($run, $mirror, 'mac_resellers', fn (int $start, int $length): array => $client->fetchMacResellersPage($start, $length), $batch, '/MACReseller/AjaxMACResellers');

            $kpi = $client->fetchBillingListOtherData();
            $mirror->record($run, 'billing_kpi', 'GET', rtrim($baseUrl, '/').'/Billing/GetBillingListOtherData', [], $kpi, 'billing-kpi');
            $summary['billing_kpi'] = 1;
            $summary['endpoint_probes'] = $this->mirrorEndpointProbes($run, $mirror, $client, $baseUrl);

            if ($this->option('with-customer-details') || $this->option('with-history')) {
                $summary['customer_pages'] = $this->mirrorCustomerPages($run, $mirror, $client, $baseUrl, $batch);
            }

            $mirror->finishRun($run, 'completed', $summary);
            $this->newLine();
            $this->table(['Domain', 'Remote Rows / Records'], collect($summary)->map(fn ($v, $k): array => [$k, is_array($v) ? json_encode($v) : $v])->values()->all());
            $this->info('Raw mirror complete. No local business data was changed.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $mirror->finishRun($run, 'failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  callable(int, int): array<string, mixed>  $fetcher
     */
    private function mirrorPaged(
        LegacyPortalMirrorRun $run,
        LegacyPortalRawMirrorService $mirror,
        string $domain,
        callable $fetcher,
        int $batch,
        string $path,
    ): int {
        $start = 0;
        $total = null;
        $seen = 0;

        do {
            $page = $fetcher($start, $batch);
            $rows = $page['aaData'] ?? $page['data'] ?? [];
            $total ??= (int) ($page['iTotalDisplayRecords'] ?? count($rows));
            $mirror->record(
                $run,
                $domain,
                'POST',
                rtrim($run->base_url, '/').$path,
                ['start' => $start, 'length' => $batch],
                $page,
                $domain.':'.$start,
            );

            $count = is_array($rows) ? count($rows) : 0;
            $seen += $count;
            $start += $batch;
        } while ($count > 0 && $start < $total);

        return $total ?? $seen;
    }

    /**
     * @return array<string, int>
     */
    private function mirrorCustomerPages(
        LegacyPortalMirrorRun $run,
        LegacyPortalRawMirrorService $mirror,
        LegacyPortalSessionClient $client,
        string $baseUrl,
        int $batch,
    ): array {
        $limit = (int) $this->option('customer-limit');
        $customers = (new LegacyPortalBillingImporter)->customersByLegacyHeaderId();
        if ($limit > 0) {
            $customers = $customers->take($limit);
        }

        $stats = ['details' => 0, 'payment_pages' => 0, 'invoice_pages' => 0, 'sms_pages' => 0, 'errors' => 0];
        $bar = $this->output->createProgressBar($customers->count());
        $bar->start();

        foreach ($customers as $headerId => $customer) {
            try {
                $sourceKey = 'customer:'.$customer->customer_code.':'.$headerId;
                if ($this->option('with-customer-details')) {
                    $html = $client->fetchCustomerDetailsHtml((int) $headerId);
                    $mirror->record($run, 'customer_details_html', 'GET', rtrim($baseUrl, '/').'/Customer/Details/'.$headerId, [], $html, $sourceKey, 200, 'text/html');
                    $stats['details']++;
                }
                if ($this->option('with-history')) {
                    $stats['payment_pages'] += $this->mirrorCustomerPaged($run, $mirror, 'payment_history', fn (int $start, int $length): array => $client->fetchPaymentHistoryPage((int) $headerId, $start, $length), (string) $sourceKey, $batch, '/Customer/AjaxReceivedHistory/'.$headerId);
                    $stats['invoice_pages'] += $this->mirrorCustomerPaged($run, $mirror, 'customer_product_invoices', fn (int $start, int $length): array => $client->fetchCustomerProductInvoicesPage((int) $headerId, $start, $length), (string) $sourceKey, $batch, '/Customer/AjaxServiceAndProductInvoices');
                    $stats['sms_pages'] += $this->mirrorCustomerPaged($run, $mirror, 'sms_history', fn (int $start, int $length): array => $client->fetchCustomerMessagesHistoryPage((int) $headerId, $start, $length), (string) $sourceKey, $batch, '/Customer/AjaxMessagesHistory/'.$headerId);
                }
            } catch (Throwable $e) {
                $stats['errors']++;
            }
            $bar->advance();
            usleep(50_000);
        }

        $bar->finish();
        $this->newLine();

        return $stats;
    }

    /**
     * Probe likely legacy modules not yet normalized, preserving whatever the old portal exposes.
     *
     * @return array<string, int>
     */
    private function mirrorEndpointProbes(
        LegacyPortalMirrorRun $run,
        LegacyPortalRawMirrorService $mirror,
        LegacyPortalSessionClient $client,
        string $baseUrl,
    ): array {
        $probes = [
            'tickets' => [
                ['GET', '/SupportTicket/Index', [], '/SupportTicket/Index'],
                ['POST', '/SupportTicket/AjaxSupportTickets', ['draw' => '1', 'start' => '0', 'length' => '200'], '/SupportTicket/Index'],
                ['GET', '/Ticket/Index', [], '/Ticket/Index'],
                ['POST', '/Ticket/AjaxTickets', ['draw' => '1', 'start' => '0', 'length' => '200'], '/Ticket/Index'],
                ['GET', '/Complaint/Index', [], '/Complaint/Index'],
                ['POST', '/Complaint/AjaxComplaints', ['draw' => '1', 'start' => '0', 'length' => '200'], '/Complaint/Index'],
            ],
            'zones' => [
                ['GET', '/Zone/Index', [], '/Zone/Index'],
                ['POST', '/Zone/AjaxZones', ['draw' => '1', 'start' => '0', 'length' => '200'], '/Zone/Index'],
                ['GET', '/Area/Index', [], '/Area/Index'],
                ['POST', '/Area/AjaxAreas', ['draw' => '1', 'start' => '0', 'length' => '200'], '/Area/Index'],
                ['GET', '/SubZone/Index', [], '/SubZone/Index'],
                ['POST', '/SubZone/AjaxSubZones', ['draw' => '1', 'start' => '0', 'length' => '200'], '/SubZone/Index'],
            ],
            'olt_onu' => [
                ['GET', '/OLT/Index', [], '/OLT/Index'],
                ['POST', '/OLT/AjaxOLTList', ['draw' => '1', 'start' => '0', 'length' => '200'], '/OLT/Index'],
                ['GET', '/ONU/Index', [], '/ONU/Index'],
                ['POST', '/ONU/AjaxONUList', ['draw' => '1', 'start' => '0', 'length' => '200'], '/ONU/Index'],
                ['GET', '/Network/Index', [], '/Network/Index'],
                ['POST', '/Network/AjaxNetworks', ['draw' => '1', 'start' => '0', 'length' => '200'], '/Network/Index'],
            ],
        ];

        $stats = [];
        foreach ($probes as $domain => $items) {
            $ok = 0;
            foreach ($items as [$method, $path, $payload, $referer]) {
                try {
                    $raw = $method === 'POST'
                        ? $client->fetchRawPost($path, $payload, rtrim($baseUrl, '/').$referer)
                        : $client->fetchRawGet($path, $payload, rtrim($baseUrl, '/').$referer);
                    $body = $raw['json'] ?? $raw['body'];
                    $mirror->record(
                        $run,
                        $domain,
                        $method,
                        rtrim($baseUrl, '/').$path,
                        $payload,
                        $body,
                        $domain.':'.$path,
                        $raw['status'],
                        $raw['content_type'],
                    );
                    if ($raw['status'] >= 200 && $raw['status'] < 400) {
                        $ok++;
                    }
                } catch (Throwable) {
                    // A missing module is still covered by the probe list; verification reports the gap.
                }
            }
            $stats[$domain] = $ok;
        }

        return $stats;
    }

    /**
     * @param  callable(int, int): array<string, mixed>  $fetcher
     */
    private function mirrorCustomerPaged(
        LegacyPortalMirrorRun $run,
        LegacyPortalRawMirrorService $mirror,
        string $domain,
        callable $fetcher,
        string $sourceKey,
        int $batch,
        string $path,
    ): int {
        $start = 0;
        $pages = 0;
        $total = null;

        do {
            $page = $fetcher($start, $batch);
            $rows = $page['aaData'] ?? $page['data'] ?? [];
            $total ??= (int) ($page['iTotalDisplayRecords'] ?? count($rows));
            $mirror->record($run, $domain, 'POST', rtrim($run->base_url, '/').$path, ['start' => $start, 'length' => $batch], $page, $sourceKey.':'.$start);
            $count = is_array($rows) ? count($rows) : 0;
            $pages++;
            $start += $batch;
        } while ($count > 0 && $start < $total);

        return $pages;
    }
}
