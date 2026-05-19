<?php

namespace Tests\Unit;

use App\Support\MikrotikOpticalHints;
use PHPUnit\Framework\TestCase;

class MikrotikOpticalHintsTest extends TestCase
{
    public function test_parses_epon_and_mac_from_secret_comment(): void
    {
        $hints = MikrotikOpticalHints::fromPppSecret([
            'name' => 'user1',
            'comment' => 'EPON0/4:29 ONU 00AD24F0FB3C',
            'raw' => [
                'last-caller-id' => '00:AD:24:F0:FB:3C',
            ],
        ]);

        $this->assertContains('EPON0/4:29', $hints['epon_ports']);
        $this->assertContains('00AD24F0FB3C', $hints['mac_compacts']);
        $this->assertSame('00:AD:24:F0:FB:3C', $hints['last_caller_id']);
    }
}
