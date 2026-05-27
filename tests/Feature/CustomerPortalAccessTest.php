<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\Portal\CustomerPortalAccessService;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_default_password_is_applied_on_create_path(): void
    {
        $service = app(CustomerPortalAccessService::class);
        $this->assertSame('123456', $service->defaultPassword());
    }

    public function test_token_login_grants_portal_session(): void
    {
        $customer = Customer::createTrusted([
            'tenant_id' => 1,
            'customer_code' => 'CUST-TEST-01',
            'name' => 'Portal User',
            'phone' => '01719999999',
            'status' => CustomerStatus::ACTIVE,
            'portal_password' => Hash::make('123456'),
        ]);

        $token = app(CustomerPortalAccessService::class)->regenerateAccessToken($customer);

        $this->get(route('portal.access.token', ['token' => $token]))
            ->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($customer, 'customer');
    }
}
