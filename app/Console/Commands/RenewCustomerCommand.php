<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Subscribers\CustomerServiceRenewalService;
use Illuminate\Console\Command;

class RenewCustomerCommand extends Command
{
    protected $signature = 'isp:renew-customer {login : Customer code or phone digits} {--days=30 : Days to add from today or current expiry} {--tenant= : Tenant id (default: 1)}';

    protected $description = 'Extend service_expires_at and re-activate network; then push MikroTik.';

    public function handle(): int
    {
        $login = trim((string) $this->argument('login'));
        $days = max(1, (int) $this->option('days'));
        $tenantId = (int) ($this->option('tenant') ?: 1);

        $digits = preg_replace('/\D+/', '', $login) ?? '';

        $customer = Customer::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($login, $digits): void {
                $q->where('customer_code', $login);
                if ($digits !== '') {
                    $q->orWhere('phone', $digits)->orWhere('phone', $login);
                }
            })
            ->first();

        if (! $customer) {
            $this->error('Customer not found for this tenant.');

            return self::FAILURE;
        }

        $result = app(CustomerServiceRenewalService::class)->extendDays($customer, $days);

        $this->info("Renewed {$customer->customer_code} +{$days}d until {$result['expires_at']} (MikroTik synced).");

        return self::SUCCESS;
    }
}
