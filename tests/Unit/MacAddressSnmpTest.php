<?php

namespace Tests\Unit;

use App\Support\MacAddress;
use App\Support\SnmpClient;
use PHPUnit\Framework\TestCase;

class MacAddressSnmpTest extends TestCase
{
    public function test_from_snmp_hex_string_with_spaces(): void
    {
        $this->assertSame(
            '00:AD:24:F0:FB:3C',
            MacAddress::fromSnmpValue('Hex-STRING: 00 AD 24 F0 FB 3C '),
        );
    }

    public function test_from_snmp_six_byte_octets(): void
    {
        $raw = hex2bin('00AD24F0FB3C');

        $this->assertSame('00:AD:24:F0:FB:3C', MacAddress::fromSnmpValue($raw));
    }

    public function test_from_snmp_compact_hex(): void
    {
        $this->assertSame('00:AD:24:F0:FB:3C', MacAddress::fromSnmpValue('00AD24F0FB3C'));
    }

    public function test_suffix_from_oid_key_with_leading_dot_and_plain_oids(): void
    {
        $base = '1.3.6.1.4.1.3320.101.10.1.1.3';

        $this->assertSame('97', SnmpClient::suffixFromOidKey('.1.3.6.1.4.1.3320.101.10.1.1.3.97', $base));
        $this->assertSame('98', SnmpClient::suffixFromOidKey('iso.3.6.1.4.1.3320.101.10.1.1.3.98', $base));
    }
}
