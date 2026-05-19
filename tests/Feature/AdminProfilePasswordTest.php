<?php

namespace Tests\Feature;

use App\Filament\Auth\EditAdminProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_available_to_staff(): void
    {
        $user = User::factory()->create([
            'tenant_id' => 1,
            'password' => Hash::make('old-password-123'),
        ]);
        Role::findOrCreate('isp-admin');
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get(EditAdminProfile::getUrl())
            ->assertOk();
    }

    public function test_staff_can_change_password_from_profile(): void
    {
        $user = User::factory()->create([
            'tenant_id' => 1,
            'password' => Hash::make('old-password-123'),
        ]);
        Role::findOrCreate('isp-admin');
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(EditAdminProfile::class)
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'current_password' => 'old-password-123',
                'password' => 'new-secure-password-9',
                'passwordConfirmation' => 'new-secure-password-9',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertTrue(Hash::check('new-secure-password-9', $user->password));
    }
}
