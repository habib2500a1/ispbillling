<?php

namespace Tests\Feature;

use App\Filament\Pages\ManagePerformanceSettings;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagePerformanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_open_performance_settings(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get(ManagePerformanceSettings::getUrl())
            ->assertOk()
            ->assertSee('Performance & polling', false);
    }

    public function test_saving_disables_auto_sync_on_customer_view(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(ManagePerformanceSettings::class)
            ->set('data.auto_sync_on_customer_view', false)
            ->set('data.auto_sync_on_customer_save', true)
            ->set('data.legacy_portal_auto_sync', true)
            ->set('data.auto_sync_olt_on_mac_lookup', true)
            ->set('data.customer_sync_connection', 'redis')
            ->set('data.optical_poll_interval', 10)
            ->set('data.bandwidth_poll_interval', 5)
            ->set('data.mikrotik_poll_enabled', true)
            ->set('data.mikrotik_fetch_details_poll', false)
            ->set('data.olt_snmp_poll_enabled', true)
            ->set('data.sync_fast_mode', true)
            ->set('data.bundle_css', true)
            ->set('data.app_settings_cache_seconds', 120)
            ->set('data.max_runner_processes', 1)
            ->set('data.runner_lock_seconds', 1800)
            ->call('save')
            ->assertHasNoErrors();

        AppSetting::syncToRuntimeConfig();

        $this->assertFalse((bool) config('optical.auto_sync_on_customer_view'));
        $this->assertSame('redis', (string) config('optical.customer_sync_connection'));
    }
}
