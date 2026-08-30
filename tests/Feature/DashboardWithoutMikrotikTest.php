<?php

namespace Tests\Feature;

use App\Models\RouterList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardWithoutMikrotikTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::create(['name' => 'Super Admin']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@isp.com',
            'mobile' => '01711122299',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole($role);

        return $admin;
    }

    public function test_dashboard_opens_without_any_mikrotik_router(): void
    {
        $this->actingAs($this->admin());

        $started = microtime(true);
        $response = $this->get('http://localhost/dashboard');
        $elapsed = microtime(true) - $started;

        $response->assertOk();
        $response->assertSee('Client Summary');
        $this->assertLessThan(5, $elapsed, 'Dashboard should open quickly when no MikroTik is configured.');
    }

    public function test_dashboard_opens_when_mikrotik_is_marked_connected_but_offline(): void
    {
        foreach (['10.10.20.1', '10.10.10.1', '10.9.9.9'] as $i => $ip) {
            RouterList::create([
                'router_name' => 'offline-lab-'.$i,
                'ip_address' => $ip,
                'username' => 'admin',
                'password' => 'admin',
                'action' => 'connected',
                'api_port' => 8728,
                'ssh_port' => 22,
            ]);
        }

        $this->actingAs($this->admin());

        $started = microtime(true);
        $response = $this->get('http://localhost/dashboard');
        $elapsed = microtime(true) - $started;

        $response->assertOk();
        $response->assertSee('Client Summary');
        $response->assertDontSee('Both API and SSH');
        $response->assertDontSee('Connection timed out');
        $response->assertDontSee('Error 110');
        $this->assertLessThan(3, $elapsed, 'Unreachable MikroTik must not be probed on dashboard load.');
    }

    public function test_login_redirects_to_dashboard_without_error(): void
    {
        $admin = $this->admin();

        $login = $this->post('http://localhost/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $login->assertRedirect();

        $dashboard = $this->get('http://localhost/dashboard');
        $dashboard->assertOk();
        $dashboard->assertDontSee('Both API and SSH');
        $dashboard->assertDontSee('not connected');
    }
}
