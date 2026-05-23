<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\OnuSignalLog;
use App\Services\Optical\Normalization\OpticalPowerNormalizer;
use App\Services\Optical\OpticalReadingPipeline;
use App\Services\Optical\Validation\OpticalSignalValidator;
use App\Support\OnuSignalLevel;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpticalSignalAccuracyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_vendor_normalization_bdcom_tenth_dbm(): void
    {
        $n = app(OpticalPowerNormalizer::class);

        $this->assertSame(-18.2, $n->normalizeRx(-182, 'bdcom_epon'));
        $this->assertSame(-18.2, $n->normalizeRx(-182, 'huawei_gpon'));
    }

    public function test_rx_band_classification(): void
    {
        $this->assertSame(OnuSignalLevel::EXCELLENT, OnuSignalLevel::classifyRx(-10.0, 'online'));
        $this->assertSame(OnuSignalLevel::GOOD, OnuSignalLevel::classifyRx(-18.0, 'online'));
        $this->assertSame(OnuSignalLevel::WARNING, OnuSignalLevel::classifyRx(-25.0, 'online'));
        $this->assertSame(OnuSignalLevel::CRITICAL, OnuSignalLevel::classifyRx(-28.0, 'online'));
    }

    public function test_spike_is_rejected_from_average(): void
    {
        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'SPIKE-ONU',
            'status' => 'assigned',
        ]);

        foreach ([-18.1, -18.2, -18.3] as $rx) {
            OnuSignalLog::query()->create([
                'tenant_id' => 1,
                'device_id' => $onu->id,
                'rx_power_dbm' => $rx,
                'rx_level' => OnuSignalLevel::GOOD,
                'granularity' => 'snapshot',
                'is_spike' => false,
                'sampled_at' => now()->subMinutes(3),
            ]);
        }

        $validator = app(OpticalSignalValidator::class);
        $this->assertTrue($validator->isSpike(-26.9, [-18.1, -18.2, -18.3]));

        $smoothed = $validator->smooth((int) $onu->id, -26.9, null);
        $this->assertTrue($smoothed['is_spike']);
        $this->assertGreaterThan(-20, (float) $smoothed['rx_dbm']);
    }

    public function test_bdcom_snmp_raw_minus_205_is_received_power_minus_20_5(): void
    {
        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'BDCOM-RX',
            'status' => 'assigned',
            'onu_oper_status' => 'online',
        ]);

        app(OpticalReadingPipeline::class)->ingest($onu, [
            'rx_raw' => -205,
            'tx_raw' => 22,
            'vendor_profile' => 'bdcom_epon',
            'source' => 'test',
        ]);

        $onu->refresh();
        $this->assertSame(-20.5, (float) $onu->rx_power_dbm);
        $this->assertSame(2.2, (float) $onu->tx_power_dbm);
        $this->assertSame(-205, $onu->meta['optical']['snmp_rx_raw']);
    }

    public function test_pipeline_persists_smoothed_health(): void
    {
        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'PIPE-ONU',
            'status' => 'assigned',
            'onu_oper_status' => 'online',
        ]);

        app(OpticalReadingPipeline::class)->ingest($onu, [
            'rx_raw' => -182,
            'tx_raw' => 25,
            'vendor_profile' => 'bdcom_epon',
            'source' => 'test',
        ]);

        $onu->refresh();
        $this->assertSame(-18.2, (float) $onu->rx_power_dbm);
        $this->assertDatabaseHas('onu_health_scores', [
            'device_id' => $onu->id,
        ]);
        $this->assertDatabaseHas('onu_signal_logs', [
            'device_id' => $onu->id,
            'poll_source' => 'test',
        ]);
    }
}
