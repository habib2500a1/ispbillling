<?php

namespace Tests\Unit;

use App\Support\BandwidthDirection;
use PHPUnit\Framework\TestCase;

class BandwidthDirectionTest extends TestCase
{
    public function test_mikrotik_counters_map_to_subscriber_download_upload(): void
    {
        $mapped = BandwidthDirection::fromMikrotikCounters(
            routerBytesIn: 1_000_000,
            routerBytesOut: 5_000_000,
        );

        $this->assertSame(5_000_000, $mapped['download_bytes']);
        $this->assertSame(1_000_000, $mapped['upload_bytes']);
    }

    public function test_format_bps_shows_kbps_for_low_rates(): void
    {
        $this->assertStringContainsString('Kbps', BandwidthDirection::formatBps(512_000));
    }
}
