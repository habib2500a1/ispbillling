<?php

namespace App\Services\Import\ISPTrack;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PaymentType;
use Carbon\Carbon;

final class ISPTrackBillingImporter
{
    public function __construct(
        private readonly ISPTrackJsonLoader $loader,
    ) {}

    /**
     * @return array<string, int>
     */
    public function run(ISPTrackImportContext $ctx, string $path): array
    {
        $data = $this->loader->load($path);

        foreach ($data['billings'] as $row) {
            $this->importBilling($ctx, $row);
        }

        foreach ($data['invoices'] as $row) {
            $this->importInvoiceRow($ctx, $row);
        }

        foreach ($data['payments'] as $row) {
            $this->importPayment($ctx, $row);
        }

        return $ctx->stats();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importBilling(ISPTrackImportContext $ctx, array $row): void
    {
        $customer = $this->resolveCustomer($ctx, $row);
        if ($customer === null) {
            $ctx->bump('billings_skipped');

            return;
        }

        $number = ISPTrackJsonLoader::str($row, 'invoice_number', 'billing_number');
        if ($number === '') {
            $oldId = ISPTrackJsonLoader::int($row, 'id');
            $number = 'IT-BILL-'.($oldId ?? uniqid());
        } else {
            $number = 'IT-'.$number;
        }

        $existing = Invoice::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('invoice_number', $number)
            ->first();

        if ($existing !== null && ! $ctx->force) {
            $ctx->mapId($ctx->billingMap, ISPTrackJsonLoader::int($row, 'id'), (int) $existing->id);
            $ctx->bump('billings_skipped');

            return;
        }

        if ($ctx->dryRun) {
            $ctx->bump('billings_would_import');

            return;
        }

        $total = ISPTrackJsonLoader::num($row, 'total_amount', 'amount', 'total');
        $discount = ISPTrackJsonLoader::num($row, 'discount', 'discount_amount');
        $tax = ISPTrackJsonLoader::num($row, 'tax', 'tax_amount');
        $paid = $this->resolvePaidAmount($row, $total);
        $issueDate = $this->parseDate($row['billing_date'] ?? $row['issue_date'] ?? null) ?? now();
        $dueDate = $this->parseDate($row['due_date'] ?? null) ?? $issueDate->copy()->addDays(7);

        $attrs = [
            'tenant_id' => $ctx->tenantId,
            'customer_id' => $customer->id,
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'period_start' => $issueDate->copy()->startOfMonth()->toDateString(),
            'period_end' => $issueDate->copy()->endOfMonth()->toDateString(),
            'subtotal' => max(0, $total - $tax),
            'tax_amount' => $tax,
            'discount_amount' => $discount,
            'total' => $total,
            'amount_paid' => $paid,
            'status' => $this->invoiceStatus($total, $paid, ISPTrackJsonLoader::str($row, 'status')),
            'notes' => ISPTrackJsonLoader::str($row, 'notes') ?: 'Imported from ISPTrack',
        ];

        $invoice = Invoice::withoutEvents(function () use ($existing, $attrs, $number, $ctx): Invoice {
            if ($existing !== null && $ctx->force) {
                return $existing->updateTrusted($attrs);
            }

            $attrs['invoice_number'] = $number;

            return Invoice::createTrusted($attrs);
        });

        $ctx->mapId($ctx->billingMap, ISPTrackJsonLoader::int($row, 'id'), (int) $invoice->id);
        $ctx->bump($existing !== null && $ctx->force ? 'billings_updated' : 'billings_created');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importInvoiceRow(ISPTrackImportContext $ctx, array $row): void
    {
        if ($this->hasBillingShape($row)) {
            $this->importBilling($ctx, $row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importPayment(ISPTrackImportContext $ctx, array $row): void
    {
        $customer = $this->resolveCustomer($ctx, $row);
        if ($customer === null) {
            $ctx->bump('payments_skipped');

            return;
        }

        $reference = ISPTrackJsonLoader::str($row, 'transaction_id', 'payment_reference', 'reference');
        if ($reference === '') {
            $reference = 'IT-PAY-'.(ISPTrackJsonLoader::int($row, 'id') ?? uniqid());
        }

        $existing = Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('reference', $reference)
            ->exists();

        if ($existing && ! $ctx->force) {
            $ctx->bump('payments_skipped');

            return;
        }

        if ($ctx->dryRun) {
            $ctx->bump('payments_would_import');

            return;
        }

        $invoiceId = $this->resolveInvoiceId($ctx, $row);
        $amount = ISPTrackJsonLoader::num($row, 'amount');
        $paidAt = $this->parseDate($row['payment_date'] ?? $row['paid_at'] ?? null) ?? now();

        Payment::withoutEvents(function () use ($ctx, $customer, $invoiceId, $amount, $reference, $row, $paidAt, $existing): void {
            $attrs = [
                'tenant_id' => $ctx->tenantId,
                'customer_id' => $customer->id,
                'invoice_id' => $invoiceId,
                'payment_type' => PaymentType::PAYMENT,
                'amount' => $amount,
                'method' => $this->mapPaymentMethod(ISPTrackJsonLoader::str($row, 'payment_method', 'method')),
                'reference' => $reference,
                'status' => strtolower(ISPTrackJsonLoader::str($row, 'status') ?: 'completed') === 'failed' ? 'failed' : 'completed',
                'paid_at' => $paidAt,
                'notes' => ISPTrackJsonLoader::str($row, 'notes') ?: 'Imported from ISPTrack',
                'meta' => ['import_source' => ISPTrackImportContext::IMPORT_SOURCE],
            ];

            if ($existing) {
                Payment::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $ctx->tenantId)
                    ->where('reference', $reference)
                    ->first()
                    ?->updateTrusted($attrs);
            } else {
                Payment::createTrusted($attrs);
            }
        });

        $ctx->bump($existing && $ctx->force ? 'payments_updated' : 'payments_created');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveCustomer(ISPTrackImportContext $ctx, array $row): ?Customer
    {
        $clientOldId = ISPTrackJsonLoader::int($row, 'client_id', 'customer_id');
        $mappedId = $ctx->resolveMapped($ctx->customerMap, $clientOldId);
        if ($mappedId !== null) {
            return Customer::query()->withoutGlobalScopes()->find($mappedId);
        }

        $code = ISPTrackJsonLoader::str($row, 'client_code', 'customer_code', 'client_id');
        if ($code !== '' && ! is_numeric($code)) {
            return Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $ctx->tenantId)
                ->where('customer_code', $code)
                ->first();
        }

        if ($clientOldId !== null) {
            return Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $ctx->tenantId)
                ->where('import_source', ISPTrackImportContext::IMPORT_SOURCE)
                ->where('meta->isptrack_id', $clientOldId)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveInvoiceId(ISPTrackImportContext $ctx, array $row): ?int
    {
        $billingOldId = ISPTrackJsonLoader::int($row, 'billing_id', 'invoice_id');
        $mapped = $ctx->resolveMapped($ctx->billingMap, $billingOldId);
        if ($mapped !== null) {
            return $mapped;
        }

        $number = ISPTrackJsonLoader::str($row, 'invoice_number', 'billing_number');
        if ($number === '') {
            return null;
        }

        $id = Invoice::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('invoice_number', 'IT-'.$number)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolvePaidAmount(array $row, float $total): float
    {
        $paid = ISPTrackJsonLoader::num($row, 'paid_amount', 'amount_paid');
        if ($paid > 0) {
            return $paid;
        }

        $status = strtolower(ISPTrackJsonLoader::str($row, 'status'));

        return in_array($status, ['paid'], true) ? $total : 0.0;
    }

    private function invoiceStatus(float $total, float $paid, string $rawStatus): string
    {
        $raw = strtolower($rawStatus);
        if (in_array($raw, ['paid'], true) || ($total > 0 && $paid >= $total - 0.01)) {
            return 'paid';
        }
        if (in_array($raw, ['partially_paid', 'partial'], true) || ($paid > 0.01 && $paid < $total - 0.01)) {
            return 'partial';
        }
        if (in_array($raw, ['overdue'], true)) {
            return 'overdue';
        }

        return 'open';
    }

    private function mapPaymentMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match (true) {
            str_contains($method, 'bkash') => 'bkash',
            str_contains($method, 'nagad') => 'nagad',
            str_contains($method, 'rocket') => 'rocket',
            str_contains($method, 'bank') => 'bank',
            str_contains($method, 'card') => 'card',
            default => $method !== '' ? $method : 'cash',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasBillingShape(array $row): bool
    {
        return ISPTrackJsonLoader::str($row, 'invoice_number', 'billing_number') !== ''
            || ISPTrackJsonLoader::num($row, 'total_amount', 'amount', 'total') > 0;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
