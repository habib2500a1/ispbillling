<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Support\CustomerAccountScopes;
use App\Support\CustomerStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCustomerAccountStatusCommand extends Command
{
    protected $signature = 'customers:sync-account-status
                            {--dry-run : Show counts only, do not update}';

    protected $description = 'Align customer status with validity dates and legacy ISP Digital «left» markers';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = now()->toDateString();

        $expireCandidates = Customer::query()
            ->where('status', CustomerStatus::ACTIVE)
            ->where(function ($q): void {
                CustomerAccountScopes::notLegacyLeft($q);
            })
            ->whereNotNull('service_expires_at')
            ->whereDate('service_expires_at', '<', $today);

        $leftCandidates = Customer::query()
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->where(function ($q): void {
                CustomerAccountScopes::legacyLeft($q);
            });

        $expireCount = (clone $expireCandidates)->count();
        $leftCount = (clone $leftCandidates)->count();

        $this->info("Will mark expired (active + past validity): {$expireCount}");
        $this->info("Will mark terminated (legacy left): {$leftCount}");

        if ($dryRun) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($expireCandidates, $leftCandidates): void {
            $expireCandidates->update([
                'status' => CustomerStatus::EXPIRED,
                'network_access_state' => 'suspended',
            ]);

            $leftCandidates->update([
                'status' => CustomerStatus::TERMINATED,
                'network_access_state' => 'suspended',
            ]);
        });

        $this->callSilent('cache:clear');

        $this->info('Customer statuses synced.');

        return self::SUCCESS;
    }
}
