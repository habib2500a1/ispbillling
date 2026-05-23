<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\OnuSignalLog;
use App\Services\Optical\OpticalDatabaseMaintenanceService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpticalDatabaseMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_status_reports_onu_with_rx_counts(): void
    {
        Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'ONU-DB-1',
            'rx_power_dbm' => -18.5,
            'status' => 'assigned',
        ]);

        $status = app(OpticalDatabaseMaintenanceService::class)->status(1);

        $this->assertSame(1, $status['counts']['onus_with_rx_dbm']);
    }

    public function test_prune_deletes_old_snapshots(): void
    {
        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'ONU-PRUNE-1',
            'status' => 'assigned',
        ]);

        OnuSignalLog::query()->create([
            'tenant_id' => 1,
            'device_id' => $onu->id,
            'rx_power_dbm' => -20,
            'granularity' => 'snapshot',
            'sampled_at' => now()->subDays(30),
        ]);

        config(['optical_database.retention.snapshot_days' => 14]);

        $deleted = app(OpticalDatabaseMaintenanceService::class)->prune(1);

        $this->assertSame(1, $deleted['snapshots']);
        $this->assertSame(0, OnuSignalLog::query()->count());
    }
}
