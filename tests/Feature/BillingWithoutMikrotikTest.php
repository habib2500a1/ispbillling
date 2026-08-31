<?php

namespace Tests\Feature;

use App\Http\Controllers\MikrotikController;
use App\Models\CustomersInfo;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingWithoutMikrotikTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        foreach (['all-customer', 'edit-customer', 'mikrotik-setup', 'enable-pending-customer'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $admin = User::create([
            'name' => 'Offline Admin',
            'email' => 'offline-admin@isp.com',
            'mobile' => '01711122400',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('Super Admin');
        $admin->givePermissionTo(['all-customer', 'edit-customer', 'mikrotik-setup', 'enable-pending-customer']);

        return $admin;
    }

    private function disconnectedRouter(): RouterList
    {
        return RouterList::create([
            'router_name' => 'nur-sajedul',
            'ip_address' => '127.0.0.1',
            'username' => 'pending',
            'password' => 'pending',
            'action' => 'disconnected',
            'api_port' => 8728,
            'ssh_port' => 22,
        ]);
    }

    public function test_mikrotik_controller_skips_disconnected_router_without_throwing(): void
    {
        $this->disconnectedRouter();
        $ctrl = app(MikrotikController::class);

        $this->assertFalse($ctrl->isRouterConnected('nur-sajedul'));
        $this->assertSame([], $ctrl->singleRead('nur-sajedul', '/ppp/secret/print', '/ppp secret print'));
        $this->assertSame(MikrotikController::OFFLINE, $ctrl->singleWrite('nur-sajedul', '/ppp secret print'));

        $ctrl->enablePPPSecret('27000', 'nur-sajedul', 'as-shamiul.18km');
        $this->assertSame(MikrotikController::OFFLINE, $ctrl->disablePPPSecret('27000', 'nur-sajedul', 'as-shamiul.18km'));
        $ctrl->updatePPPSecret('nur-sajedul', 'as-shamiul.18km', 'profile', 'P-1');
    }

    public function test_staff_pages_open_with_disconnected_router_and_no_error_toast(): void
    {
        $admin = $this->staff();
        $this->disconnectedRouter();
        PackageList::create([
            'package' => 'P-1',
            'price' => 500,
            'router_name' => 'nur-sajedul',
        ]);
        $ppp = PPPSecrets::create([
            'username' => 'as-shamiul.18km',
            'password' => 'R27000',
            'service' => 'pppoe',
            'profile' => 'Packages>>1',
            'router_name' => 'nur-sajedul',
            'status' => 'active',
        ]);
        CustomersInfo::create([
            'customer_unique_id' => '27000',
            'customer_name' => 'Md shamiuln islam',
            'mobile' => '01732218813',
            'status' => 'active',
            'ppp_user_id' => $ppp->id,
        ]);

        $this->actingAs($admin);

        foreach ([
            $this->get('http://localhost/dashboard'),
            $this->get(route('online-clients')),
            $this->get(route('customers.index')),
        ] as $response) {
            $response->assertOk();
            $response->assertDontSee('not connected', false);
            $response->assertDontSee('Both API and SSH', false);
            $response->assertDontSee('Connection refused', false);
            $response->assertDontSee('Failed to connect to MikroTik', false);
        }
    }
}
