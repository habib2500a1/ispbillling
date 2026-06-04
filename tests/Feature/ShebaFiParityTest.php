<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PromotionalOffer;
use App\Models\Reseller;
use App\Models\ResellerCustomerTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\CustomerWalletService;
use App\Services\Subscribers\AdminSubscriberTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShebaFiParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_center_webhook_rejects_bad_secret(): void
    {
        config(['call_center.webhook_secret' => 'test-secret']);

        $this->postJson('/api/webhooks/call-center', [], [
            'X-ISP-Webhook-Secret' => 'wrong',
        ])->assertUnauthorized();
    }

    public function test_promotional_offer_validity(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'sheba-offer-test'],
            ['name' => 'Offer ISP', 'is_active' => true],
        );

        $offer = PromotionalOffer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Summer',
            'discount_type' => PromotionalOffer::TYPE_PERCENT,
            'discount_value' => 10,
            'is_active' => true,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addMonth(),
        ]);

        $this->assertTrue($offer->isValidAt(now()));
        $this->assertTrue($offer->appliesToPackage(null));
    }

    public function test_wallet_deposit_increases_balance(): void
    {
        config(['network.mikrotik_push_enabled' => false]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'sheba-wallet-test'],
            ['name' => 'Wallet ISP', 'is_active' => true],
        );
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_code' => 'W001',
            'name' => 'Wallet Client',
            'phone' => '01799998888',
            'status' => 'active',
            'billing_day' => 1,
            'account_balance' => 0,
        ]);

        app(CustomerWalletService::class)->deposit($customer, 500, 'test');

        $this->assertEquals(500.0, (float) $customer->fresh()->account_balance);
    }

    public function test_admin_move_logs_reseller_transfer_record(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'sheba-move-test'],
            ['name' => 'Move ISP', 'is_active' => true],
        );

        $from = Reseller::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'POP A',
            'code' => 'POP-A',
            'is_active' => true,
        ]);
        $to = Reseller::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'POP B',
            'code' => 'POP-B',
            'is_active' => true,
        ]);

        $customer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'reseller_id' => $from->id,
            'customer_code' => 'M001',
            'name' => 'Move Client',
            'phone' => '01710003333',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        app(AdminSubscriberTransferService::class)->moveToReseller($customer, $to, $actor, 'test move');

        $this->assertSame($to->id, $customer->fresh()->reseller_id);
        $this->assertDatabaseHas('reseller_customer_transfers', [
            'customer_id' => $customer->id,
            'from_reseller_id' => $from->id,
            'to_reseller_id' => $to->id,
            'status' => ResellerCustomerTransfer::STATUS_COMPLETED,
        ]);
    }
}
