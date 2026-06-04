<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Support\BillingDefaults;
use Illuminate\Console\Command;

class NormalizeBillingDayCommand extends Command
{
    protected $signature = 'isp:normalize-billing-day
                            {--day= : Bill day 1-28 (default from config)}
                            {--grace= : Set grace_period_days for all subscribers (e.g. 0 = due on bill day)}
                            {--enable-auto-invoice : Turn on meta.auto_invoice for all subscribers}';

    protected $description = 'Set every subscriber bill day to 1 (or --day=N) and optionally grace days so monthly bills and line-off rules align.';

    public function handle(): int
    {
        $day = $this->option('day') !== null
            ? max(1, min(28, (int) $this->option('day')))
            : BillingDefaults::billingDay();

        $updated = Customer::withoutGlobalScopes()->update(['billing_day' => $day]);
        $this->info("Set billing_day={$day} on {$updated} subscriber(s).");

        if ($this->option('grace') !== null) {
            $grace = max(0, min(90, (int) $this->option('grace')));
            $graceUpdated = Customer::withoutGlobalScopes()->update(['grace_period_days' => $grace]);
            $this->info("Set grace_period_days={$grace} on {$graceUpdated} subscriber(s).");
        }

        if ($this->option('enable-auto-invoice')) {
            $count = 0;
            Customer::withoutGlobalScopes()->orderBy('id')->each(function (Customer $customer) use (&$count): void {
                $meta = is_array($customer->meta) ? $customer->meta : [];
                if (($meta['auto_invoice'] ?? true) === true) {
                    return;
                }
                $meta['auto_invoice'] = true;
                $customer->forceFill(['meta' => $meta])->saveQuietly();
                $count++;
            });
            $this->info("Enabled auto_invoice on {$count} subscriber(s).");
        }

        $this->line('Monthly bills will generate on day '.$day.' via automatic process “Generate bills (monthly)”.');

        return self::SUCCESS;
    }
}
