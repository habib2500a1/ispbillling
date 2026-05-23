<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\Network\HuaweiGponOnuSyncService;
use Tests\TestCase;

class HuaweiGponOnuSyncTest extends TestCase
{
    public function test_parse_huawei_index(): void
    {
        $parts = HuaweiGponOnuSyncService::parseHuaweiIndex('0.1.2.5');

        $this->assertNotNull($parts);
        $this->assertSame(1, $parts['card_no']);
        $this->assertSame(2, $parts['pon_no']);
        $this->assertSame(5, $parts['onu_index']);
    }

    public function test_supports_huawei_driver(): void
    {
        $olt = new Device([
            'type' => 'olt',
            'olt_driver' => 'huawei_gpon',
            'vendor' => 'huawei',
        ]);

        $this->assertTrue(app(HuaweiGponOnuSyncService::class)->supportsDriver($olt));
    }
}
