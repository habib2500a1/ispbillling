<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\Olt\OltSnmpProbeService;
use PHPUnit\Framework\TestCase;

class OltSnmpProbeServiceTest extends TestCase
{
    public function test_snmp_peer_falls_back_to_management_ip(): void
    {
        $olt = new Device([
            'management_ip' => '192.168.1.10',
            'snmp_host' => null,
            'snmp_port' => 161,
        ]);
        $svc = new OltSnmpProbeService;

        $this->assertSame('192.168.1.10', $svc->snmpPeer($olt));
    }

    public function test_snmp_peer_includes_non_default_port(): void
    {
        $olt = new Device([
            'management_ip' => '10.0.0.1',
            'snmp_port' => 10161,
        ]);
        $svc = new OltSnmpProbeService;

        $this->assertSame('10.0.0.1:10161', $svc->snmpPeer($olt));
    }

    public function test_effective_community_defaults_to_public_when_blank(): void
    {
        $olt = new Device(['snmp_community' => null]);
        $svc = new OltSnmpProbeService;

        $this->assertSame('public', $svc->effectiveCommunity($olt));
    }

    public function test_is_snmp_extension_detection_is_boolean(): void
    {
        $this->assertIsBool(OltSnmpProbeService::isSnmpExtensionAvailable());
    }
}
