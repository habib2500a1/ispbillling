<?php

namespace Tests\Feature;

use Tests\TestCase;

class VerifyStylesheetsCommandTest extends TestCase
{
    public function test_isp_verify_stylesheets_passes_when_modules_exist(): void
    {
        $this->artisan('isp:verify-stylesheets')
            ->assertSuccessful();
    }
}
