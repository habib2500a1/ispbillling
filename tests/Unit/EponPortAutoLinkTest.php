<?php

namespace Tests\Unit;

use App\Support\EponLabel;
use PHPUnit\Framework\TestCase;

class EponPortAutoLinkTest extends TestCase
{
    public function test_epon_port_normalizes_for_olt_inventory_match(): void
    {
        $this->assertSame('EPON0/4:29', EponLabel::normalize('EPON0/4:29'));
        $this->assertContains('EPON0/4:29', EponLabel::extractFromText('port EPON0/4:29 installed'));
    }
}
