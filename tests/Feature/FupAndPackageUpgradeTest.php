<?php

namespace Tests\Feature;

use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Services\Billing\FupOverageBillingService;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\PackageChangeQuoteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FupAndPackageUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fup_overage_calculated_for_period(): void
    {
        $package = Package::query()->create([
            'name' => 'FUP',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'included_data_gb' => 1,
            'overage_price_per_gb' => 20,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'User',
            'phone' => '01723333333',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $package->id,
        ]);

        BandwidthUsageDaily::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'usage_date' => now()->toDateString(),
            'bytes_in' => 3 * 1073741824,
            'bytes_out' => 0,
        ]);

        $start = now()->startOfDay();
        $end = now()->startOfDay();
        $result = app(FupOverageBillingService::class)->calculateForPeriod($customer, $package, $start, $end);

        $this->assertNotNull($result);
        $this->assertGreaterThan(0, $result['amount']);
    }

    public function test_fup_line_on_invoice_when_usage_exceeds(): void
    {
        config(['billing.setup_fee_on_first_invoice' => false]);

        $package = Package::query()->create([
            'name' => 'FUP',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_type' => 'daily',
            'billing_cycle_days' => 30,
            'included_data_gb' => 1,
            'overage_price_per_gb' => 10,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'User',
            'phone' => '01724444444',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $package->id,
        ]);

        BandwidthUsageDaily::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'usage_date' => now()->toDateString(),
            'bytes_in' => 2 * 1073741824,
            'bytes_out' => 0,
        ]);

        $invoice = InvoiceGenerator::generateForCustomer($customer, Carbon::now(), true);
        $this->assertNotNull($invoice);
        $this->assertTrue($invoice->items()->where('item_type', 'fup_overage')->exists());
    }

    public function test_package_upgrade_quote(): void
    {
        $current = Package::query()->create([
            'name' => '5M',
            'type' => 'residential',
            'download_mbps' => 5,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $bigger = Package::query()->create([
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 800,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Up',
            'phone' => '01725555555',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $current->id,
        ]);

        $quote = app(PackageChangeQuoteService::class)->quote($customer, $bigger);
        $this->assertTrue($quote['is_upgrade']);
        $this->assertGreaterThanOrEqual(0, $quote['net_due']);
    }
}
