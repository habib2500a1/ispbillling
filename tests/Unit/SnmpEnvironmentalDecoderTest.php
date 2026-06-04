<?php

namespace Tests\Unit;

use App\Support\SnmpEnvironmentalDecoder;
use Tests\TestCase;

class SnmpEnvironmentalDecoderTest extends TestCase
{
    public function test_bdcom_temperature_uses_one_over_256_celsius(): void
    {
        // 38 °C → raw 9728 (38 * 256)
        $this->assertSame(38.0, SnmpEnvironmentalDecoder::temperatureC(9728, 'bdcom_epon'));
    }

    public function test_bdcom_voltage_uses_100_microvolts(): void
    {
        // 3.30 V → raw 33000 (33000 * 100 µV)
        $this->assertSame(3.3, SnmpEnvironmentalDecoder::voltageV(33000, 'bdcom_epon'));
    }
}
