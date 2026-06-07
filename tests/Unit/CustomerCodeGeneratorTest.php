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

    public function test_numeric_continues_after_manual_id_even_when_start_is_higher(): void
    {
        config(['subscriber.code_format' => 'numeric', 'subscriber.numeric_start' => 10001]);

        Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Manual Anchor',
            'customer_code' => '101',
            'status' => 'active',
        ]);

        $this->assertSame('102', CustomerCodeGenerator::generate(1));
    }

    public function test_numeric_uses_start_when_no_codes_exist(): void
    {
        config(['subscriber.code_format' => 'numeric', 'subscriber.numeric_start' => 101]);

        $this->assertSame('101', CustomerCodeGenerator::generate(1));
    }
}
