<?php

namespace Tests\Feature;

use App\Filament\Pages\ManagePortalSettings;
use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagePortalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_open_customer_portal_settings(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(ManagePortalSettings::class)
            ->assertSuccessful();
    }

    public function test_isp_manager_can_save_portal_otp_ttl(): void
    {
        Role::findOrCreate('isp-manager');
        $user = User::factory()->create();
        $user->assignRole('isp-manager');

        Livewire::actingAs($user)
            ->test(ManagePortalSettings::class)
            ->set('data.portal_otp_enabled', true)
            ->set('data.portal_otp_log_delivery_only', false)
            ->set('data.portal_otp_ttl_seconds', 300)
            ->set('data.portal_otp_digits', 6)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('1', AppSetting::getStoredValue('portal.otp.enabled'));
        $this->assertSame('300', AppSetting::getStoredValue('portal.otp.ttl_seconds'));
        $this->assertSame(300, (int) config('portal.otp.ttl_seconds'));
        $this->assertStringContainsString('Customer portal', IntegrationSettingsAudit::query()->first()->summary);
    }

    public function test_isp_support_cannot_open_portal_settings(): void
    {
        Role::findOrCreate('isp-support');
        $user = User::factory()->create();
        $user->assignRole('isp-support');

        Livewire::actingAs($user)
            ->test(ManagePortalSettings::class)
            ->assertForbidden();
    }
}
