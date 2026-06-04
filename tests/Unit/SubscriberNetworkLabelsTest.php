<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Device;
use App\Models\MikrotikServer;
use App\Models\OltPort;
use App\Support\SubscriberNetworkLabels;
use Tests\TestCase;

class SubscriberNetworkLabelsTest extends TestCase
{
    public function test_uses_olt_port_label_when_set(): void
    {
        $onu = new Device([
            'type' => 'onu',
            'card_no' => 1,
            'pon_no' => 4,
            'onu_index' => 29,
        ]);
        $onu->setRelation('oltPort', new OltPort(['label' => 'EPON0/4:29']));

        $this->assertSame('EPON0/4:29', SubscriberNetworkLabels::ponPortLabel($onu));
    }

    public function test_uses_customer_epon_port_and_vlan_mikrotik(): void
    {
        $customer = new Customer([
            'meta' => ['epon_port' => 'PON-Mirpur-7', 'vlan' => '200'],
        ]);
        $customer->setRelation('mikrotikServer', new MikrotikServer(['name' => 'Core-MK-01']));

        $onu = new Device(['type' => 'onu', 'card_no' => 0, 'pon_no' => 1]);

        $this->assertSame('PON-Mirpur-7', SubscriberNetworkLabels::ponPortLabel($onu, $customer));
        $this->assertSame('Core-MK-01', SubscriberNetworkLabels::mikrotikName($customer));
        $this->assertSame('200', SubscriberNetworkLabels::vlan($customer));
    }

    public function test_vlan_from_onu_fdb_when_customer_meta_empty(): void
    {
        $customer = new Customer(['meta' => []]);
        $onu = new Device([
            'type' => 'onu',
            'meta' => [
                'pon_mac_entries' => [
                    ['mac' => 'B0:19:21:12:30:1D', 'vlan' => 515, 'type' => 'dynamic'],
                ],
            ],
        ]);

        $this->assertSame('515', SubscriberNetworkLabels::vlan($customer, $onu));
    }
}
