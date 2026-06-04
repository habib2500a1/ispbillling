<?php

namespace Tests\Feature;

use App\Models\CallCenterSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallCenter\WebSipConfigPresenter;
use App\Support\WebSipFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebSipLiveCallTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithWebSip(bool $configured = true): User
    {
        Role::findOrCreate('isp-admin');

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default ISP', 'is_active' => true],
        );

        $meta = $configured
            ? [
                'websip_username' => '09617179160',
                'websip_password' => WebSipConfigPresenter::encryptPassword('test-sip-secret'),
                'sip_port' => 5060,
            ]
            : [];

        CallCenterSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'websip_enabled' => true,
                'sip_domain' => 'sip17.bdwebs.com',
                'sip_server' => '202.40.176.2',
                'default_extension' => '09617179160',
                'outbound_caller_id' => '09617179160',
                'meta' => $meta,
            ],
        );

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('isp-admin');

        return $user;
    }

    public function test_presenter_returns_configured_payload(): void
    {
        config(['call_center.websip_enabled' => false, 'call_center.websip_auto_wss_candidates' => true]);

        $user = $this->adminWithWebSip(true);
        $config = app(WebSipConfigPresenter::class)->forUser($user);

        $this->assertNotNull($config);
        $this->assertTrue($config['configured']);
        $this->assertSame('sip17.bdwebs.com', $config['sip_domain']);
        $this->assertSame('09617179160', $config['username']);
        $this->assertArrayHasKey('wss_uris', $config);
        $this->assertStringStartsWith('wss://sip17.bdwebs.com', $config['wss_uris'][0]);
        $this->assertContains('sip:sip17.bdwebs.com', $config['registrar_servers'] ?? []);
        $this->assertSame('sip17.bdwebs.com', $config['identity_host']);
        $this->assertSame('test-sip-secret', $config['password']);
    }

    public function test_presenter_unconfigured_without_password(): void
    {
        config(['call_center.websip_enabled' => false]);

        $user = $this->adminWithWebSip(false);
        $config = app(WebSipConfigPresenter::class)->forUser($user);

        $this->assertNotNull($config);
        $this->assertFalse($config['configured']);
        $this->assertArrayHasKey('wss_uris', $config);
    }

    public function test_admin_dashboard_includes_live_call_fab_without_global_env(): void
    {
        config(['call_center.websip_enabled' => false]);

        $response = $this->actingAs($this->adminWithWebSip(true))
            ->get('/admin');

        if ($response->status() === 308) {
            $response = $this->followRedirects($response);
        }

        $response->assertOk();
        $response->assertSee('data-isp-websip-fab', false);
        $response->assertSee('data-isp-websip-panel', false);
        $response->assertSee('UDP 5060', false);
        $response->assertSee('isp-live-call-fab.css', false);
        $response->assertSee('window.__ispWebSip', false);
    }

    public function test_admin_dashboard_hides_fab_when_websip_disabled(): void
    {
        config(['call_center.websip_enabled' => false]);

        $user = $this->adminWithWebSip(true);
        CallCenterSetting::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->update(['websip_enabled' => false]);

        $response = $this->actingAs($user)->get('/admin');

        if ($response->status() === 308) {
            $response = $this->followRedirects($response);
        }

        $response->assertOk();
        $response->assertDontSee('data-isp-websip-fab', false);
    }

    public function test_sip_settings_page_accessible_for_isp_admin(): void
    {
        $user = $this->adminWithWebSip(false);

        $response = $this->actingAs($user)->get('/admin/manage-call-center-settings');

        $response->assertOk();
        $response->assertSee('PortSIP', false);
    }

    public function test_sip_settings_save_off_keeps_websip_disabled(): void
    {
        $user = $this->adminWithWebSip(true);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Pages\ManageCallCenterSettings::class)
            ->fillForm([
                'websip_enabled' => false,
                'sip_server' => '202.40.176.2',
                'sip_port' => 5060,
                'sip_domain' => 'sip17.bdwebs.com',
                'sip_username' => '09617179160',
                'outbound_caller_id' => '09617179160',
                'wss_uri' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = CallCenterSetting::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->first();

        $this->assertFalse($settings->websip_enabled);
        $this->assertFalse(\App\Support\WebSipFeature::showsLiveCallUi($user));
    }

    public function test_public_assets_exist(): void
    {
        $this->assertFileExists(public_path('js/isp-websip.js'));
        $this->assertFileExists(public_path('css/isp-live-call-fab.css'));
        $this->assertStringContainsString('isp-live-call-fab', file_get_contents(public_path('css/isp-live-call-fab.css')));
        $this->assertStringContainsString('wss_uris', file_get_contents(public_path('js/isp-websip.js')));
    }
}
