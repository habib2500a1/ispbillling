<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestLoginAndOnlineClientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_uses_lightweight_guest_bundle(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Sign in', false);
        $html = $response->getContent();

        $this->assertStringContainsString('guest.js', $html);
        $this->assertStringNotContainsString('resources/js/app.js', $html);
        $this->assertStringNotContainsString('livewire.js', $html);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function test_online_clients_page_opens_from_cached_sessions(): void
    {
        $role = Role::create(['name' => 'Super Admin']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-online@isp.com',
            'mobile' => '01711122300',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole($role);

        $started = microtime(true);
        $response = $this->actingAs($admin)->get(route('online-clients'));
        $elapsed = microtime(true) - $started;

        $response->assertOk();
        $response->assertSee('Online Clients');
        $this->assertLessThan(5, $elapsed, 'Online Clients should render from DB without a live MikroTik poll.');
    }
}
