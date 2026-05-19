<?php

namespace Tests\Feature;

use App\Filament\Pages\ManagePaymentSettings;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\BkashSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagePaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_save_bkash_settings(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(ManagePaymentSettings::class)
            ->set('data.bkash_environment', BkashSettings::ENV_SANDBOX)
            ->set('data.bkash_app_key', 'test_app_key')
            ->set('data.bkash_app_secret', 'test_secret')
            ->set('data.bkash_username', '01900000000')
            ->set('data.bkash_password', 'test_pass')
            ->set('data.bkash_http_timeout', 30)
            ->set('data.bkash_callback_url', 'https://pay.example.com/bkash/callback')
            ->set('data.bkash_enabled', '1')
            ->set('data.bkash_channels', BkashSettings::allChannels())
            ->call('save')
            ->assertHasNoErrors();

        AppSetting::syncToRuntimeConfig();

        $this->assertTrue(config('bkash.enabled'));
        $this->assertSame(BkashSettings::SANDBOX_BASE_URL, config('bkash.base_url'));
        $this->assertSame('test_app_key', config('bkash.app_key'));
        $this->assertSame('https://pay.example.com/bkash/callback', config('bkash.callback_url'));
        $this->assertTrue(BkashSettings::isEnabledForChannel(BkashSettings::CHANNEL_PUBLIC_PAY));
    }

    public function test_live_environment_sets_production_base_url(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(ManagePaymentSettings::class)
            ->set('data.bkash_environment', BkashSettings::ENV_LIVE)
            ->set('data.bkash_app_key', 'live_key')
            ->set('data.bkash_app_secret', 'live_secret')
            ->set('data.bkash_username', '01900000000')
            ->set('data.bkash_password', 'live_pass')
            ->set('data.bkash_http_timeout', 30)
            ->set('data.bkash_enabled', '1')
            ->set('data.bkash_channels', [BkashSettings::CHANNEL_ADMIN])
            ->call('save');

        AppSetting::syncToRuntimeConfig();

        $this->assertSame(BkashSettings::LIVE_BASE_URL, config('bkash.base_url'));
        $this->assertFalse(BkashSettings::isEnabledForChannel(BkashSettings::CHANNEL_PUBLIC_PAY));
        $this->assertTrue(BkashSettings::isEnabledForChannel(BkashSettings::CHANNEL_ADMIN));
    }
}
