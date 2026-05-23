<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Services\Olt\OltProvisioningService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OltProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_quick_create_olt_with_huawei_defaults(): void
    {
        $result = app(OltProvisioningService::class)->createQuick(1, [
            'display_name' => 'Test Huawei OLT',
            'management_ip' => '10.10.10.1',
            'snmp_community' => 'private',
            'olt_driver' => 'huawei_gpon',
        ], pollAfterCreate: false);

        $olt = $result['olt'];
        $this->assertSame('olt', $olt->type);
        $this->assertSame('huawei_gpon', $olt->olt_driver);
        $this->assertSame('huawei', $olt->vendor);
        $this->assertSame('huawei_gpon', $olt->gpon_profile);
        $this->assertSame('OLT-10-10-10-1', $olt->serial_number);

        $this->assertSame(1, Device::query()->olts()->count());
    }
}
