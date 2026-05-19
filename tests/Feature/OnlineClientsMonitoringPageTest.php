<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnlineClientsMonitoringPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_loads_and_shows_online_stats(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'u1',
            'mikrotik_secret_name' => 'ppp-u1',
            'name' => 'Online User',
            'phone' => '01710000001',
            'status' => 'active',
            'is_ppp_online' => true,
        ]);

        Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'u2',
            'mikrotik_secret_name' => 'ppp-u2',
            'name' => 'Offline User',
            'phone' => '01710000002',
            'status' => 'active',
            'is_ppp_online' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/online-clients')
            ->assertOk()
            ->assertSee('Live PPP / online clients')
            ->assertSee('PPP subscribers')
            ->assertSee('Online now')
            ->assertSee('Offline User');
    }
}
