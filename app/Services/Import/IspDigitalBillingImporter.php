<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class IspDigitalBillingImporter
{
    public function __construct(
        private readonly int $tenantId = 1,
    ) {}

    /**
     * @return array{invoices: int, payments: int, skipped: int, customers: int}
     */
    public function importAll(IspDigitalSessionClient $client, bool $force = false): array
    {
        $customers = $this->customersByLegacyHeaderId();
        $stats = ['invoices' => 0, 'payments' => 0, 'skipped' => 0, 'customers' => 0];

        foreach ($customers as $headerId => $customer) {
            $stats['customers']++;
            $stats = $this->mergeStats(
                $stats,
                $this->importCustomerPayments($client, $customer, (int) $headerId, $force),
            );
        }

        return $stats;
    }

    /**
     * @param  Collection<string, Customer>  $customers
     * @return array{invoices: int, payments: int, skipped: int}
     */
    public function importServiceInvoices(
        IspDigitalSessionClient $client,
        Collection $customers,
        bool $force = false,
    ): array {
        $stats = ['invoices' => 0, 'payments' => 0, 'skipped' => 0];
        $start = 0;
        $batch = 100;

        do {
            $page = $client->fetchServiceInvoicePage($start, $batch);
            $rows = $page['aaData'];
            $total = $page['iTotalDisplayRecords'];

            foreach ($rows as $row) {
                $headerId = (string) ($row['CustomerHeaderId'] ?? '');
                $customer = $customers->get($headerId);
                if ($customer === null) {
                    $stats['skipped']++;

                    continue;
                }

                $number = 'ISD-SINV-'.trim((string) ($row['InvoiceNo'] ?? $row['ServiceInvoiceId'] ?? ''));
                if ($number === 'ISD-SINV-' || (Invoice::query()->where('invoice_number', $number)->exists() && ! $force)) {
                    $stats['skipped']++;

                    continue;
                }

                $totalAmount = $this->parseMoney($row['TotalAmount'] ?? 0);
                $paid = $this->parseMoney($row['PaidAmount'] ?? 0);
                $issueDate = $this->parseDotNetDate($row['CreationDate'] ?? null)
                    ?? $this->parseFlexibleDate($row['CreationDateString'] ?? null)
                    ?? now();
                $dueDate = $this->parseDotNetDate($row['PaymentDueDate'] ?? null) ?? $issueDate->copy()->addDays(7);

                Invoice::withoutEvents(function () use ($customer, $number, $row, $totalAmount, $paid, $issueDate, $dueDate, $force): void {
                    $existing = Invoice::query()->where('invoice_number', $number)->first();
                    $attrs = [
                        'tenant_id' => $this->tenantId,
                        'customer_id' => $customer->id,
                        'issue_date' => $issueDate->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'period_start' => $issueDate->copy()->startOfMonth()->toDateString(),
                        'period_end' => $issueDate->copy()->endOfMonth()->toDateString(),
                        'subtotal' => $totalAmount,
                        'tax_amount' => 0,
                        'discount_amount' => $this->parseMoney($row['DiscountAmount'] ?? 0),
                        'total' => $totalAmount,
                        'amount_paid' => $paid,
                        'status' => $this->invoiceStatus($totalAmount, $paid),
                        'notes' => trim((string) (($row['Remarks'] ?? '') ?: 'ISP Digital service invoice #'.($row['ServiceInvoiceId'] ?? ''))),
                    ];

                    if ($existing !== null && $force) {
                        $existing->updateTrusted($attrs);
                    } elseif ($existing === null) {
                        $attrs['invoice_number'] = $number;
                        Invoice::createTrusted($attrs);
                    }
                });

                $stats['invoices']++;
            }

            $start += $batch;
        } while ($start < $total && $rows !== []);

        return $stats;
    }

    /**
     * @return array{invoices: int, payments: int, skipped: int}
     */
    public function importCustomerPayments(
        IspDigitalSessionClient $client,
        Customer $customer,
        int $customerHeaderId,
        bool $force = false,
    ): array {
        $stats = ['invoices' => 0, 'payments' => 0, 'skipped' => 0];
        $rows = $client->fetchPaymentHistory($customerHeaderId, 0, 500);

        foreach ($rows as $row) {
            if (filter_var($row['IsBillReceiveCanceled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $stats['skipped']++;

                continue;
            }

            $receipt = trim((string) ($row['MoneyReceiptNo'] ?? ''));
            if ($receipt === '') {
                $receipt = 'ISD-BH-'.(string) ($row['BillHeaderId'] ?? uniqid());
            }

            if (Payment::query()->where('receipt_number', $receipt)->exists() && ! $force) {
                $stats['skipped']++;

                continue;
            }

            $billMonth = trim((string) ($row['BillMonth'] ?? ''));
            $paidAt = $this->parseFlexibleDate($row['PaymentDate'] ?? $row['BillReceivedDate'] ?? null) ?? now();
            $amount = round((float) ($row['PaidAmount'] ?? 0), 2);
            $billTotal = round((float) ($row['PayabaleBill'] ?? $row['PaidAmount'] ?? 0), 2);

            if ($amount <= 0) {
                $stats['skipped']++;

                continue;
            }

            $invoice = $this->findOrCreateMonthlyInvoice($customer, $billMonth, $billTotal, $paidAt, $force);
            if ($invoice === null) {
                $stats['skipped']++;

                continue;
            }

            Payment::withoutEvents(function () use ($customer, $invoice, $row, $receipt, $amount, $paidAt, $force): void {
                $existing = Payment::query()->where('receipt_number', $receipt)->first();
                $attrs = [
                    'tenant_id' => $this->tenantId,
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'payment_type' => PaymentType::PAYMENT,
                    'amount' => $amount,
                    'method' => $this->mapPaymentMethod($row),
                    'reference' => filled($row['ReceiptOrTransactionNo'] ?? null)
                        ? (string) $row['ReceiptOrTransactionNo']
                        : null,
                    'notes' => trim((string) ($row['Remarks'] ?? '')),
                    'status' => 'completed',
                    'paid_at' => $paidAt,
                    'meta' => [
                        'import_source' => 'isp_digital',
                        'isp_digital_bill_header_id' => $row['BillHeaderId'] ?? null,
                        'isp_digital_bill_month' => $row['BillMonth'] ?? null,
                        'received_by' => $row['ReceivedBy'] ?? null,
                    ],
                ];

                if ($existing !== null && $force) {
                    $existing->updateTrusted($attrs);
                } elseif ($existing === null) {
                    $attrs['receipt_number'] = $receipt;
                    Payment::createTrusted($attrs);
                }
            });

            $this->refreshInvoicePaidTotal($invoice);
            $stats['payments']++;
        }

        return $stats;
    }

    /**
     * @return Collection<string, Customer>
     */
    public function customersByLegacyHeaderId(): Collection
    {
        return Customer::query()
            ->where('import_source', 'isp_digital')
            ->get()
            ->filter(fn (Customer $c): bool => filled($c->meta['legacy_id'] ?? null))
            ->keyBy(fn (Customer $c): string => (string) $c->meta['legacy_id']);
    }

    private function findOrCreateMonthlyInvoice(
        Customer $customer,
        string $billMonth,
        float $billTotal,
        Carbon $paidAt,
        bool $force,
    ): ?Invoice {
        $period = $this->parseBillMonth($billMonth);
        $suffix = $period?->format('Y-m') ?? $paidAt->format('Y-m');
        $number = 'ISD-'.$customer->customer_code.'-'.$suffix;

        $existing = Invoice::query()->where('invoice_number', $number)->first();
        if ($existing !== null) {
            return $existing;
        }

        $issueDate = $period ?? $paidAt->copy()->startOfMonth();
        $total = $billTotal > 0 ? $billTotal : (float) ($customer->package?->price_monthly ?? 0);
        if ($total <= 0) {
            $total = $billTotal;
        }

        return Invoice::withoutEvents(function () use ($customer, $number, $issueDate, $total, $billMonth): Invoice {
            return Invoice::createTrusted([
                'tenant_id' => $this->tenantId,
                'customer_id' => $customer->id,
                'invoice_number' => $number,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $issueDate->copy()->endOfMonth()->toDateString(),
                'period_start' => $issueDate->copy()->startOfMonth()->toDateString(),
                'period_end' => $issueDate->copy()->endOfMonth()->toDateString(),
                'subtotal' => $total,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => $total,
                'amount_paid' => 0,
                'status' => 'open',
                'notes' => $billMonth !== '' ? "ISP Digital bill month: {$billMonth}" : 'Imported from ISP Digital',
            ]);
        });
    }

    private function refreshInvoicePaidTotal(Invoice $invoice): void
    {
        $paid = (float) Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'completed')
            ->where('payment_type', PaymentType::PAYMENT)
            ->sum('amount');

        $total = (float) $invoice->total;
        $invoice->updateTrusted([
            'amount_paid' => round($paid, 2),
            'status' => $this->invoiceStatus($total, $paid),
        ]);
    }

    private function invoiceStatus(float $total, float $paid): string
    {
        if ($total <= 0 || $paid >= $total - 0.009) {
            return 'paid';
        }
        if ($paid > 0.009) {
            return 'partial';
        }

        return 'open';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapPaymentMethod(array $row): string
    {
        $short = strtolower(trim((string) ($row['PaymentMethodShortName'] ?? $row['PaymentMethodName'] ?? 'cash')));

        return match (true) {
            str_contains($short, 'bkash') => PaymentGateway::BKASH,
            str_contains($short, 'nagad') => PaymentGateway::NAGAD,
            str_contains($short, 'rocket') => PaymentGateway::ROCKET,
            str_contains($short, 'bank') => PaymentGateway::BANK,
            str_contains($short, 'cash') => PaymentGateway::CASH,
            default => PaymentGateway::OTHER,
        };
    }

    private function parseBillMonth(string $billMonth): ?Carbon
    {
        $billMonth = trim($billMonth);
        if ($billMonth === '') {
            return null;
        }

        if (preg_match('/^([A-Za-z]{3,})-(\d{2})$/i', $billMonth, $m)) {
            $year = 2000 + (int) $m[2];

            try {
                return Carbon::parse('1 '.$m[1].' '.$year)->startOfMonth();
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse($billMonth)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseMoney(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^\d.]/', '', (string) $value) ?? '';

        return round((float) ($clean !== '' ? $clean : 0), 2);
    }

    private function parseDotNetDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/\/Date\((\d+)\)\//', $value, $m)) {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $m[1]);
    }

    private function parseFlexibleDate(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        $value = trim((string) $value);
        foreach (['d M Y', 'd/m/Y', 'd-M-Y', 'M d, Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array{invoices: int, payments: int, skipped: int}  $a
     * @param  array{invoices: int, payments: int, skipped: int}  $b
     * @return array{invoices: int, payments: int, skipped: int, customers: int}
     */
    private function mergeStats(array $a, array $b): array
    {
        return [
            'invoices' => ($a['invoices'] ?? 0) + ($b['invoices'] ?? 0),
            'payments' => ($a['payments'] ?? 0) + ($b['payments'] ?? 0),
            'skipped' => ($a['skipped'] ?? 0) + ($b['skipped'] ?? 0),
            'customers' => $a['customers'] ?? 0,
        ];
    }
}
