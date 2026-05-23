<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reseller;
use App\Models\ResellerPackage;
use App\Services\Resellers\ResellerPackageCatalogService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerPackageCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_reseller_with_assignments_only_sees_assigned_packages(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'R1',
            'code' => 'RSL-TEST',
            'is_active' => true,
        ]);

        $p1 = Package::query()->create([
            'tenant_id' => 1,
            'name' => '10 Mbps',
            'download_mbps' => 10,
            'upload_mbps' => 10,
            'price_monthly' => 500,
            'is_active' => true,
        ]);

        Package::query()->create([
            'tenant_id' => 1,
            'name' => '20 Mbps',
            'download_mbps' => 20,
            'upload_mbps' => 20,
            'price_monthly' => 800,
            'is_active' => true,
        ]);

        ResellerPackage::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'package_id' => $p1->id,
            'selling_price' => 450,
            'is_active' => true,
        ]);

        $catalog = app(ResellerPackageCatalogService::class);

        $this->assertCount(1, $catalog->packagesForReseller($reseller));
        $this->assertSame(450.0, $catalog->sellingPriceFor($reseller, $p1));
        $this->assertTrue($catalog->resellerMaySellPackage($reseller, (int) $p1->id));
    }

    public function test_reseller_without_assignments_sees_all_active_packages(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'R2',
            'code' => 'RSL-TEST2',
            'is_active' => true,
        ]);

        Package::query()->create([
            'tenant_id' => 1,
            'name' => 'A',
            'download_mbps' => 5,
            'upload_mbps' => 5,
            'price_monthly' => 300,
            'is_active' => true,
        ]);

        Package::query()->create([
            'tenant_id' => 1,
            'name' => 'B',
            'download_mbps' => 10,
            'upload_mbps' => 10,
            'price_monthly' => 500,
            'is_active' => true,
        ]);

        $this->assertCount(2, app(ResellerPackageCatalogService::class)->packagesForReseller($reseller));
    }
}
