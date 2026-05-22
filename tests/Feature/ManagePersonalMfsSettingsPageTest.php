<?php

namespace Tests\Feature;

use App\Filament\Pages\ManagePersonalMfsSettings;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\BkashSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagePersonalMfsSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_render_personal_mfs_settings_page(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create(['tenant_id' => null]);
        $user->assignRole('super-admin');

        $this->actingAs($user);

        Livewire::test(ManagePersonalMfsSettings::class)
            ->assertOk()
            ->assertSet('activeGatewayTab', 'bkash')
            ->assertSee('bKash Personal');
    }

    public function test_nagad_tab_query_selects_nagad_section(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create(['tenant_id' => null]);
        $user->assignRole('super-admin');

        $this->actingAs($user);

        $this->get(ManagePersonalMfsSettings::getUrl(['tab' => 'nagad']))
            ->assertOk()
            ->assertSee('Nagad Personal');
    }

    public function test_saving_bkash_does_not_disable_nagad_personal(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create(['tenant_id' => null]);
        $user->assignRole('super-admin');

        $this->actingAs($user);

        AppSetting::putValues([
            'nagad.gateway_type' => 'personal',
            'nagad.enabled' => '1',
            'nagad.personal_number' => '01722222222',
        ]);

        Livewire::test(ManagePersonalMfsSettings::class)
            ->set('activeGatewayTab', 'bkash')
            ->set('data.bkash_enabled', '1')
            ->set('data.bkash_personal_number', '01711111111')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(config('nagad.enabled'));
        $this->assertSame('personal', config('nagad.gateway_type'));
        $this->assertSame('01722222222', config('nagad.personal_number'));
        $this->assertTrue(config('bkash.enabled'));
        $this->assertSame(BkashSettings::GATEWAY_PERSONAL, config('bkash.gateway_type'));
    }

    public function test_saving_nagad_does_not_disable_bkash_personal(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create(['tenant_id' => null]);
        $user->assignRole('super-admin');

        $this->actingAs($user);

        AppSetting::putValues([
            'bkash.gateway_type' => BkashSettings::GATEWAY_PERSONAL,
            'bkash.enabled' => '1',
            'bkash.personal_number' => '01711111111',
        ]);

        Livewire::test(ManagePersonalMfsSettings::class)
            ->set('activeGatewayTab', 'nagad')
            ->set('data.nagad_enabled', '1')
            ->set('data.nagad_personal_number', '01733333333')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(config('bkash.enabled'));
        $this->assertSame(BkashSettings::GATEWAY_PERSONAL, config('bkash.gateway_type'));
        $this->assertTrue(config('nagad.enabled'));
        $this->assertSame('personal', config('nagad.gateway_type'));
        $this->assertSame('01733333333', config('nagad.personal_number'));
    }
}
