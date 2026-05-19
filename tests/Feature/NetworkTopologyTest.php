<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Customer;
use App\Models\Device;
use App\Models\MikrotikServer;
use App\Models\OltPort;
use App\Models\User;
use App\Models\Zone;
use App\Services\Network\NetworkTopologyService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NetworkTopologyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    private function admin(): User
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        return $user;
    }

    public function test_topology_page_loads(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/network-topology')
            ->assertOk()
            ->assertSee('Network topology map')
            ->assertSee('Core — MikroTik')
            ->assertSee('Geographic tree');
    }

    public function test_topology_service_builds_fiber_hierarchy(): void
    {
        $mt = MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'Core-1',
            'host' => '10.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'x',
            'is_enabled' => true,
            'last_api_status' => 'ok',
        ]);

        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'display_name' => 'OLT-DHK-1',
            'serial_number' => 'OLT-SN-001',
            'management_ip' => '10.0.1.10',
            'status' => 'active',
        ]);

        $port = OltPort::query()->create([
            'tenant_id' => 1,
            'device_id' => $olt->id,
            'card_index' => 0,
            'pon_index' => 1,
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Test User',
            'phone' => '01710001199',
            'status' => 'active',
            'billing_day' => 1,
            'mikrotik_server_id' => $mt->id,
        ]);

        Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'ONU-SN-99',
            'olt_id' => $olt->id,
            'olt_port_id' => $port->id,
            'customer_id' => $customer->id,
            'onu_oper_status' => 'online',
            'status' => 'deployed',
        ]);

        $area = Area::query()->create(['tenant_id' => 1, 'name' => 'Dhaka', 'is_active' => true]);
        Zone::query()->create(['tenant_id' => 1, 'area_id' => $area->id, 'name' => 'North', 'is_active' => true]);

        $topology = app(NetworkTopologyService::class)->build();

        $this->assertSame(1, $topology['summary']['mikrotik']);
        $this->assertSame(1, $topology['summary']['olts']);
        $this->assertSame('Core-1', $topology['mikrotik'][0]['name']);
        $this->assertSame('OLT-DHK-1', $topology['olts'][0]['label']);
        $this->assertSame('Test User', $topology['olts'][0]['ports'][0]['onus']['items'][0]['customer']['name']);
        $this->assertSame('Dhaka', $topology['geo'][0]['name']);
    }
}
