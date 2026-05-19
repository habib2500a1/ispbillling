<?php

namespace Tests;

use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        TenantResolver::resetState();

        parent::tearDown();
    }
}
