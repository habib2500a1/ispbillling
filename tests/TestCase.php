<?php

namespace Tests;

use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\CreatesTestActors;

abstract class TestCase extends BaseTestCase
{
    use CreatesTestActors;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeTenant(1);
        config([
            'portal.enabled' => true,
            'call_center.enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        TenantResolver::resetState();

        parent::tearDown();
    }
}
