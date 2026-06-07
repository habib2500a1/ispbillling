<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HotspotVoucher;
use App\Models\Package;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Services\Hotspot\HotspotVoucherRedeemer;
use App\Support\PaymentGateway;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class TenantSecurityIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    public function test_landing_page_only_shows_packages_for_default_tenant(): void
    {
        $tenantA = Tenant::query()->findOrFail(1);
        $tenantB = Tenant::query()->create(['name' => 'ISP B', 'slug' => 'isp-b-sec', 'is_active' => true]);

        config([
            'isp.default_tenant_id' => $tenantA->id,
            'isp.landing_cache_minutes' => 0,
        ]);
        TenantResolver::setSubdomainTenantId($tenantA->id);
        Cache::flush();
        SafeCache::forget('landing:page:'.$tenantA->id);

        Package::query()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Plan A',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'show_on_website' => true,
        ]);

        Package::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Plan B Hidden',
            'type' => 'residential',
            'download_mbps' => 20,
            'price_monthly' => 900,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'show_on_website' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Plan A');
        $response->assertDontSee('Plan B Hidden');
    }

    public function test_shop_checkout_rejects_product_from_another_tenant(): void
    {
        config(['inventory.shop_enabled' => true]);

        $tenantA = Tenant::query()->create(['name' => 'Shop A', 'slug' => 'shop-a', 'is_active' => true]);
        $tenantB = Tenant::query()->create(['name' => 'Shop B', 'slug' => 'shop-b', 'is_active' => true]);

        config(['isp.default_tenant_id' => $tenantA->id]);
        TenantResolver::setSubdomainTenantId($tenantA->id);

        $foreignProduct = Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'sku' => 'FOREIGN-1',
            'name' => 'Foreign ONU',
            'sell_price' => 1000,
            'stock_qty' => 5,
            'is_active' => true,
            'show_on_shop' => true,
        ]);

        $this->from('/shop')
            ->post('/shop/checkout', [
                'product_id' => $foreignProduct->id,
                'quantity' => 1,
                'customer_name' => 'Buyer',
                'customer_phone' => '01700000000',
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('product_id');
    }

    public function test_support_webhook_scopes_customer_by_tenant(): void
    {
        config(['support.webhook_secret' => 'secret-123']);

        $tenantA = Tenant::query()->create(['name' => 'Support A', 'slug' => 'sup-a', 'is_active' => true]);
        $tenantB = Tenant::query()->create(['name' => 'Support B', 'slug' => 'sup-b', 'is_active' => true]);

        $package = Package::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B User',
            'customer_code' => 'SHARED-CODE',
            'phone' => '01811111111',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'portal_password' => Hash::make('x'),
        ]);

        $this->postJson(
            '/api/webhooks/support-ticket-ingest',
            [
                'tenant_id' => $tenantA->id,
                'customer_code' => 'SHARED-CODE',
                'subject' => 'Cross tenant',
                'description' => 'Should fail',
                'department' => 'technical_support',
            ],
            ['X-ISP-Webhook-Secret' => 'secret-123'],
        )->assertNotFound();

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_payment_webhook_scopes_customer_by_tenant(): void
    {
        config([
            'payments.gateways.'.PaymentGateway::BKASH.'.enabled' => true,
            'payments.gateways.'.PaymentGateway::BKASH.'.webhook_secret' => 'pay-secret',
        ]);

        $tenantA = Tenant::query()->create(['name' => 'Pay A', 'slug' => 'pay-a', 'is_active' => true]);
        $tenantB = Tenant::query()->create(['name' => 'Pay B', 'slug' => 'pay-b', 'is_active' => true]);

        $package = Package::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Pay User',
            'customer_code' => 'PAY-001',
            'phone' => '01822222222',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'portal_password' => Hash::make('x'),
        ]);

        $this->postJson('/api/webhooks/payments/bkash', [
            'secret' => 'pay-secret',
            'tenant_id' => $tenantA->id,
            'transaction_id' => 'TX-1',
            'amount' => 100,
            'customer_code' => 'PAY-001',
        ])->assertStatus(422);
    }

    public function test_hotspot_redeem_scopes_voucher_by_tenant(): void
    {
        config(['isp.default_tenant_id' => 1]);
        TenantResolver::setSubdomainTenantId(1);

        $tenantB = Tenant::query()->create(['name' => 'Hotspot B', 'slug' => 'hot-b', 'is_active' => true]);

        HotspotVoucher::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'code' => 'TENANT-B-CODE',
            'status' => HotspotVoucher::STATUS_AVAILABLE,
            'duration_hours' => 1,
        ]);

        $this->post('/hotspot/redeem', ['code' => 'TENANT-B-CODE'])
            ->assertSessionHasErrors('code');
    }

    public function test_hotspot_voucher_stays_available_when_provision_fails(): void
    {
        config(['isp.default_tenant_id' => 1]);
        TenantResolver::setSubdomainTenantId(1);

        $voucher = HotspotVoucher::query()->withoutGlobalScopes()->create([
            'tenant_id' => 1,
            'code' => 'FAIL-PROVISION',
            'status' => HotspotVoucher::STATUS_AVAILABLE,
            'duration_hours' => 1,
        ]);

        config(['hotspot.provision_enabled' => false]);

        $result = app(HotspotVoucherRedeemer::class)->redeem('FAIL-PROVISION', null, 1);

        $this->assertFalse($result['ok']);
        $this->assertSame(HotspotVoucher::STATUS_AVAILABLE, $voucher->fresh()->status);
    }
}
