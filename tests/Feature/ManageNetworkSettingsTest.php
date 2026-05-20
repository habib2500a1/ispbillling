<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageNetworkSettings;
use App\Models\AppSetting;
use App\Services\Network\NetworkSettingsConfigurator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageNetworkSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_save_mikrotik_only_mode(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(ManageNetworkSettings::class)
            ->set('data.network_setup_mode', NetworkSettingsConfigurator::MODE_MIKROTIK)
            ->set('data.bandwidth_collection_enabled', true)
            ->set('data.mikrotik_poll_enabled', true)
            ->set('data.radius_accounting_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(NetworkSettingsConfigurator::MODE_MIKROTIK, AppSetting::getStoredValue('network.setup_mode'));
        $this->assertSame('1', AppSetting::getStoredValue('bandwidth.collection_enabled'));
        $this->assertSame('0', AppSetting::getStoredValue('radius.accounting_enabled'));
    }

    public function test_super_admin_can_save_radius_db_in_panel(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(ManageNetworkSettings::class)
            ->set('data.network_setup_mode', NetworkSettingsConfigurator::MODE_RADIUS)
            ->set('data.radius_accounting_enabled', true)
            ->set('data.radius_db_host', '10.0.0.5')
            ->set('data.radius_db_database', 'radius_test')
            ->set('data.radius_db_username', 'raduser')
            ->set('data.radius_db_password', 'secret-pass')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('10.0.0.5', AppSetting::getStoredValue('radius.db.host'));
        $this->assertSame('radius_test', AppSetting::getStoredValue('radius.db.database'));
    }
}
