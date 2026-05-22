<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSessionLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_classic_post_login_works_without_livewire(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create([
            'email' => 'habib@radiantbd.com',
            'password' => Hash::make('habib@123'),
            'is_active' => true,
        ]);
        $user->assignRole('isp-admin');

        $this->post('/admin/login', [
            'email' => 'habib@radiantbd.com',
            'password' => 'habib@123',
            'remember' => '1',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_page_uses_html_form_not_livewire_submit(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('action="'.route('admin.login.session').'"', false)
            ->assertSee('name="email"', false)
            ->assertDontSee('wire:submit', false);
    }

    public function test_classic_post_rejects_bad_password(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => Hash::make('correct'),
            'is_active' => true,
        ]);
        $user->assignRole('isp-admin');

        $this->from('/admin/login')
            ->post('/admin/login', [
                'email' => 'staff@example.com',
                'password' => 'wrong',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }
}
