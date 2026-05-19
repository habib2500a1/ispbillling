<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikServerService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class MikrotikPppSecretPasswordTest extends TestCase
{
    #[Test]
    public function blank_customer_password_falls_back_to_server_default(): void
    {
        $customer = new Customer;
        $customer->mikrotik_ppp_password = '';

        $server = new MikrotikServer;
        $server->default_ppp_password = 'bulk-default';

        $method = new ReflectionMethod(MikrotikServerService::class, 'resolvePppSecretPassword');
        $method->setAccessible(true);
        $svc = new MikrotikServerService;

        $this->assertSame('bulk-default', $method->invoke($svc, $customer, $server));
    }

    #[Test]
    public function non_blank_customer_password_wins_over_server_default(): void
    {
        $customer = new Customer;
        $customer->mikrotik_ppp_password = 'per-user';

        $server = new MikrotikServer;
        $server->default_ppp_password = 'bulk-default';

        $method = new ReflectionMethod(MikrotikServerService::class, 'resolvePppSecretPassword');
        $method->setAccessible(true);
        $svc = new MikrotikServerService;

        $this->assertSame('per-user', $method->invoke($svc, $customer, $server));
    }

    #[Test]
    public function returns_null_when_no_password_anywhere(): void
    {
        $customer = new Customer;
        $customer->mikrotik_ppp_password = null;

        $server = new MikrotikServer;
        $server->default_ppp_password = null;

        $method = new ReflectionMethod(MikrotikServerService::class, 'resolvePppSecretPassword');
        $method->setAccessible(true);
        $svc = new MikrotikServerService;

        $this->assertNull($method->invoke($svc, $customer, $server));
    }
}
