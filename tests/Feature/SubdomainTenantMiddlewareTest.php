<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Tenant;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubdomainTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['isp.tenant_base_domain' => 'isp.test']);
    }

    public function test_subdomain_host_scopes_models_to_matching_tenant(): void
    {
        $t2 = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
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

        $this->get('http://acme.isp.test/');

        $this->assertSame($t2->id, TenantResolver::currentTenantId());
        $this->assertCount(1, Package::query()->get());
    }

    public function test_apex_host_does_not_set_subdomain_tenant(): void
    {
        config(['isp.tenant_base_domain' => 'isp.test']);

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

        $this->get('http://isp.test/');

        $this->assertNull(TenantResolver::currentTenantId());
        $this->assertCount(1, Package::query()->get());
    }
}
