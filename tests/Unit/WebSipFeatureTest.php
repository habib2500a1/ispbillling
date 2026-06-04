<?php

namespace Tests\Unit;

use App\Models\CallCenterSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\WebSipFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSipFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_websip_enabled_without_global_env(): void
    {
        config(['call_center.websip_enabled' => false]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'websip-tenant'],
            ['name' => 'WebSIP ISP', 'is_active' => true],
        );

        CallCenterSetting::query()->create([
            'tenant_id' => $tenant->id,
            'websip_enabled' => true,
            'sip_domain' => 'sip17.bdwebs.com',
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue(WebSipFeature::isEnabledForUser($user));
    }

    public function test_global_env_requires_tenant_toggle_on(): void
    {
        config(['call_center.websip_enabled' => true]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'websip-global'],
            ['name' => 'Global WebSIP', 'is_active' => true],
        );

        CallCenterSetting::forTenant($tenant->id);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse(WebSipFeature::isEnabledForUser($user));

        CallCenterSetting::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->update(['websip_enabled' => true]);

        $this->assertTrue(WebSipFeature::isEnabledForUser($user));
    }

    public function test_tenant_off_hides_ui_even_when_global_env_on(): void
    {
        config(['call_center.websip_enabled' => true]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'websip-tenant-off'],
            ['name' => 'Tenant Off', 'is_active' => true],
        );

        CallCenterSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['websip_enabled' => false, 'sip_domain' => 'sip17.bdwebs.com'],
        );

        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse(WebSipFeature::showsLiveCallUi($user));
    }

    public function test_disabled_when_neither_global_nor_tenant(): void
    {
        config(['call_center.websip_enabled' => false]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'websip-off'],
            ['name' => 'Off ISP', 'is_active' => true],
        );

        CallCenterSetting::forTenant($tenant->id);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse(WebSipFeature::isEnabledForUser($user));
    }

    public function test_sanitize_sip_host_strips_slashes(): void
    {
        $this->assertSame('sip17.bdwebs.com', WebSipFeature::sanitizeSipHost('\\sip17.bdwebs.com'));
        $this->assertSame('sip17.bdwebs.com', WebSipFeature::sanitizeSipHost('sip17.bdwebs.com/'));
    }

    public function test_super_admin_without_tenant_uses_default_tenant_settings(): void
    {
        config(['call_center.websip_enabled' => false]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'websip-default-tenant'],
            ['name' => 'Default', 'is_active' => true],
        );

        CallCenterSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['websip_enabled' => true, 'sip_domain' => 'sip17.bdwebs.com'],
        );

        $user = User::factory()->create(['tenant_id' => null]);

        if ($tenant->id === 1) {
            $this->assertTrue(WebSipFeature::isEnabledForUser($user));
        } else {
            $this->markTestSkipped('Default tenant is not id 1 in this database.');
        }
    }

    public function test_global_env_without_tenant_user_needs_default_tenant_toggle(): void
    {
        config(['call_center.websip_enabled' => true]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'websip-default-tenant-2'],
            ['name' => 'Default', 'is_active' => true],
        );

        CallCenterSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['websip_enabled' => true, 'sip_domain' => 'sip17.bdwebs.com'],
        );

        $user = User::factory()->create(['tenant_id' => null]);

        if ($tenant->id !== (int) config('isp.default_tenant_id', 1)) {
            $this->markTestSkipped('Resolver default tenant id mismatch.');
        }

        $this->assertTrue(WebSipFeature::isEnabledForUser($user));
    }
}
