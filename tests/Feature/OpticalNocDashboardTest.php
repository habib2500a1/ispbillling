<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\OnuSignalLog;
use App\Services\Optical\OpticalNocDashboardService;
use App\Services\Optical\OpticalSignalHistoryService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpticalNocDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_signal_history_series_returns_chart_data(): void
    {
        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'ONU-CHART-1',
            'status' => 'assigned',
        ]);

        OnuSignalLog::query()->create([
            'tenant_id' => 1,
            'device_id' => $onu->id,
            'rx_power_dbm' => -18.5,
            'tx_power_dbm' => 2.0,
            'rx_level' => 'good',
            'tx_level' => 'normal',
            'granularity' => 'snapshot',
            'sampled_at' => now()->subHour(),
        ]);

        $series = app(OpticalSignalHistoryService::class)->series($onu->id, '24h');

        $this->assertNotEmpty($series['labels']);
        $this->assertEquals(-18.5, $series['rx'][0]);
    }

    public function test_noc_full_snapshot_includes_network_health(): void
    {
        Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'ONU-NOC-1',
            'rx_power_dbm' => -20,
            'onu_oper_status' => 'online',
            'status' => 'assigned',
        ]);

        $snap = app(OpticalNocDashboardService::class)->fullSnapshot(1);

        $this->assertArrayHasKey('network_health_score', $snap);
        $this->assertArrayHasKey('trend_24h', $snap);
        $this->assertGreaterThan(0, $snap['total_onus']);
    }
}
