<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Support\OnuEnvironmentalMetrics;
use Tests\TestCase;

class OnuEnvironmentalMetricsTest extends TestCase
{
    public function test_parses_temperature_and_voltage_from_meta(): void
    {
        $onu = new Device([
            'type' => 'onu',
            'meta' => [
                'temperature_c' => 38,
                'voltage_v' => 3.28,
            ],
        ]);

        $env = OnuEnvironmentalMetrics::fromDevice($onu);

        $this->assertSame(38.0, $env['temperature_c']);
        $this->assertSame(3.28, $env['voltage_v']);
        $this->assertSame('38 °C', OnuEnvironmentalMetrics::formatTemperature($env['temperature_c']));
        $this->assertSame('3.28 V', OnuEnvironmentalMetrics::formatVoltage($env['voltage_v']));
    }

    public function test_reads_optical_sub_array(): void
    {
        $onu = new Device([
            'type' => 'onu',
            'meta' => [
                'optical' => ['temperature' => 42.5, 'voltage' => 3.1],
            ],
        ]);

        $env = OnuEnvironmentalMetrics::fromDevice($onu);

        $this->assertSame(42.5, $env['temperature_c']);
        $this->assertSame(3.1, $env['voltage_v']);
    }
}
