<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Billing\CustomerPrepayService;
use App\Services\Payments\PaymentProcessor;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPrepayPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepay_quote_totals_current_due_plus_months(): void
    {
        $package = Package::query()->create([
            'name' => '25 Mbps',
            'price_monthly' => 1000,
            'billing_cycle_type' => 'monthly',
            'billing_cycle_days' => 30,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Prepay Customer',
            'phone' => '01730000111',
            'status' => CustomerStatus::ACTIVE,
            'package_id' => $package->id,
            'service_expires_at' => now()->subDay()->toDateString(),
        ]);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $quote = app(CustomerPrepayService::class)->quote($customer, 3);
        $this->assertNotNull($quote);
        $this->assertSame(3, $quote['months']);
        $this->assertSame(500.0, $quote['current_due']);
        $this->assertSame(3000.0, $quote['prepay_amount']);
        $this->assertSame(3500.0, $quote['total_amount']);
    }

    public function test_prepay_payment_clears_due_and_extends_service_for_multiple_months(): void
    {
        $package = Package::query()->create([
            'name' => '25 Mbps',
            'price_monthly' => 1000,
            'billing_cycle_type' => 'monthly',
            'billing_cycle_days' => 30,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Prepay Payer',
            'phone' => '01730000222',
            'status' => CustomerStatus::EXPIRED,
            'network_access_state' => 'suspended',
            'service_expires_at' => now()->subDays(5)->toDateString(),
            'package_id' => $package->id,
            'meta' => ['auto_activate' => true],
        ]);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        Payment::createTrusted([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'payment_type' => PaymentType::PREPAY,
            'amount' => 3500,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'meta' => [
                'prepay_months' => 3,
                'fifo_multi_invoice' => true,
            ],
        ]);

        $customer = $customer->fresh();
        $this->assertSame(CustomerStatus::ACTIVE, $customer->status);
        $this->assertSame('active', $customer->network_access_state);
        $this->assertTrue($customer->service_expires_at->greaterThan(now()->addDays(80)));
    }
}
