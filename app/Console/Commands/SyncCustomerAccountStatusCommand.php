<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\CustomerAccountScopes;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCustomerAccountStatusCommand extends Command
{
    protected $signature = 'customers:sync-account-status
                            {--dry-run : Show counts only, do not update}';

    protected $description = 'Align customer status with validity dates and legacy legacy portal «left» markers';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = now()->toDateString();

        // Find active customers whose service_expires_at is in the past
        // but only mark them expired if they have an overdue balance
        $expireCandidatesQuery = Customer::query()
            ->where('status', CustomerStatus::ACTIVE)
            ->where(function ($q): void {
                CustomerAccountScopes::notLegacyLeft($q);
            })
            ->whereNotNull('service_expires_at')
            ->whereDate('service_expires_at', '<', $today);

        // Filter to only those with overdue balances
        $expireCandidates = $expireCandidatesQuery->get()->filter(function (Customer $customer): bool {
            return $this->hasOverdueOpenBalance($customer);
        });

        $leftCandidates = Customer::query()
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->where(function ($q): void {
                CustomerAccountScopes::legacyLeft($q);
            });

        $expireCount = $expireCandidates->count();
        $leftCount = (clone $leftCandidates)->count();

        $this->info("Will mark expired (active + past validity + has overdue balance): {$expireCount}");
        $this->info("Will mark terminated (legacy left): {$leftCount}");

        if ($dryRun) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($expireCandidates, $leftCandidates): void {
            // Update each expired candidate individually to use the filtered list
            foreach ($expireCandidates as $customer) {
                $customer->forceFill([
                    'status' => CustomerStatus::EXPIRED,
                    'network_access_state' => 'suspended',
                ])->saveQuietly();
            }

            $leftCandidates->update([
                'status' => CustomerStatus::TERMINATED,
                'network_access_state' => 'suspended',
            ]);
        });

        $this->callSilent('cache:clear');

        $this->info('Customer statuses synced.');

        return self::SUCCESS;
    }

    /**
     * Check if customer has any invoice with a positive balance and due date in the past.
     */
    private function hasOverdueOpenBalance(Customer $customer): bool
    {
        $graceDays = max(0, (int) ($customer->grace_period_days ?? config('network.auto_suspend_grace_days', 0)));
        $minBalance = max(0.0, (float) config('network.auto_suspend_min_balance', 1));
        $asOf = now()->subDays($graceDays);

        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->when($customer->tenant_id !== null, fn ($q) => $q->where('tenant_id', $customer->tenant_id))
            ->whereNotIn('status', ['void', 'cancelled', 'paid', 'draft'])
            ->get()
            ->contains(fn (Invoice $invoice): bool => $invoice->balanceDue() >= $minBalance
                && $invoice->due_date !== null
                && $asOf->toDateString() > $invoice->due_date->toDateString());
    }
}
