<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Services\Payments\PublicCheckoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RocketPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_page_requires_valid_session(): void
    {
        config([
            'rocket.enabled' => true,
            'rocket.merchant_number' => '01710000001',
        ]);

        $this->get('/rocket/pay?order=invalid')->assertRedirect();
    }

    public function test_checkout_page_shows_for_valid_session(): void
    {
        config([
            'rocket.enabled' => true,
            'rocket.merchant_number' => '01710000001',
        ]);

        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Rocket User',
            'phone' => '01710000088',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        $orderId = PublicCheckoutSession::makeTranId($customer->id, null);
        PublicCheckoutSession::put($orderId, [
            'customer_id' => $customer->id,
            'amount' => '100.00',
            'return_to' => 'bill_payment',
            'payment_type' => 'payment',
            'gateway' => 'rocket',
        ]);

        $this->get('/rocket/pay?order='.$orderId)
            ->assertOk()
            ->assertSee('Rocket payment')
            ->assertSee('01710000001');
    }
}
