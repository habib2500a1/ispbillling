<?php

namespace Tests\Feature;

use App\Filament\Pages\ManagePersonalMfsSettings;
use App\Models\User;
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
            ->assertSet('data._ui_tab', 'bkash')
            ->assertSee('Personal bKash (Send Money)');
    }

    public function test_nagad_tab_query_selects_nagad_section(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create(['tenant_id' => null]);
        $user->assignRole('super-admin');

        $this->actingAs($user);

        $this->get(ManagePersonalMfsSettings::getUrl(['tab' => 'nagad']))
            ->assertOk()
            ->assertSee('Personal Nagad (Send Money)');
    }
}
