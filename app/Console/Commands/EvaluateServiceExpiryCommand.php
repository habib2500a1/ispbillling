<?php

namespace App\Console\Commands;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use Illuminate\Console\Command;

class EvaluateServiceExpiryCommand extends Command
{
    protected $signature = 'isp:evaluate-service-expiry {--tenant= : Limit to tenant id}';

    protected $description = 'Process customers past service_expires_at: demote to inactive + push MikroTik (runs coordinator sync).';

    public function handle(): int
    {
        if (! config('network.service_expiry_enforced', true)) {
            $this->info('Service expiry enforcement is disabled (NETWORK_SERVICE_EXPIRY_ENFORCED).');

            return self::SUCCESS;
        }

        $q = Customer::query()->withoutGlobalScopes()
            ->whereNotNull('service_expires_at')
            ->whereDate('service_expires_at', '<', now()->toDateString())
            ->where(function ($q): void {
                $q->whereIn('status', ['active', 'suspended', 'inactive'])
                    ->orWhere('network_access_state', 'active');
            });

        if ($this->option('tenant') !== null && $this->option('tenant') !== '') {
            $q->where('tenant_id', (int) $this->option('tenant'));
        }

        $n = 0;
        $q->orderBy('id')->chunkById(200, function ($customers) use (&$n): void {
            foreach ($customers as $customer) {
                SyncCustomerNetworkAccessJob::dispatch((int) $customer->tenant_id, (int) $customer->id)->afterResponse();
                $n++;
            }
        });

        $this->info("Queued network sync for {$n} customer(s) with expired service date.");

        return self::SUCCESS;
    }
}
