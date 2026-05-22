<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Import\IspDigitalBillingReconciler;
use App\Support\CustomerBalanceDue;
use App\Support\TenantResolver;
use Illuminate\Console\Command;

class RefreshCustomerDueBalanceCommand extends Command
{
    protected $signature = 'isp:refresh-customer-due-balance
                            {--customer= : Customer ID or customer_code}';

    protected $description = 'Sync customer meta from open invoices only (removes legacy isp_digital_balance_due)';

    public function handle(): int
    {
        $tenantId = TenantResolver::currentTenantId();
        $reopened = app(IspDigitalBillingReconciler::class)->reopenConsolidatedMonthlyInvoices($tenantId);
        if ($reopened > 0) {
            $this->info("Reopened {$reopened} consolidated monthly invoice(s).");
        }

        $query = Customer::query();
        $filter = $this->option('customer');
        if ($filter !== null && $filter !== '') {
            $query->where(function ($q) use ($filter): void {
                $q->where('id', $filter)->orWhere('customer_code', $filter);
            });
        }

        $updated = 0;
        $query->orderBy('id')->chunkById(100, function ($customers) use (&$updated): void {
            foreach ($customers as $customer) {
                $hadLegacy = isset($customer->meta['isp_digital_balance_due']);
                $before = $hadLegacy
                    ? (float) $customer->meta['isp_digital_balance_due']
                    : CustomerBalanceDue::amount($customer);
                CustomerBalanceDue::refreshMetaAfterPayment($customer);
                $after = CustomerBalanceDue::amount($customer->fresh());
                if ($hadLegacy || abs($before - $after) > 0.009) {
                    $updated++;
                    $this->line("  {$customer->customer_code}: {$before} → {$after} BDT (invoice only)");
                }
            }
        });

        $this->info("Updated {$updated} customer(s).");

        return self::SUCCESS;
    }
}
