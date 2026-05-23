<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Services\Optical\OpticalTelegramAlertService;
use App\Services\Optical\OpticalTopologyService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpticalTelegramTopologyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
        Cache::flush();
    }

    public function test_telegram_cooldown_blocks_duplicate(): void
    {
        config(['optical.telegram.enabled' => true, 'notifications.telegram.enabled' => true]);

        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'ONU-TG-1',
            'status' => 'assigned',
        ]);

        $svc = app(OpticalTelegramAlertService::class);
        $svc->notifyOnuAlert($onu, 'critical', 'Test', 'Message');
        $svc->notifyOnuAlert($onu, 'critical', 'Test', 'Message again');

        $this->assertTrue(Cache::has(sprintf('optical_tg:%d:onu:%d:%s', 1, $onu->id, md5('Test'))));
    }

    public function test_topology_builds_olt_tree(): void
    {
        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-TOPO-1',
            'display_name' => 'OLT Main',
            'status' => 'active',
        ]);

        Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'ONU-TOPO-1',
            'olt_id' => $olt->id,
            'rx_power_dbm' => -22,
            'onu_oper_status' => 'online',
            'status' => 'assigned',
        ]);

        $tree = app(OpticalTopologyService::class)->buildForTenant(1);

        $this->assertSame(1, $tree['summary']['olts']);
        $this->assertGreaterThanOrEqual(1, $tree['summary']['onus']);
    }
}
