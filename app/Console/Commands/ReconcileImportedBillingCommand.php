<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\CustomerBalanceDue;
use App\Support\PaymentType;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReconcileImportedBillingCommand extends Command
{
    protected $signature = 'isp:reconcile-imported-billing
                            {--customer= : Only this customer_code}
                            {--dry-run : Report only, do not change data}';

    protected $description = 'Fix duplicate monthly invoices and refresh paid amounts after legacy portal import';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $codeFilter = trim((string) $this->option('customer'));

        $this->info('Refreshing invoice paid totals from payments…');
        $refreshed = $this->refreshAllInvoicePaidTotals($dryRun);

        $this->info('Voiding duplicate local monthly invoices (RCL/INV vs imported ISD)…');
        $voidedLocalDupes = $this->voidLocalDuplicateMonthlyInvoices($codeFilter, $dryRun);

        $this->info('Voiding duplicate open invoices (same month already paid)…');
        $voided = $this->voidDuplicateOpenInvoices($codeFilter, $dryRun);

        if (! $dryRun) {
            $this->info('Refreshing subscriber due balances…');
            $n = 0;
            $query = Customer::query()->fromLegacyPortal();
            if ($codeFilter !== '') {
                $query->where('customer_code', $codeFilter);
            }
            $query->orderBy('id')->chunkById(100, function ($chunk) use (&$n): void {
                foreach ($chunk as $customer) {
                    CustomerBalanceDue::refreshMetaAfterPayment($customer);
                    $n++;
                }
            });
            $this->line("Due meta refreshed for {$n} subscribers.");
        }

        $this->newLine();
        $this->table(['Action', 'Count'], [
            ['Invoices payment totals refreshed', $refreshed],
            ['Local duplicate monthly invoices voided', $voidedLocalDupes],
            ['Duplicate open invoices voided', $voided],
            ['Open invoices (now)', Invoice::query()->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)->count()],
        ]);

        if ($codeFilter !== '') {
            $c = Customer::query()->where('customer_code', $codeFilter)->first();
            if ($c) {
                $this->dumpCustomer($c);
            }
        }

        return self::SUCCESS;
    }

    private function refreshAllInvoicePaidTotals(bool $dryRun): int
    {
        $count = 0;
        Invoice::query()->orderBy('id')->chunkById(200, function ($invoices) use ($dryRun, &$count): void {
            foreach ($invoices as $invoice) {
                $paid = (float) Payment::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('status', 'completed')
                    ->where('payment_type', PaymentType::PAYMENT)
                    ->sum('amount');

                $total = (float) $invoice->total;
                $status = $this->invoiceStatus($total, $paid);

                if (abs((float) $invoice->amount_paid - $paid) < 0.01 && $invoice->status === $status) {
                    continue;
                }

                $count++;
                if (! $dryRun) {
                    $invoice->updateTrusted([
                        'amount_paid' => round($paid, 2),
                        'status' => $status,
                    ]);
                }
            }
        });

        return $count;
    }

    /**
     * Void auto-generated monthly bills (e.g. RCL-*) when legacy portal import (ISD-*) exists for the same month.
     */
    private function voidLocalDuplicateMonthlyInvoices(string $codeFilter, bool $dryRun): int
    {
        $voided = 0;
        $localPrefix = rtrim((string) config('billing.invoice_number_prefix', 'INV'), '-').'-';

        $query = Customer::query()->fromLegacyPortal();
        if ($codeFilter !== '') {
            $query->where('customer_code', $codeFilter);
        }

        $query->with(['invoices'])->chunkById(100, function ($customers) use ($dryRun, &$voided, $localPrefix): void {
            foreach ($customers as $customer) {
                $byMonth = $customer->invoices->groupBy(
                    fn (Invoice $i): string => ($i->issue_date ?? $i->created_at)?->format('Y-m') ?? 'unknown',
                );

                foreach ($byMonth as $invoices) {
                    $imported = $invoices->filter(fn (Invoice $i): bool => str_starts_with(
                        (string) $i->invoice_number,
                        'ISD-',
                    ));

                    if ($imported->isEmpty()) {
                        continue;
                    }

                    $localOpen = $invoices->filter(fn (Invoice $i): bool => str_starts_with((string) $i->invoice_number, $localPrefix)
                        && in_array($i->status, CustomerBalanceDue::OPEN_INVOICE_STATUSES, true)
                        && ! Payment::query()
                            ->where('invoice_id', $i->id)
                            ->where('status', 'completed')
                            ->exists());

                    foreach ($localOpen as $openInv) {
                        $voided++;
                        if (! $dryRun) {
                            $openInv->updateTrusted([
                                'status' => 'void',
                                'notes' => trim(($openInv->notes ?? '').' · Voided duplicate local bill (legacy portal ISD exists)'),
                            ]);
                        }
                    }
                }
            }
        });

        return $voided;
    }

    private function voidDuplicateOpenInvoices(string $codeFilter, bool $dryRun): int
    {
        $voided = 0;
        $query = Customer::query()->fromLegacyPortal();
        if ($codeFilter !== '') {
            $query->where('customer_code', $codeFilter);
        }

        $query->with(['invoices'])->chunkById(100, function ($customers) use ($dryRun, &$voided): void {
            foreach ($customers as $customer) {
                $byMonth = $customer->invoices->groupBy(
                    fn (Invoice $i): string => ($i->issue_date ?? $i->created_at)?->format('Y-m') ?? 'unknown',
                );

                foreach ($byMonth as $invoices) {
                    if ($invoices->count() < 2) {
                        continue;
                    }

                    $paid = $invoices->filter(fn (Invoice $i): bool => $i->status === 'paid'
                        || (float) $i->amount_paid >= (float) $i->total - 0.01);

                    $open = $invoices->filter(fn (Invoice $i): bool => in_array(
                        $i->status,
                        CustomerBalanceDue::OPEN_INVOICE_STATUSES,
                        true,
                    ));

                    if ($paid->isEmpty() || $open->isEmpty()) {
                        continue;
                    }

                    foreach ($open as $openInv) {
                        $hasPayment = Payment::query()
                            ->where('invoice_id', $openInv->id)
                            ->where('status', 'completed')
                            ->exists();

                        if ($hasPayment) {
                            continue;
                        }

                        $matchesPaidMonth = $paid->contains(function (Invoice $p) use ($openInv): bool {
                            return abs((float) $p->total - (float) $openInv->total) < 1.0;
                        });

                        if (! $matchesPaidMonth) {
                            continue;
                        }

                        $voided++;
                        if (! $dryRun) {
                            $openInv->updateTrusted([
                                'status' => 'void',
                                'notes' => trim(($openInv->notes ?? '').' · Voided duplicate after legacy portal reconcile'),
                            ]);
                        }
                    }
                }
            }
        });

        return $voided;
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

    private function dumpCustomer(Customer $customer): void
    {
        $open = CustomerBalanceDue::invoiceBalanceDue($customer);
        $this->line("{$customer->customer_code} open due: {$open} BDT");
        foreach ($customer->invoices()->orderBy('issue_date')->get() as $inv) {
            $this->line("  {$inv->invoice_number} {$inv->status} total={$inv->total} paid={$inv->amount_paid}");
        }
    }
}
