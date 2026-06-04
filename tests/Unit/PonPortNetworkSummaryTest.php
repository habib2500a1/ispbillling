<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Device;
use App\Models\OltPort;
use App\Models\PonSignalStat;
use App\Services\Optical\PonPortNetworkSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PonPortNetworkSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_pon_row_includes_port_name_mikrotik_and_vlan(): void
    {
        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-T',
            'display_name' => 'Core-OLT',
            'status' => 'active',
        ]);

        $port = OltPort::query()->create([
            'tenant_id' => 1,
            'device_id' => $olt->id,
            'card_index' => 1,
            'pon_index' => 4,
            'label' => 'Mirpur-PON-7',
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Test User',
            'phone' => '01710000001',
            'status' => 'active',
            'meta' => ['vlan' => '200', 'epon_port' => 'EPON0/4:1'],
        ]);

        Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'olt_id' => $olt->id,
            'olt_port_id' => $port->id,
            'card_no' => 1,
            'pon_no' => 4,
            'customer_id' => $customer->id,
            'serial_number' => 'ONU-T1',
            'rx_power_dbm' => -20,
            'onu_oper_status' => 'online',
        ]);

        $stat = PonSignalStat::query()->create([
            'tenant_id' => 1,
            'olt_id' => $olt->id,
            'olt_port_id' => $port->id,
            'card_no' => 1,
            'pon_no' => 4,
            'onu_total' => 1,
            'onu_online' => 1,
            'onu_offline' => 0,
            'avg_rx_dbm' => -20,
            'computed_at' => now(),
        ]);

        $row = PonPortNetworkSummary::toRow($stat);

        $this->assertSame('Mirpur-PON-7', $row['port_name']);
        $this->assertSame('C1/P4', $row['port_index']);
        $this->assertSame('200', $row['vlan']);
        $this->assertStringContainsString('Mirpur-PON-7', $row['port_display']);
    }

    public function test_pon_port_name_from_mikrotik_interface_when_label_is_technical(): void
    {
        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-MK',
            'display_name' => 'Bdcom',
            'status' => 'active',
        ]);

        $port = OltPort::query()->create([
            'tenant_id' => 1,
            'device_id' => $olt->id,
            'card_index' => 0,
            'pon_index' => 3,
            'label' => '0/3',
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'PPP User',
            'phone' => '01710000002',
            'status' => 'active',
            'meta' => [
                'vlan' => '517',
                'mikrotik_interface' => 'KAJLA-BDCOM-OLT-P-3-BOROF GOLLI',
            ],
        ]);

        Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'olt_id' => $olt->id,
            'olt_port_id' => $port->id,
            'card_no' => 0,
            'pon_no' => 3,
            'customer_id' => $customer->id,
            'serial_number' => 'ONU-MK1',
            'onu_oper_status' => 'online',
        ]);

        $stat = PonSignalStat::query()->create([
            'tenant_id' => 1,
            'olt_id' => $olt->id,
            'olt_port_id' => $port->id,
            'card_no' => 0,
            'pon_no' => 3,
            'onu_total' => 1,
            'onu_online' => 1,
            'onu_offline' => 0,
            'computed_at' => now(),
        ]);

        $row = PonPortNetworkSummary::toRow($stat);

        $this->assertSame('KAJLA-BDCOM-OLT-P-3-BOROF GOLLI', $row['port_name']);
        $this->assertSame('517', $row['vlan']);
    }

    public function test_vlan_cell_shows_primary_only_when_multiple_on_pon(): void
    {
        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-V',
            'display_name' => 'Bdcom',
            'status' => 'active',
        ]);

        $stat = PonSignalStat::query()->create([
            'tenant_id' => 1,
            'olt_id' => $olt->id,
            'card_no' => 0,
            'pon_no' => 3,
            'onu_total' => 3,
            'onu_online' => 3,
            'onu_offline' => 0,
            'computed_at' => now(),
        ]);

        foreach ([
            ['vlan' => '517', 'iface' => 'KAJLA-BDCOM-OLT-P-3-A'],
            ['vlan' => '520', 'iface' => 'KAJLA-BDCOM-OLT-P-3-B'],
            ['vlan' => '520', 'iface' => 'KAJLA-BDCOM-OLT-P-3-C'],
        ] as $i => $data) {
            $customer = Customer::query()->create([
                'tenant_id' => 1,
                'name' => 'User '.$i,
                'phone' => '0171000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'status' => 'active',
                'meta' => ['vlan' => $data['vlan'], 'mikrotik_interface' => $data['iface']],
            ]);
            Device::query()->create([
                'tenant_id' => 1,
                'type' => 'onu',
                'olt_id' => $olt->id,
                'card_no' => 0,
                'pon_no' => 3,
                'customer_id' => $customer->id,
                'serial_number' => 'ONU-V'.$i,
                'onu_oper_status' => 'online',
            ]);
        }

        $row = PonPortNetworkSummary::toRow($stat);

        $this->assertSame('520 (+1)', $row['vlan']);
        $this->assertStringContainsString('517', $row['vlan_detail']);
        $this->assertStringContainsString('520', $row['vlan_detail']);
    }
}
