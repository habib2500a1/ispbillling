<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Services\Billing\CustomerActivationBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerActivationBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepaid_this_month_creates_invoice_on_activation(): void
    {
        $package = Package::query()->create([
            'name' => 'Prepaid 10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'billing_cycle_type' => 'monthly',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Prepaid New',
            'phone' => '01700009999',
            'status' => 'active',
            'billing_mode' => 'prepaid',
            'billing_day' => 19,
            'joined_at' => now()->toDateString(),
            'package_id' => $package->id,
            'meta' => ['auto_invoice' => true],
        ]);

        $result = app(CustomerActivationBillingService::class)->issueFirstBillIfRequested(
            $customer,
            CustomerActivationBillingService::CYCLE_THIS_MONTH,
            false,
        );

        $this->assertNotNull($result['invoice']);
        $this->assertDatabaseCount('invoices', 1);

        $customer->refresh();
        $this->assertSame(
            $result['invoice']->period_end?->toDateString(),
            $customer->service_expires_at?->toDateString(),
        );
    }

    public function test_next_month_skips_invoice_today(): void
    {
        $package = Package::query()->create([
            'name' => 'Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Later Bill',
            'phone' => '01700009998',
            'status' => 'active',
            'billing_mode' => 'prepaid',
            'billing_day' => 5,
            'package_id' => $package->id,
            'meta' => ['auto_invoice' => true],
        ]);

        $result = app(CustomerActivationBillingService::class)->issueFirstBillIfRequested(
            $customer,
            CustomerActivationBillingService::CYCLE_NEXT_MONTH,
        );

        $this->assertNull($result['invoice']);
        $this->assertDatabaseCount('invoices', 0);
    }
}
