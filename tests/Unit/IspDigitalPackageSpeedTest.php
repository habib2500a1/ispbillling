<?php

namespace Tests\Unit;

use App\Support\IspDigitalPackageSpeed;
use PHPUnit\Framework\TestCase;

class IspDigitalPackageSpeedTest extends TestCase
{
    public function test_parses_display_and_mikrotik_profile(): void
    {
        $parsed = IspDigitalPackageSpeed::parse([
            'Package' => '25Mbps',
            'PackageSpeed' => '25Mbps/Packages>>1',
        ]);

        $this->assertSame('25Mbps', $parsed['display_name']);
        $this->assertSame('Packages>>1', $parsed['mikrotik_profile']);
    }

    public function test_uses_package_speed_label_when_package_missing(): void
    {
        $parsed = IspDigitalPackageSpeed::parse([
            'PackageSpeed' => '40 Mbps/Packages>>>2',
        ]);

        $this->assertSame('40 Mbps', $parsed['display_name']);
        $this->assertSame('Packages>>>2', $parsed['mikrotik_profile']);
    }

    public function test_package_only_without_slash(): void
    {
        $parsed = IspDigitalPackageSpeed::parse([
            'Package' => '10Mbps',
        ]);

        $this->assertSame('10Mbps', $parsed['display_name']);
        $this->assertNull($parsed['mikrotik_profile']);
    }
}
