<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAutoActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_payment_reactivates_expired_subscriber(): void
    {
        $package = Package::query()->create([
            'name' => 'Test 25 Mbps',
            'price' => 500,
            'billing_cycle_type' => 'monthly',
            'billing_cycle_days' => 30,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Expired Payer',
            'phone' => '01730000099',
            'status' => CustomerStatus::EXPIRED,
            'network_access_state' => 'suspended',
            'service_expires_at' => now()->subDays(3)->toDateString(),
            'package_id' => $package->id,
            'meta' => ['auto_activate' => true],
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 500,
            'status' => 'paid',
        ]);

        Payment::createTrusted([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'payment_type' => PaymentType::PAYMENT,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $customer = $customer->fresh();

        $this->assertSame(CustomerStatus::ACTIVE, $customer->status);
        $this->assertSame('active', $customer->network_access_state);
        $this->assertFalse($customer->isServiceExpired());
        $this->assertTrue($customer->service_expires_at->isFuture());
    }
}
