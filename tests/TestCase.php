<?php

namespace Tests;

use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('route:clear');
    }

    protected function tearDown(): void
    {
        TenantResolver::resetState();

        parent::tearDown();
    }
}
