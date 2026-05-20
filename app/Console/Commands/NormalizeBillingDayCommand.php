<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Support\BillingDefaults;
use Illuminate\Console\Command;

class NormalizeBillingDayCommand extends Command
{
    protected $signature = 'isp:normalize-billing-day
                            {--day= : Bill day 1-28 (default from config)}
                            {--enable-auto-invoice : Turn on meta.auto_invoice for all subscribers}';

    protected $description = 'Set every subscriber bill day to 1 (or --day=N) so monthly bills run on the same date regardless of join date or due.';

    public function handle(): int
    {
        $day = $this->option('day') !== null
            ? max(1, min(28, (int) $this->option('day')))
            : BillingDefaults::billingDay();

        $updated = Customer::withoutGlobalScopes()->update(['billing_day' => $day]);
        $this->info("Set billing_day={$day} on {$updated} subscriber(s).");

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
