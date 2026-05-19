<?php

namespace Tests\Feature;

use App\Support\SnmpClient;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BdcomEponOnuSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_snmp_suffix_parser(): void
    {
        $suffix = SnmpClient::suffixFromOidKey(
            'iso.3.6.1.4.1.3320.101.10.1.1.3.113',
            '1.3.6.1.4.1.3320.101.10.1.1.3',
        );

        $this->assertSame('113', $suffix);
    }

    public function test_bdcom_epon_profile_has_oids(): void
    {
        $profile = config('gpon.profiles.bdcom_epon');

        $this->assertArrayHasKey('bdcom_epon_onu_mac', $profile);
        $this->assertArrayHasKey('bdcom_epon_onu_rx', $profile);
    }
}
