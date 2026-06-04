<?php

namespace Tests\Unit;

use App\Support\MikrotikVlanParser;
use PHPUnit\Framework\TestCase;

class MikrotikVlanParserTest extends TestCase
{
    public function test_parses_vlan_from_secret_comment(): void
    {
        $vlan = MikrotikVlanParser::fromPppSecret([
            'name' => 'user1',
            'comment' => 'EPON0/4:29 vlan=200',
            'profile' => '20M',
            'raw' => [],
        ]);

        $this->assertSame('200', $vlan);
    }

    public function test_parses_vlan_from_profile_name(): void
    {
        $vlan = MikrotikVlanParser::fromPppSecret([
            'name' => 'user1',
            'comment' => null,
            'profile' => 'vlan350_20M',
            'raw' => [],
        ]);

        $this->assertSame('350', $vlan);
    }

    public function test_uses_profile_map_fallback(): void
    {
        $vlan = MikrotikVlanParser::fromPppSecret([
            'name' => 'user1',
            'profile' => 'Home20',
            'raw' => [],
        ], '410');

        $this->assertSame('410', $vlan);
    }

    public function test_parses_raw_vlan_id_field(): void
    {
        $vlan = MikrotikVlanParser::fromPppSecret([
            'name' => 'user1',
            'raw' => ['vlan-id' => '88'],
        ]);

        $this->assertSame('88', $vlan);
    }

    public function test_rejects_invalid_vlan(): void
    {
        $this->assertNull(MikrotikVlanParser::fromText('vlan=0'));
        $this->assertNull(MikrotikVlanParser::fromText('vlan=99999'));
    }

    public function test_parses_vlan_from_olt_interface_label(): void
    {
        $this->assertSame('507', MikrotikVlanParser::fromOltInterfaceLabel('AVIES-OLT-507-KP OFFICE AREA'));
        $this->assertNull(MikrotikVlanParser::fromOltInterfaceLabel('KAJLA-BDCOM-OLT-P-3-BOROF GOLLI'));
    }

    public function test_parses_pon_index_from_olt_interface_label(): void
    {
        $this->assertSame(6, MikrotikVlanParser::ponIndexFromOltInterfaceLabel('KAJLA-BDCOM-OLT-P-6-MOSTOFA LANE-1'));
        $this->assertSame(4, MikrotikVlanParser::ponIndexFromOltInterfaceLabel('KAJLA-BDCOM-OLT-P-4-MUKTI NAGAR'));
        $this->assertNull(MikrotikVlanParser::ponIndexFromOltInterfaceLabel('AVIES-OLT-507-KP OFFICE'));
    }
}
