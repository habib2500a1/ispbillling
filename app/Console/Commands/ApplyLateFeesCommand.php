<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Billing\LateFeeCalculator;
use App\Support\SubscriberType;
use Illuminate\Console\Command;

class ApplyLateFeesCommand extends Command
{
    protected $signature = 'isp:apply-late-fees
                            {--customer= : Limit to one customer ID}
                            {--dry-run : Show fees without saving}';

    protected $description = 'Add late fee line items to overdue invoices past grace period.';

    public function handle(): int
    {
        if (! config('billing.late_fees_enabled', true)) {
            $this->warn('Late fees disabled (BILLING_LATE_FEES_ENABLED=false).');

            return self::SUCCESS;
        }

        $q = Invoice::query()
            ->with('customer')
            ->whereIn('status', ['open', 'partial'])
            ->when($this->option('customer'), fn ($query) => $query->where('customer_id', $this->option('customer')));

        $applied = 0;
        foreach ($q->cursor() as $invoice) {
            $customer = $invoice->customer;
            if ($customer !== null && SubscriberType::skipsBilling((string) ($customer->subscriber_type ?? SubscriberType::STANDARD))) {
                continue;
            }

            $fee = LateFeeCalculator::calculateFee($invoice);
            if ($fee <= 0) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Invoice {$invoice->invoice_number}: late fee {$fee} BDT");
                $applied++;

                continue;
            }

            if (LateFeeCalculator::applyToInvoice($invoice)) {
                $this->info("Applied late fee {$fee} BDT to {$invoice->invoice_number}");
                $applied++;
            }
        }

        $this->info("Late fees applied: {$applied}");

        return self::SUCCESS;
    }
}
