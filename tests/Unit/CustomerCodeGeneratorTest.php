<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Support\CustomerCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_numeric_format_generates_digits_only(): void
    {
        config(['subscriber.code_format' => 'numeric', 'subscriber.numeric_start' => 10001]);

        $code = CustomerCodeGenerator::generate(1);

        $this->assertMatchesRegularExpression('/^\d+$/', $code);
        $this->assertSame('10001', $code);
    }

    public function test_secret_as_code_uses_secret_name(): void
    {
        config(['subscriber.code_format' => 'secret_as_code']);

        $this->assertSame('user12345', CustomerCodeGenerator::generate(1, 'user12345'));
    }

    public function test_numeric_manual_validation(): void
    {
        config(['subscriber.code_format' => 'numeric']);

        $this->assertTrue(CustomerCodeGenerator::isValidManualCode('10099'));
        $this->assertFalse(CustomerCodeGenerator::isValidManualCode('CUST-01'));
    }
}
