<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlobalSmartSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_smart_search_requires_auth(): void
    {
        $this->getJson(route('admin.smart-search', ['q' => 'test']))
            ->assertUnauthorized();
    }

    public function test_smart_search_returns_json_for_admin(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);
        Role::findOrCreate('isp-admin');
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->getJson(route('admin.smart-search', ['q' => 'xx']))
            ->assertOk()
            ->assertJsonStructure(['results']);
    }

    public function test_smart_search_finds_customer_by_ppp_login(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);
        Role::findOrCreate('isp-admin');
        $user->assignRole('isp-admin');

        Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Test Subscriber',
            'customer_code' => 'CUST-TEST-SEARCH',
            'mikrotik_secret_name' => 'test.ppp.user',
            'phone' => '01700000001',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('admin.smart-search', ['q' => 'test.ppp']))
            ->assertOk()
            ->assertJsonPath('results.0.type', 'customer')
            ->assertJsonPath('results.0.label', 'CUST-TEST-SEARCH — Test Subscriber');

        $viewUrl = $response->json('results.0.view_url');
        $payUrl = $response->json('results.0.pay_url');

        $this->assertStringNotContainsString('/edit', (string) $viewUrl);
        $this->assertStringContainsString('/edit', (string) $response->json('results.0.edit_url'));
        $this->assertStringContainsString('bill-collection', (string) $payUrl);
        $this->assertStringContainsString('customer=', (string) $payUrl);
    }
}
