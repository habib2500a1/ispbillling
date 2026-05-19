<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportHubPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_open_support_hub(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get('/admin/support-hub')
            ->assertOk()
            ->assertSee('Support center', false)
            ->assertSee('Open tickets', false)
            ->assertSee('All tickets', false);
    }
}
