<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Reseller;
use App\Models\ResellerPackage;
use App\Services\Resellers\ResellerCustomerProfileService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerCustomerExtendedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_profile_meta_payment_plan_and_tags(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'R',
            'code' => 'R-EXT',
            'is_active' => true,
        ]);

        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => '10M',
            'download_mbps' => 10,
            'upload_mbps' => 10,
            'price_monthly' => 500,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'name' => 'User',
            'phone' => '01711111111',
            'address' => 'Dhaka',
            'package_id' => $package->id,
            'customer_code' => 'EXT-1',
            'status' => 'active',
            'meta' => [],
        ]);

        $service = app(ResellerCustomerProfileService::class);
        $service->applyProfileMeta($customer, [
            'tag_vip' => true,
            'tag_late_payer' => true,
            'payment_plan_enabled' => true,
            'payment_plan_installment_bdt' => 250,
            'payment_plan_note' => '4 parts',
            'mac_binding' => 'AA:BB:CC:DD:EE:01',
            'notify_sms' => false,
        ]);
        $customer->save();

        $snap = $service->profileSnapshot($customer->fresh());
        $this->assertTrue($snap['tags']['vip']);
        $this->assertTrue($snap['payment_plan']['enabled']);
        $this->assertSame(250.0, $snap['payment_plan']['installment_bdt']);
        $this->assertSame('AA:BB:CC:DD:EE:01', $snap['network']['mac_binding']);
        $this->assertFalse($snap['notify']['sms']);
    }

    public function test_package_quote_returns_json_shape(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'R2',
            'code' => 'R-EXT2',
            'is_active' => true,
        ]);

        $small = Package::query()->create([
            'tenant_id' => 1,
            'name' => '5M',
            'download_mbps' => 5,
            'upload_mbps' => 5,
            'price_monthly' => 400,
            'is_active' => true,
        ]);

        $big = Package::query()->create([
            'tenant_id' => 1,
            'name' => '20M',
            'download_mbps' => 20,
            'upload_mbps' => 20,
            'price_monthly' => 800,
            'is_active' => true,
        ]);

        foreach ([$small, $big] as $pkg) {
            ResellerPackage::query()->create([
                'tenant_id' => 1,
                'reseller_id' => $reseller->id,
                'package_id' => $pkg->id,
                'selling_price' => 0,
                'wholesale_price' => 200,
                'is_active' => true,
            ]);
        }

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'name' => 'U',
            'phone' => '01722222222',
            'address' => 'X',
            'package_id' => $small->id,
            'customer_code' => 'EXT-2',
            'status' => 'active',
        ]);

        $quote = app(ResellerCustomerProfileService::class)->packageQuote($reseller, $customer, (int) $big->id);
        $this->assertSame('5M', $quote['current_package']);
        $this->assertSame('20M', $quote['new_package']);
        $this->assertArrayHasKey('net_due', $quote);
    }
}
