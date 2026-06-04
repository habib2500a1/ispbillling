<?php

namespace Tests\Unit;

use App\Models\CallCenterSetting;
use App\Models\Tenant;
use App\Services\CallCenter\PortSipConnectionProfile;
use App\Services\CallCenter\WebSipConfigPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortSipConnectionProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_portsip_profile_matches_bdwebs_example(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'portsip-profile'],
            ['name' => 'PortSIP', 'is_active' => true],
        );

        CallCenterSetting::query()->create([
            'tenant_id' => $tenant->id,
            'websip_enabled' => true,
            'sip_server' => '202.40.176.2',
            'sip_domain' => 'sip17.bdwebs.com',
            'default_extension' => '09617179160',
            'outbound_caller_id' => '09617179160',
            'meta' => [
                'sip_port' => 5060,
                'websip_username' => '09617179160',
                'websip_password' => WebSipConfigPresenter::encryptPassword('secret'),
            ],
        ]);

        $profile = PortSipConnectionProfile::forTenant($tenant->id);
        $this->assertNotNull($profile);
        $this->assertTrue($profile->isConfigured());
        $this->assertSame(5060, $profile->sipPort());
        $this->assertSame('sip17.bdwebs.com', $profile->identityHost());
        $this->assertContains('sip:202.40.176.2', $profile->registrarServers());
        $this->assertNotEmpty($profile->wssUris());
    }
}
