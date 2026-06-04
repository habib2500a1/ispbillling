<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\ProductSkuGenerator;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSkuGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_unique_sku_for_tenant(): void
    {
        $tenantId = TenantResolver::requiredTenantId();

        Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'sku' => 'PRD-TEST-0001',
            'name' => 'Existing',
        ]);

        $sku = ProductSkuGenerator::generate($tenantId, 'Test Router');

        $this->assertNotSame('PRD-TEST-0001', $sku);
        $this->assertStringStartsWith('PRD-', $sku);
    }
}
