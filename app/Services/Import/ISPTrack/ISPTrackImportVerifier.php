<?php

namespace App\Services\Import\ISPTrack;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;

final class ISPTrackImportVerifier
{
    public function __construct(
        private readonly ISPTrackJsonLoader $loader,
    ) {}

    /**
     * @return list<array{metric: string, expected: int|string, actual: int|string, ok: string}>
     */
    public function run(ISPTrackImportContext $ctx, string $path): array
    {
        $data = $this->loader->load($path);

        $expectedClients = count($data['clients']);
        $actualClients = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('import_source', ISPTrackImportContext::IMPORT_SOURCE)
            ->count();

        $expectedBillings = count($data['billings']) + count($data['invoices']);
        $actualInvoices = Invoice::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('notes', 'like', '%ISPTrack%')
            ->count();

        $expectedPayments = count($data['payments']);
        $actualPayments = Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('notes', 'like', '%ISPTrack%')
            ->count();

        $suspended = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('import_source', ISPTrackImportContext::IMPORT_SOURCE)
            ->where('network_access_state', 'suspended')
            ->count();

        $openDue = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('import_source', ISPTrackImportContext::IMPORT_SOURCE)
            ->get()
            ->filter(fn (Customer $c): bool => $c->openInvoiceBalance() > 0.01)
            ->count();

        $rows = [
            ['metric' => 'clients', 'expected' => $expectedClients, 'actual' => $actualClients, 'ok' => $this->ok($expectedClients, $actualClients)],
            ['metric' => 'billings/invoices', 'expected' => $expectedBillings, 'actual' => $actualInvoices, 'ok' => $expectedBillings === 0 ? 'n/a' : $this->ok($expectedBillings, $actualInvoices)],
            ['metric' => 'payments', 'expected' => $expectedPayments, 'actual' => $actualPayments, 'ok' => $expectedPayments === 0 ? 'n/a' : $this->ok($expectedPayments, $actualPayments)],
            ['metric' => 'suspended_network', 'expected' => '—', 'actual' => $suspended, 'ok' => 'info'],
            ['metric' => 'customers_with_open_due', 'expected' => '—', 'actual' => $openDue, 'ok' => 'info'],
        ];

        foreach ($rows as $row) {
            if ($row['ok'] === 'yes' || $row['ok'] === 'info' || $row['ok'] === 'n/a') {
                $ctx->bump('verify_pass');
            } else {
                $ctx->bump('verify_warn');
            }
        }

        return $rows;
    }

    private function ok(int $expected, int $actual): string
    {
        if ($expected === 0) {
            return 'n/a';
        }

        return $actual >= $expected ? 'yes' : 'check';
    }
}
