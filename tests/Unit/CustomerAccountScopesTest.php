<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Support\CustomerAccountScopes;
use App\Support\CustomerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountScopesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function customer(array $overrides = []): Customer
    {
        return Customer::createTrusted(array_merge([
            'tenant_id' => 1,
            'customer_code' => 'c_'.uniqid(),
            'name' => 'Test Client',
            'phone' => '017'.random_int(10000000, 99999999),
            'status' => CustomerStatus::ACTIVE,
        ], $overrides));
    }

    public function test_active_excludes_past_validity(): void
    {
        $this->customer([
            'status' => CustomerStatus::ACTIVE,
            'service_expires_at' => now()->subDay()->toDateString(),
        ]);

        $this->customer([
            'status' => CustomerStatus::ACTIVE,
            'service_expires_at' => now()->addDay()->toDateString(),
        ]);

        $this->assertSame(1, CustomerAccountScopes::applyActive(Customer::query())->count());
    }

    public function test_left_includes_legacy_isp_digital_marker(): void
    {
        $this->customer([
            'status' => CustomerStatus::SUSPENDED,
            'meta' => [
                'isp_digital_raw' => [
                    'Status' => 'Left Customer',
                    'ShortStatus' => 'left',
                ],
            ],
        ]);

        $this->assertSame(1, CustomerAccountScopes::applyLeft(Customer::query())->count());
    }

    public function test_expired_excludes_legacy_left(): void
    {
        $this->customer([
            'status' => CustomerStatus::EXPIRED,
            'service_expires_at' => now()->subDays(3)->toDateString(),
            'meta' => [
                'isp_digital_raw' => [
                    'Status' => 'Left',
                    'ShortStatus' => 'left',
                ],
            ],
        ]);

        $this->assertSame(0, CustomerAccountScopes::applyExpired(Customer::query())->count());
    }
}
