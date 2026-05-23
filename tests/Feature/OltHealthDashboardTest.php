<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\OltHealthLog;
use App\Services\Olt\OltHealthHistoryService;
use App\Services\Olt\OltNocDashboardService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OltHealthDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_olt_noc_snapshot_lists_health_metrics(): void
    {
        Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-CORE-1',
            'display_name' => 'OLT-Core-1',
            'management_ip' => '10.0.0.1',
            'status' => 'active',
            'vendor' => 'huawei',
            'olt_health' => [
                'cpu_percent' => 42,
                'memory_percent' => 61,
                'temperature_c' => 38.5,
                'health_score' => 88,
                'onus_online' => 120,
                'onus_offline' => 5,
                'snmp_ok' => true,
            ],
        ]);

        $snap = app(OltNocDashboardService::class)->snapshot(1);

        $this->assertSame(1, $snap['olt_total']);
        $this->assertSame(88, $snap['avg_health_score']);
        $this->assertSame(42, $snap['olts'][0]['cpu_percent']);
    }

    public function test_olt_health_history_series_from_logs(): void
    {
        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-HIST-1',
            'status' => 'active',
        ]);

        OltHealthLog::query()->create([
            'tenant_id' => 1,
            'device_id' => $olt->id,
            'snmp_ok' => true,
            'cpu_percent' => 30,
            'memory_percent' => 50,
            'health_score' => 90,
            'sampled_at' => now()->subHour(),
        ]);

        $series = app(OltHealthHistoryService::class)->series($olt->id, '24h');

        $this->assertNotEmpty($series['labels']);
        $this->assertSame(30, $series['cpu'][0]);
    }

    public function test_meta_override_applied_in_health_probe(): void
    {
        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-META-1',
            'status' => 'active',
            'meta' => ['cpu_percent' => 55, 'memory_percent' => 70],
        ]);

        $result = app(\App\Services\Olt\OltHealthProbeService::class)->probeAndPersist($olt, [
            'onus_online' => 10,
            'onus_offline' => 2,
        ]);

        $this->assertSame(55, $result['cpu_percent']);
        $this->assertSame(70, $result['memory_percent']);
        $olt->refresh();
        $this->assertSame(55, $olt->olt_health['cpu_percent'] ?? null);
    }
}
