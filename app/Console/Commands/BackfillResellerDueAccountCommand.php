<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerLedgerEntry;
use App\Services\Resellers\ResellerDueLedgerService;
use App\Services\Resellers\ResellerHierarchicalBillingService;
use Illuminate\Console\Command;
class BackfillResellerDueAccountCommand extends Command
{
    protected $signature = 'isp:backfill-reseller-due-account
                            {--reseller= : Reseller ID (default: first active reseller)}
                            {--assign-orphans : Assign customers without reseller_id to this reseller}
                            {--invoices : Accrue HQ ledger from existing invoices}
                            {--payments : Apply customer payments to HQ ledger}
                            {--dry-run : Show counts only}';

    protected $description = 'Link subscribers to a reseller and backfill due-account ledger from existing invoices/payments';

    public function handle(
        ResellerDueLedgerService $ledger,
        ResellerHierarchicalBillingService $billing,
    ): int {
        $resellerId = (int) ($this->option('reseller') ?: Reseller::query()->orderBy('id')->value('id') ?: 0);
        $reseller = Reseller::query()->find($resellerId);
        if ($reseller === null) {
            $this->error('Reseller not found.');

            return self::FAILURE;
        }

        if (! $ledger->usesPostpaidDue($reseller)) {
            $this->warn("Reseller {$reseller->code} uses settlement mode «{$reseller->billing_settlement_mode}» — postpaid due ledger may be skipped.");
        }

        $dryRun = (bool) $this->option('dry-run');
        $doAssign = (bool) $this->option('assign-orphans');
        $doInvoices = (bool) $this->option('invoices');
        $doPayments = (bool) $this->option('payments');

        if (! $doAssign && ! $doInvoices && ! $doPayments) {
            $doAssign = $doInvoices = $doPayments = true;
        }

        $this->info("Reseller: {$reseller->name} ({$reseller->code}) #{$reseller->id}");

        if ($doAssign) {
            $orphanQuery = Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $reseller->tenant_id)
                ->whereNull('reseller_id');

            $toAssign = (clone $orphanQuery)->count();
            $this->line("Orphan subscribers (no reseller): {$toAssign}");

            if (! $dryRun && $toAssign > 0) {
                $updated = (clone $orphanQuery)->update(['reseller_id' => $reseller->id]);
                $this->info("Assigned {$updated} subscriber(s) to {$reseller->code}.");
            }
        }

        $customerIds = Customer::query()
            ->withoutGlobalScopes()
            ->where('reseller_id', $reseller->id)
            ->pluck('id');

        $this->line('Subscribers under reseller: '.$customerIds->count());

        if ($doInvoices) {
            $invoices = Invoice::query()
                ->withoutGlobalScopes()
                ->whereIn('customer_id', $customerIds)
                ->whereNotIn('status', ['void', 'cancelled'])
                ->where('total', '>', 0)
                ->orderBy('id')
                ->get();

            $this->line('Invoices to process: '.$invoices->count());

            $accrued = 0;
            $skipped = 0;

            foreach ($invoices as $invoice) {
                $ref = 'INV-ACCR-'.$invoice->id;
                if (ResellerLedgerEntry::query()->where('reseller_id', $reseller->id)->where('reference', $ref)->exists()) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $accrued++;

                    continue;
                }

                $billing->handleInvoiceCreated($invoice->fresh(['customer.reseller', 'items']));
                if (ResellerLedgerEntry::query()->where('reseller_id', $reseller->id)->where('reference', $ref)->exists()) {
                    $accrued++;
                }
            }

            $this->info($dryRun
                ? "Would accrue up to {$accrued} invoice(s) ({$skipped} already in ledger)."
                : "Accrued {$accrued} invoice(s); skipped {$skipped} existing.");
        }

        if ($doPayments) {
            $payments = Payment::query()
                ->withoutGlobalScopes()
                ->whereIn('customer_id', $customerIds)
                ->where('status', 'completed')
                ->where('amount', '>', 0)
                ->orderBy('id')
                ->get();

            $this->line('Payments to process: '.$payments->count());

            $applied = 0;
            foreach ($payments as $payment) {
                $ref = 'PAY-APPLY-'.$payment->id;
                if (ResellerLedgerEntry::query()->where('reseller_id', $reseller->id)->where('reference', $ref)->exists()) {
                    continue;
                }

                if ($dryRun) {
                    $applied++;

                    continue;
                }

                $billing->handlePaymentCompleted($payment->fresh(['customer.reseller', 'invoice']));
                if (ResellerLedgerEntry::query()->where('reseller_id', $reseller->id)->where('reference', $ref)->exists()) {
                    $applied++;
                }
            }

            $this->info($dryRun
                ? "Would apply up to {$applied} payment(s)."
                : "Applied {$applied} payment(s) to HQ ledger.");
        }

        if (! $dryRun) {
            $reseller->refresh();
            $breakdown = $ledger->customerDueBreakdown($reseller);
            $hq = $ledger->ledgerBreakdown($reseller);

            $this->table(
                ['Metric', 'BDT'],
                [
                    ['HQ due (stored)', number_format((float) $reseller->admin_receivable_due, 2)],
                    ['HQ calculated', number_format($hq['calculated_due'], 2)],
                    ['Customer invoiced', number_format($breakdown['invoiced'], 2)],
                    ['Customer collected', number_format($breakdown['collected'], 2)],
                    ['Customer due', number_format($breakdown['due'], 2)],
                    ['Ledger entries', (string) ResellerLedgerEntry::query()->where('reseller_id', $reseller->id)->count()],
                ],
            );
        }

        return self::SUCCESS;
    }
}
