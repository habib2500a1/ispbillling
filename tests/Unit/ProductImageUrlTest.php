<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_url_null_when_no_path(): void
    {
        $product = new Product(['image_path' => null]);

        $this->assertNull($product->imageUrl());
    }

    public function test_image_url_uses_public_disk(): void
    {
        $product = Product::withoutGlobalScopes()->create([
            'tenant_id' => TenantResolver::requiredTenantId(),
            'name' => 'ONU',
            'image_path' => 'products/1/draft/photo.jpg',
        ]);

        $this->assertStringContainsString('products/1/draft/photo.jpg', (string) $product->imageUrl());
    }
}
