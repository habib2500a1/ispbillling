<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Subscribers\CustomerLineActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerLineActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_line_activation_creates_invoice_device_link_and_wallet_debit(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');
        $this->actingAs($user);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'LINE'.random_int(1000, 9999),
            'name' => 'Line Activation Test',
            'phone' => '01720000001',
            'status' => 'active',
            'account_balance' => 500,
        ]);

        $device = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'display_name' => 'Test ONU',
            'serial_number' => 'SN-'.random_int(10000, 99999),
            'status' => 'in_stock',
            'lease_monthly_fee' => 200,
        ]);

        $result = app(CustomerLineActivationService::class)->activate($customer, [
            'line_charge' => 300,
            'device_id' => $device->id,
            'device_charge' => 200,
            'use_wallet' => true,
            'notes' => 'New fiber line',
        ]);

        $this->assertEquals(500.0, (float) $result['activation']->total_charged);
        $this->assertEquals(500.0, (float) $result['wallet_applied']);
        $this->assertNotNull($result['invoice']);
        $this->assertSame((int) $device->id, (int) $result['device']?->id);
        $this->assertSame((int) $customer->id, (int) $device->fresh()->customer_id);

        $customer->refresh();
        $this->assertSame(0.0, (float) $customer->account_balance);
        $this->assertSame('completed', $customer->meta['installation_status'] ?? '');
        $this->assertSame(300.0, (float) ($customer->meta['installation_charge'] ?? 0));

        $invoice = Invoice::query()->find($result['invoice']->id);
        $this->assertNotNull($invoice);
        $this->assertLessThan(0.01, $invoice->balanceDue());
    }

    public function test_partial_wallet_and_cash_collection(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'LINE'.random_int(1000, 9999),
            'name' => 'Partial Pay',
            'phone' => '01720000002',
            'status' => 'active',
            'account_balance' => 200,
        ]);

        $result = app(CustomerLineActivationService::class)->activate($customer, [
            'line_charge' => 500,
            'use_wallet' => true,
            'cash_amount' => 300,
            'cash_method' => 'cash',
        ]);

        $this->assertEquals(200.0, (float) $result['wallet_applied']);
        $this->assertEquals(300.0, (float) $result['cash_collected']);
        $this->assertLessThan(0.01, (float) $result['remaining_due']);
        $this->assertSame(0.0, (float) $customer->fresh()->account_balance);
    }

    public function test_register_form_triggers_activation_input(): void
    {
        $service = app(CustomerLineActivationService::class);

        $this->assertTrue($service->shouldActivateFromRegisterForm([
            'apply_line_charges' => true,
            'meta' => ['installation_charge' => 400],
            'onu_device_pick' => null,
            'line_device_charge' => 0,
        ]));

        $input = $service->inputFromRegisterForm([
            'apply_line_charges' => true,
            'meta' => ['installation_charge' => 400],
            'onu_device_pick' => '12',
            'line_device_charge' => 100,
            'use_wallet_on_register' => true,
            'line_cash_amount' => 50,
        ]);

        $this->assertEquals(400.0, $input['line_charge']);
        $this->assertSame(12, $input['device_id']);
        $this->assertEquals(100.0, $input['device_charge']);
        $this->assertEquals(50.0, $input['cash_amount']);
    }
}
