<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Services\Billing\CustomerCreditLimitService;
use App\Services\Billing\InvoiceGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_limit_blocks_invoice_generation(): void
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 1000,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Limited',
            'phone' => '01721111111',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $package->id,
            'credit_limit' => 500,
        ]);

        Invoice::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => 600,
            'total' => 600,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $this->assertTrue(app(CustomerCreditLimitService::class)->isOverCreditLimit($customer));
        $this->assertNull(InvoiceGenerator::generateForCustomer($customer, Carbon::now(), true));
    }

    public function test_setup_fee_added_once(): void
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 200,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'New',
            'phone' => '01722222222',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $package->id,
        ]);

        $invoice = InvoiceGenerator::generateForCustomer($customer, Carbon::now(), true);
        $this->assertNotNull($invoice);
        $this->assertTrue($invoice->items()->where('item_type', 'setup_fee')->exists());
    }

    public function test_dunning_command_runs(): void
    {
        config(['billing.dunning.enabled' => true]);
        config(['notifications.events.invoice_due_soon.enabled' => true]);

        $this->artisan('isp:send-invoice-due-reminders', ['--dry-run' => true])
            ->assertSuccessful();
    }
}
