<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\PendingGatewayPayment;
use App\Models\User;
use App\Services\Payments\GatewayPaymentVerificationService;
use App\Services\Payments\PublicCheckoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ThreeFeatureBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_rocket_confirmation_queues_when_auto_verify_off(): void
    {
        config(['rocket.auto_verify' => false]);

        $customer = $this->makeCustomer();
        $orderId = PublicCheckoutSession::makeTranId($customer->id, null);
        PublicCheckoutSession::put($orderId, [
            'customer_id' => $customer->id,
            'amount' => '100.00',
            'return_to' => 'bill_payment',
            'payment_type' => 'payment',
        ]);

        $result = app(GatewayPaymentVerificationService::class)->submitRocketConfirmation(
            $orderId,
            'ABCD12345678',
            PublicCheckoutSession::get($orderId) ?? [],
        );

        $this->assertSame('pending', $result['status']);
        $this->assertDatabaseHas('pending_gateway_payments', [
            'transaction_id' => 'ABCD12345678',
            'status' => PendingGatewayPayment::STATUS_PENDING,
        ]);
    }

    public function test_rocket_auto_verify_posts_payment(): void
    {
        config(['rocket.auto_verify' => true]);

        $customer = $this->makeCustomer();
        $orderId = PublicCheckoutSession::makeTranId($customer->id, null);
        PublicCheckoutSession::put($orderId, [
            'customer_id' => $customer->id,
            'amount' => '50.00',
        ]);

        $result = app(GatewayPaymentVerificationService::class)->submitRocketConfirmation(
            $orderId,
            'ROCKET9999',
            PublicCheckoutSession::get($orderId) ?? [],
        );

        $this->assertSame('approved', $result['status']);
        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'gateway_transaction_id' => 'ROCKET9999',
        ]);
    }

    public function test_collector_api_accepts_cashier_role(): void
    {
        Role::findOrCreate('cashier');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('cashier');

        Sanctum::actingAs($user, ['collector', 'staff']);

        $this->getJson('/api/v1/collector/visits/today')->assertOk();
    }

    public function test_network_grace_days_delay_suspend_eligibility(): void
    {
        config([
            'network.auto_suspend_grace_days' => 5,
            'network.auto_suspend_min_balance' => 1,
        ]);

        $customer = $this->makeCustomer();
        \App\Models\Invoice::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-GRACE',
            'status' => 'sent',
            'issue_date' => now()->subDays(2)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'period_start' => now()->subDays(32)->toDateString(),
            'period_end' => now()->subDays(2)->toDateString(),
            'total' => 500,
            'amount_paid' => 0,
        ]);

        $coordinator = app(\App\Services\Network\NetworkAccessCoordinator::class);
        $this->assertFalse($coordinator->hasOverdueOpenBalance($customer->fresh()));
    }

    private function makeCustomer(): Customer
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        return Customer::query()->create([
            'name' => 'Test',
            'phone' => '01710001111',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);
    }
}
