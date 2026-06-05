<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Reseller;
use App\Models\ResellerPackage;
use App\Services\Billing\PackagePriceResolver;
use App\Services\Resellers\ResellerCustomerPricingService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerCustomerPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_custom_retail_override_changes_invoice_base_price(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Partner',
            'code' => 'RSL-PRC',
            'is_active' => true,
        ]);

        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => '20 Mbps',
            'download_mbps' => 20,
            'upload_mbps' => 20,
            'price_monthly' => 1000,
            'is_active' => true,
        ]);

        ResellerPackage::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'package_id' => $package->id,
            'selling_price' => 0,
            'wholesale_price' => 700,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'name' => 'Test User',
            'phone' => '01700000001',
            'address' => 'Dhaka',
            'package_id' => $package->id,
            'customer_code' => 'C-PRC-1',
            'status' => 'active',
            'billing_mode' => 'prepaid',
            'meta' => ['reseller_retail_monthly_bdt' => 850],
        ]);

        $monthly = PackagePriceResolver::resolveBaseMonthlyPrice($package, $customer);
        $this->assertSame(850.0, $monthly);

        $snapshot = app(ResellerCustomerPricingService::class)->snapshot($reseller, $customer);
        $this->assertSame(850.0, $snapshot['retail_monthly']);
        $this->assertSame(700.0, $snapshot['wholesale_monthly']);
        $this->assertSame(150.0, $snapshot['margin_monthly']);
    }

    public function test_monthly_discount_applies_when_no_custom_retail(): void
    {
        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => '10 Mbps',
            'download_mbps' => 10,
            'upload_mbps' => 10,
            'price_monthly' => 500,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'reseller_id' => null,
            'name' => 'User',
            'phone' => '01700000002',
            'address' => 'Dhaka',
            'package_id' => $package->id,
            'customer_code' => 'C-PRC-2',
            'status' => 'active',
            'meta' => ['monthly_discount_bdt' => 50],
        ]);

        $this->assertSame(450.0, PackagePriceResolver::resolveBaseMonthlyPrice($package, $customer));
    }
}
