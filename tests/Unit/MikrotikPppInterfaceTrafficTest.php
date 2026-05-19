<?php

namespace Tests\Unit;

use App\Support\CustomerPppLoginResolver;
use PHPUnit\Framework\TestCase;

class MikrotikPppInterfaceTrafficTest extends TestCase
{
    public function test_pppoe_interface_name_parses_login(): void
    {
        $name = '<pppoe-akash.al>';
        $this->assertMatchesRegularExpression('/^<pppoe-(.+)>$/', $name);
        preg_match('/^<pppoe-(.+)>$/', $name, $m);
        $this->assertSame('akash.al', CustomerPppLoginResolver::normalize($m[1]));
    }
}
