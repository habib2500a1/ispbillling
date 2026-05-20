<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Support\SubscriberIdSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberIdSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_mode_does_not_auto_assign_code(): void
    {
        config(['subscriber.auto_generate_customer_code' => false]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Manual ID',
            'phone' => '01711112222',
            'status' => 'active',
            'customer_code' => 'MY-5001',
        ]);

        $this->assertSame('MY-5001', $customer->customer_code);
    }

    public function test_auto_mode_assigns_code_when_blank(): void
    {
        config([
            'subscriber.auto_generate_customer_code' => true,
            'subscriber.code_format' => 'numeric',
            'subscriber.numeric_start' => 20001,
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Auto ID',
            'phone' => '01711113333',
            'status' => 'active',
        ]);

        $this->assertSame('20001', $customer->customer_code);
    }

    public function test_auto_generate_enabled_reads_config(): void
    {
        config(['subscriber.auto_generate_customer_code' => false]);
        $this->assertFalse(SubscriberIdSettings::autoGenerateEnabled());

        config(['subscriber.auto_generate_customer_code' => true]);
        $this->assertTrue(SubscriberIdSettings::autoGenerateEnabled());
    }
}
