<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Automation\PrepaidWalletAutoSettleService;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\ScheduledPackageChangeService;
use App\Jobs\RunMonthlyBillingJob;
use App\Support\BillingCycleType;
use App\Support\QueueJobDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyBillsCommand extends Command
{
    protected $signature = 'isp:generate-bills
                            {--date= : Reference date (Y-m-d), default today}
                            {--customer= : Limit to one customer ID}
                            {--force : Ignore billing_day filter}
                            {--no-prorate : Charge full cycle even if customer joined mid-period}
                            {--coupon= : Apply coupon code to each new invoice}
                            {--cycle= : Only packages with this billing_cycle_type (hourly,daily,monthly,...)}
                            {--dry-run : Show actions without saving}
                            {--queued : Internal: skip re-queue}';

    protected $description = 'Auto-generate invoices: monthly/daily/hourly cycles, pro-rata, advance/postpaid due dates, VAT, coupons.';

    public function handle(ScheduledPackageChangeService $scheduledChanges): int
    {
        if (config('queue_ops.heavy_jobs_enabled', false) && ! $this->option('dry-run') && ! $this->option('queued')) {
            $opts = $this->options();
            $opts['--queued'] = true;
            QueueJobDispatcher::run(new RunMonthlyBillingJob($opts), fn () => $this->runBilling($scheduledChanges));
            $this->info('Bill generation queued.');

            return self::SUCCESS;
        }

        return $this->runBilling($scheduledChanges);
    }

    public function runBilling(ScheduledPackageChangeService $scheduledChanges): int
    {
        if (! $this->option('dry-run') && config('billing.downgrade_next_cycle', true)) {
            $applied = $scheduledChanges->applyDueChanges(null, false);
            if ($applied['applied'] > 0) {
                $this->info("Applied {$applied['applied']} scheduled package change(s).");
            }
        }

        $date = Carbon::parse($this->option('date') ?: now())->startOfDay();
        $cycleFilter = $this->option('cycle');

        $query = Customer::query()
            ->billable()
            ->with(['package.addons', 'package'])
            ->when($this->option('customer'), fn ($q) => $q->where('id', $this->option('customer')));

        if ($cycleFilter) {
            $query->whereHas('package', fn ($q) => $q->where('billing_cycle_type', $cycleFilter));
        }

        $created = 0;
        $skipped = 0;

        foreach ($query->cursor() as $customer) {
            $package = $customer->package;
            if (! $package) {
                continue;
            }

            if ($cycleFilter && ($package->billing_cycle_type ?? '') !== $cycleFilter) {
                continue;
            }

            if (! InvoiceGenerator::shouldBillOnDate($customer, $package, $date, (bool) $this->option('force'))) {
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->info("[dry-run] Would invoice #{$customer->id} ({$customer->name}) — ".($package->billing_cycle_type ?? 'monthly'));
                $created++;

                continue;
            }

            $invoice = InvoiceGenerator::generateForCustomer(
                $customer,
                $date,
                (bool) $this->option('no-prorate'),
                $this->option('coupon'),
            );

            if ($invoice === null) {
                $this->line("Skip #{$customer->id} — period already invoiced.");
                $skipped++;

                continue;
            }

            $this->info("Created {$invoice->invoice_number} for #{$customer->id} ({$package->billing_cycle_type})");
            $created++;
        }

        $this->info("Done. Created: {$created}, skipped: {$skipped}");

        if (! $this->option('dry-run')
            && $created > 0
            && config('billing.prepaid_wallet_auto_settle', true)
            && config('automation.prepaid_wallet_settle.enabled', true)) {
            $settle = app(PrepaidWalletAutoSettleService::class)->settleForTenant(null, false);
            $this->info(sprintf(
                'Prepaid wallet: applied %.2f BDT on %d invoice(s), %d renewed.',
                $settle['applied'],
                $settle['invoices'],
                $settle['renewed'],
            ));
        }

        return self::SUCCESS;
    }
}
