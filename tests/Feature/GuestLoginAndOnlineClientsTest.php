<?php

namespace Tests\Feature;

use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use App\Models\User;
use App\Services\Dashboard\DashboardOpsService;
use App\Services\Saas\OperatorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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

    public function test_sold_isp_admin_only_sees_own_online_clients(): void
    {
        Role::findOrCreate('Super Admin', 'web');
        Permission::findOrCreate('mikrotik-setup', 'web');
        Permission::findOrCreate('all-customer', 'web');
        Permission::findOrCreate('saas-sell', 'web');

        $owner = User::factory()->create([
            'name' => 'Platform Owner',
            'email' => 'owner-online@isp.com',
        ]);
        $owner->assignRole('Super Admin');
        $this->actingAs($owner);

        RouterList::create([
            'router_name' => 'anet-r1',
            'ip_address' => '10.0.0.1',
            'username' => 'admin',
            'password' => 'secret',
            'action' => 'connected',
            'ssh_port' => '22',
        ]);
        $platformPpp = PPPSecrets::create([
            'username' => 'habibfree',
            'password' => 'pass',
            'service' => 'pppoe',
            'profile' => '10Mbps',
            'router_name' => 'anet-r1',
            'status' => 'active',
            'uptime' => now(),
        ]);
        CustomersInfo::create([
            'customer_unique_id' => 'FCNET100',
            'customer_name' => 'Platform Client',
            'mobile' => '01700000100',
            'status' => 'active',
            'ppp_user_id' => $platformPpp->id,
        ]);

        $operator = app(OperatorProvisioningService::class)->sell([
            'company' => 'Radiant ISP',
            'contact_name' => 'Radiant Admin',
            'email' => 'radiant-online@isp.com',
            'password' => 'password12',
            'plan' => 'starter',
        ]);
        $buyer = User::where('email', 'radiant-online@isp.com')->firstOrFail();
        $this->actingAs($buyer);

        RouterList::create([
            'router_name' => 'rad-r1',
            'ip_address' => '10.0.0.2',
            'username' => 'admin',
            'password' => 'secret',
            'action' => 'connected',
            'ssh_port' => '22',
        ]);
        $buyerPpp = PPPSecrets::create([
            'username' => 'radiantuser',
            'password' => 'pass',
            'service' => 'pppoe',
            'profile' => '10Mbps',
            'router_name' => 'rad-r1',
            'status' => 'active',
            'uptime' => now(),
        ]);
        CustomersInfo::create([
            'customer_unique_id' => 'RAD100',
            'customer_name' => 'Radiant Client',
            'mobile' => '01700000200',
            'status' => 'active',
            'ppp_user_id' => $buyerPpp->id,
            'saas_operator_id' => $operator->id,
        ]);

        $buyerHtml = $this->actingAs($buyer)->get(route('online-clients'))->assertOk()->getContent();
        $this->assertStringContainsString('radiantuser', $buyerHtml);
        $this->assertStringContainsString('Radiant Client', $buyerHtml);
        $this->assertStringNotContainsString('habibfree', $buyerHtml);
        $this->assertStringNotContainsString('Platform Client', $buyerHtml);

        $this->actingAs($buyer);
        $this->assertSame(1, app(DashboardOpsService::class)->snapshot()['online_clients']);

        $ownerHtml = $this->actingAs($owner)->get(route('online-clients'))->assertOk()->getContent();
        $this->assertStringContainsString('habibfree', $ownerHtml);
        $this->assertStringContainsString('Platform Client', $ownerHtml);
        $this->assertStringNotContainsString('radiantuser', $ownerHtml);
        $this->assertStringNotContainsString('Radiant Client', $ownerHtml);

        $this->actingAs($owner);
        $this->assertSame(1, app(DashboardOpsService::class)->snapshot()['online_clients']);
    }
}
