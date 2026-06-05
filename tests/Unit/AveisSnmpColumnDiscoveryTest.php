<?php

namespace Tests\Unit;

use App\Services\Network\AveisGponOnuSyncService;
use App\Services\Network\AveisSnmpColumnDiscoveryService;
use Tests\TestCase;

class AveisSnmpColumnDiscoveryTest extends TestCase
{
    public function test_scores_mac_column(): void
    {
        $score = AveisSnmpColumnDiscoveryService::scoreMacColumn([
            'Hex-STRING: 00 AD 24 F0 FB 3C',
            'Hex-STRING: 11 22 33 44 55 66',
            'PON1:2',
        ]);

        $this->assertGreaterThan(0.5, $score);
    }

    public function test_scores_status_column(): void
    {
        $score = AveisSnmpColumnDiscoveryService::scoreStatusColumn(['1', '2', '1', '3']);

        $this->assertGreaterThan(0.9, $score);
    }

    public function test_scores_rx_column(): void
    {
        $score = AveisSnmpColumnDiscoveryService::scoreRxColumn(['841', '920', '0', 'N/A']);

        $this->assertGreaterThan(0.4, $score);
    }

    public function test_apply_map_overrides_profile_columns(): void
    {
        $discovery = app(AveisSnmpColumnDiscoveryService::class);
        $merged = $discovery->applyMap(
            ['aveis_onu_mac_column' => 7, 'aveis_onu_rx_column' => 15],
            ['mac' => 9, 'rx' => 16, 'status' => 4, 'label' => 2, 'name' => 11],
        );

        $this->assertSame(9, $merged['aveis_onu_mac_column']);
        $this->assertSame(16, $merged['aveis_onu_rx_column']);
        $this->assertSame(4, $merged['aveis_onu_status_column']);
    }

    public function test_rx_decode_used_by_discovery_scoring(): void
    {
        $this->assertEqualsWithDelta(-14.68, AveisGponOnuSyncService::decodeAveisRx(841), 0.05);
    }
}
