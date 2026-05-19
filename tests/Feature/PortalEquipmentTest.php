<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalEquipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_olt_and_onu_on_equipment_page(): void
    {
        $package = Package::query()->create([
            'name' => 'Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Eq User',
            'phone' => '017'.random_int(10000000, 99999999),
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'portal_password' => Hash::make('secret'),
        ]);

        $olt = Device::query()->create([
            'type' => 'olt',
            'display_name' => 'POP-Gulshan-1',
            'location' => 'Gulshan',
            'serial_number' => 'OLT-SN-'.uniqid(),
            'management_ip' => '10.0.0.1',
            'status' => 'active',
        ]);

        $pon = \App\Models\OltPort::query()->create([
            'tenant_id' => 1,
            'device_id' => $olt->id,
            'card_index' => 0,
            'pon_index' => 3,
            'label' => '0/3',
        ]);

        Device::query()->create([
            'type' => 'onu',
            'serial_number' => 'ONU-SN-'.uniqid(),
            'customer_id' => $customer->id,
            'olt_id' => $olt->id,
            'olt_port_id' => $pon->id,
            'card_no' => 0,
            'pon_no' => 3,
            'onu_index' => 12,
            'onu_oper_status' => 'offline',
            'offline_reason' => 'ONT power loss at customer premises.',
            'status' => 'assigned',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('portal.equipment.index'))
            ->assertOk()
            ->assertSee('POP-Gulshan-1', false)
            ->assertSee('Gulshan', false)
            ->assertSee('10.0.0.1', false)
            ->assertSee('0/3', false)
            ->assertSee('Offline', false)
            ->assertSee('ONT power loss', false);
    }
}
