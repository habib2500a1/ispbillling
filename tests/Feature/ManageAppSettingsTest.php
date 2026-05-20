<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageAppSettings;
use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_render_integrations_page(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(ManageAppSettings::class)
            ->assertSuccessful();
    }

    public function test_isp_admin_cannot_open_integrations_page(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(ManageAppSettings::class)
            ->assertForbidden();
    }

    public function test_super_admin_save_persists_sms_reminders(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(ManageAppSettings::class)
            ->set('data.sms_reminders_enabled', false)
            ->set('data.sms_reminders_days_before', 5)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('0', AppSetting::getStoredValue('sms.reminders_enabled'));
        $this->assertSame('5', AppSetting::getStoredValue('sms.reminders_days_before'));
        $this->assertDatabaseCount('integration_settings_audits', 1);
    }
}
