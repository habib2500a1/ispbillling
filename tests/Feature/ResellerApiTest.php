<?php

namespace Tests\Feature;

use App\Models\Reseller;
use App\Models\User;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResellerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_api_login_and_create_customer(): void
    {
        Role::findOrCreate('isp-admin');
        $admin = User::factory()->create(['tenant_id' => 1]);
        $admin->assignRole('isp-admin');

        $package = \App\Models\Package::query()->create([
            'tenant_id' => 1,
            'name' => '10 Mbps',
            'price_monthly' => 500,
            'is_active' => true,
        ]);

        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'API Partner',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'is_active' => true,
            'primary_user_id' => $admin->id,
            'portal_password' => Hash::make('api-secret'),
            'portal_permissions' => ResellerPortalPermission::defaultsFor(ResellerType::FRANCHISE),
        ]);

        $login = $this->postJson('/api/v1/reseller/login', [
            'login' => $reseller->code,
            'password' => 'api-secret',
            'device_name' => 'test',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'reseller']);
        $token = $login->json('token');

        $this->withToken($token)
            ->postJson('/api/v1/reseller/customers', [
                'name' => 'API Sub',
                'phone' => '01710001122',
                'address' => 'Test area',
                'package_id' => $package->id,
            ])
            ->assertCreated()
            ->assertJsonPath('customer.name', 'API Sub');
    }
}
