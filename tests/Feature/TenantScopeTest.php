<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Tenant;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_query_respects_tenant_resolver_fake(): void
    {
        $t2 = Tenant::query()->create([
            'name' => 'Second ISP',
            'slug' => 'second',
            'is_active' => true,
        ]);

        Package::withoutGlobalScopes()->create([
            'tenant_id' => 1,
            'name' => 'P1',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        Package::withoutGlobalScopes()->create([
            'tenant_id' => $t2->id,
            'name' => 'P2',
            'type' => 'residential',
            'download_mbps' => 20,
            'price_monthly' => 200,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        TenantResolver::fake(1);
        $this->assertCount(1, Package::query()->get());

        TenantResolver::fake((int) $t2->id);
        $this->assertCount(1, Package::query()->get());

        TenantResolver::clearFake();
        $this->assertCount(2, Package::withoutGlobalScopes()->get());
    }
}
