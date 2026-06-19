<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Reseller;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Notifications\InvoiceOpsNotificationBatch;
use App\Support\CustomerStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class ResellerBulkInvoiceService
{
    /**
     * @return array{
     *     created: int,
     *     skipped: int,
     *     errors: list<string>,
     *     invoices: list<array{customer_code: string, invoice_number: string, total: float}>
     * }
     */
    public function generateForReseller(
        Reseller $reseller,
        ?CarbonInterface $referenceDate = null,
        bool $dryRun = false,
    ): array {
        if (! config('reseller_billing.portal_bulk_invoice_generate', true)) {
            throw ValidationException::withMessages([
                'billing' => 'Bulk bill generation is disabled. Contact admin.',
            ]);
        }

        $date = Carbon::parse($referenceDate ?? now())->startOfDay();
        $created = 0;
        $skipped = 0;
        $errors = [];
        $invoices = [];

        $query = Customer::query()
            ->withoutGlobalScopes()
            ->where('reseller_id', $reseller->id)
            ->where('status', CustomerStatus::ACTIVE)
            ->whereNotNull('package_id')
            ->with('package');

        $useDigest = ! $dryRun && (bool) config('notifications.events.invoice_created.telegram_ops_digest', true);
        if ($useDigest) {
            app(InvoiceOpsNotificationBatch::class)->start('Reseller bulk billing · '.$reseller->name);
        }

        try {
            foreach ($query->cursor() as $customer) {
                /** @var Customer $customer */
                if (! $customer->shouldGenerateInvoice()) {
                    $skipped++;

                    continue;
                }

                if ($customer->package === null) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    if ($this->wouldSkipExistingPeriod($customer, $date)) {
                        $skipped++;
                    } else {
                        $created++;
                    }

                    continue;
                }

                try {
                    $noProrate = app(ResellerCustomerBillingEngine::class)->shouldSkipProration($customer);
                    $invoice = InvoiceGenerator::generateForCustomer($customer, $date, $noProrate, null);

                    if ($invoice === null) {
                        $skipped++;

                        continue;
                    }

                    $created++;
                    $invoices[] = [
                        'customer_code' => (string) $customer->customer_code,
                        'invoice_number' => (string) $invoice->invoice_number,
                        'total' => round((float) $invoice->total, 2),
                    ];
                } catch (ValidationException $e) {
                    $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                    $errors[] = ($customer->customer_code ?? '#'.$customer->id).': '.$msg;
                } catch (\Throwable $e) {
                    $errors[] = ($customer->customer_code ?? '#'.$customer->id).': '.$e->getMessage();
                }
            }
        } finally {
            if ($useDigest) {
                app(InvoiceOpsNotificationBatch::class)->flush();
            }
        }

        if (! $dryRun && $created > 0) {
            app(ResellerPortalActivityLogger::class)->log($reseller, 'invoice.bulk_generate', null, [
                'created' => $created,
                'skipped' => $skipped,
                'reference_date' => $date->toDateString(),
            ]);
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'invoices' => $invoices,
        ];
    }

    public function countEligible(Reseller $reseller): int
    {
        return Customer::query()
            ->withoutGlobalScopes()
            ->where('reseller_id', $reseller->id)
            ->where('status', CustomerStatus::ACTIVE)
            ->whereNotNull('package_id')
            ->count();
    }

    private function wouldSkipExistingPeriod(Customer $customer, CarbonInterface $date): bool
    {
        $package = $customer->package;
        if ($package === null) {
            return true;
        }

        [$periodStart, $periodEnd] = \App\Services\Billing\BillingPeriodResolver::resolve($package, Carbon::parse($date));

        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['void', 'cancelled'])
            ->where(function ($q) use ($periodStart, $periodEnd, $date): void {
                $q->where(function ($q2) use ($periodStart, $periodEnd): void {
                    $q2->whereDate('period_start', $periodStart->toDateString())
                        ->whereDate('period_end', $periodEnd->toDateString());
                })->orWhere(function ($q2) use ($date): void {
                    $q2->whereYear('issue_date', $date->year)
                        ->whereMonth('issue_date', $date->month);
                });
            })
            ->exists();
    }
}
