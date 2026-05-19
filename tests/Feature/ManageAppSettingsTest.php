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

    public function test_super_admin_save_persists_network_settings(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(ManageAppSettings::class)
            ->set('data.sms_reminders_enabled', false)
            ->set('data.sms_reminders_days_before', 3)
            ->set('data.network_provisioner_driver', 'both')
            ->set('data.network_mikrotik_push_enabled', false)
            ->set('data.network_mikrotik_always_push_ppp_on_customer_save', true)
            ->set('data.network_radius_push_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('both', AppSetting::getStoredValue('network.provisioner_driver'));
        $this->assertSame('0', AppSetting::getStoredValue('network.mikrotik_push_enabled'));
        $this->assertSame('1', AppSetting::getStoredValue('network.mikrotik_always_push_ppp_on_customer_save'));
        $this->assertSame('1', AppSetting::getStoredValue('network.radius_push_enabled'));
        $this->assertDatabaseCount('integration_settings_audits', 1);
        $this->assertStringContainsString('Integrations', IntegrationSettingsAudit::query()->first()->summary);
    }

    public function test_super_admin_can_turn_radius_push_off_without_error(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(ManageAppSettings::class)
            ->set('data.network_radius_push_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('0', AppSetting::getStoredValue('network.radius_push_enabled'));
        $this->assertFalse((bool) config('network.radius_push_enabled'));
    }
}
