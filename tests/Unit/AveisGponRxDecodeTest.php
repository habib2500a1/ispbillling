<?php

namespace Tests\Unit;

use App\Services\Network\AveisGponOnuSyncService;
use Tests\TestCase;

class AveisGponRxDecodeTest extends TestCase
{
    public function test_col15_divisor_matches_olt_receive_power(): void
    {
        config(['gpon.aveis_rx_mode' => 'col15_divisor', 'gpon.aveis_rx_divisor' => 57.3]);

        $this->assertEqualsWithDelta(-14.67, AveisGponOnuSyncService::decodeAveisRx(841), 0.02);
        $this->assertEqualsWithDelta(-22.15, AveisGponOnuSyncService::decodeAveisRx(1270), 0.05);
    }

    public function test_negative_tenth_legacy_column16(): void
    {
        config([
            'gpon.aveis_rx_mode' => 'negative_tenth',
            'gpon.aveis_rx_raw_min' => 0,
            'gpon.aveis_rx_raw_max' => 0,
            'gpon.aveis_rx_dbm_floor' => -60,
        ]);

        $this->assertEqualsWithDelta(-5.8, AveisGponOnuSyncService::decodeAveisRx(58), 0.01);
    }

    public function test_rejects_olt_fault_codes_as_rx(): void
    {
        config([
            'gpon.aveis_rx_mode' => 'col15_divisor',
            'gpon.aveis_rx_divisor' => 57.3,
            'gpon.aveis_rx_raw_min' => 400,
            'gpon.aveis_rx_raw_max' => 2000,
            'gpon.aveis_rx_dbm_floor' => -35,
        ]);

        $this->assertNull(AveisGponOnuSyncService::decodeAveisRx(2726));
        $this->assertNull(AveisGponOnuSyncService::decodeAveisRx(4001));
        $this->assertNull(AveisGponOnuSyncService::decodeAveisRx(208));
        $this->assertEqualsWithDelta(-14.67, AveisGponOnuSyncService::decodeAveisRx(841), 0.02);
    }
}
