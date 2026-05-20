<?php

namespace Tests\Unit;

use App\Models\MikrotikServer;
use App\Services\Radius\RadiusNasResolver;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadiusNasResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_nas_map_uses_radius_nas_ip_and_host(): void
    {
        MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'Core',
            'host' => '10.0.0.1',
            'radius_nas_ip' => '10.0.0.99',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_enabled' => true,
        ]);

        $map = app(RadiusNasResolver::class)->nasMapForTenant(1);

        $this->assertSame(1, $map['10.0.0.99']);
        $this->assertSame(1, $map['10.0.0.1']);
    }

    public function test_resolve_server_id_from_nas_ip(): void
    {
        $server = MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'BRAS',
            'host' => '192.168.1.1',
            'radius_nas_ip' => '192.168.1.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_enabled' => true,
        ]);

        $id = app(RadiusNasResolver::class)->resolveServerId(1, '192.168.1.1');

        $this->assertSame($server->id, $id);
    }
}
