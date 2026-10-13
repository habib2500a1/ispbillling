<?php

namespace Tests\Feature;

use App\Livewire\Admin\LoginLogViewer;
use App\Livewire\Admin\PppoeAudit;
use App\Livewire\Admin\WaiverManagement;
use App\Livewire\Admin\WalletApprovals;
use App\Livewire\Reseller\ResellerWalletManagement;
use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\MikrotikLog;
use App\Models\Reseller;
use App\Models\ResellerWalletApproval;
use App\Models\RouterList;
use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoAdminFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName, string $email): User
    {
        Role::findOrCreate($roleName, 'web');
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($roleName);

        return $user;
    }

    public function test_admin_can_apply_audited_waiver_without_waiving_more_than_due(): void
    {
        $admin = $this->userWithRole('Super Admin', 'waiver-admin@example.test');
        $customer = CustomersInfo::create([
            'customer_unique_id' => 'WAIVER-100',
            'customer_name' => 'Waiver Test Customer',
            'mobile' => '01700000100',
            'status' => 'active',
        ]);
        BillingInfo::create([
            'customer_bill_unique_id' => $customer->customer_unique_id,
            'billing_type' => 'prepaid',
            'monthly_rent' => 500,
            'due_amount' => 500,
            'total_amount' => 500,
            'discount' => 0,
        ]);

        $this->actingAs($admin);

        Livewire::test(WaiverManagement::class)
            ->set('customerUniqueId', $customer->customer_unique_id)
            ->set('amount', 125)
            ->set('reason', 'Service interruption credit')
            ->call('applyWaiver')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('billing_waivers', [
            'customer_unique_id' => $customer->customer_unique_id,
            'amount' => 125,
            'reason' => 'Service interruption credit',
            'waived_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('billing_infos', [
            'customer_bill_unique_id' => $customer->customer_unique_id,
            'due_amount' => 375,
            'total_amount' => 375,
            'discount' => 125,
        ]);

        Livewire::test(WaiverManagement::class)
            ->set('customerUniqueId', $customer->customer_unique_id)
            ->set('amount', 400)
            ->set('reason', 'Amount exceeds the outstanding due')
            ->call('applyWaiver')
            ->assertHasErrors('amount');

        $this->assertDatabaseCount('billing_waivers', 1);
    }

    public function test_reseller_topup_request_requires_admin_approval_before_credit(): void
    {
        $admin = $this->userWithRole('Super Admin', 'wallet-admin@example.test');
        $resellerUser = $this->userWithRole('Reseller', 'wallet-reseller@example.test');
        $reseller = Reseller::create([
            'user_id' => $resellerUser->id,
            'company' => 'Wallet Test Reseller',
            'commission_percentage' => 10,
            'balance' => 50,
            'status' => 'active',
        ]);

        $this->actingAs($resellerUser);
        Livewire::test(ResellerWalletManagement::class)
            ->set('topUpAmount', 250)
            ->set('paymentMethod', 'bkash')
            ->set('paymentReference', 'TXN-ABC-123')
            ->call('submitTopUpRequest')
            ->assertHasNoErrors();

        $approval = ResellerWalletApproval::where('reseller_id', $reseller->id)->firstOrFail();
        $this->assertSame('pending', $approval->status);
        $this->assertEquals(50, (float) $reseller->fresh()->balance);

        $this->actingAs($admin);
        Livewire::test(WalletApprovals::class)
            ->assertSee('Wallet Test Reseller')
            ->call('approve', $approval->id)
            ->assertHasNoErrors();

        $this->assertSame('approved', $approval->fresh()->status);
        $this->assertEquals(300, (float) $reseller->fresh()->balance);
        $this->assertDatabaseHas('reseller_wallet_transactions', [
            'reseller_id' => $reseller->id,
            'type' => 'credit',
            'amount' => 250,
            'reference_type' => 'wallet_approval',
            'reference_id' => $approval->id,
        ]);
    }

    public function test_pppoe_audit_shows_only_ppp_related_events_from_configured_routers(): void
    {
        $admin = $this->userWithRole('Super Admin', 'pppoe-audit-admin@example.test');
        RouterList::create([
            'router_name' => 'audit-router',
            'ip_address' => '192.0.2.10',
            'username' => 'audit-user',
            'password' => 'router-password',
            'action' => 'connected',
        ]);
        MikrotikLog::create([
            'router_name' => 'audit-router',
            'topics' => 'ppp,info',
            'message' => 'alice logged in via pppoe',
            'buffer' => 'memory',
        ]);
        MikrotikLog::create([
            'router_name' => 'audit-router',
            'topics' => 'system,info',
            'message' => 'router restarted',
            'buffer' => 'memory',
        ]);

        $this->actingAs($admin);
        Livewire::test(PppoeAudit::class)
            ->assertSee('alice logged in via pppoe')
            ->assertDontSee('router restarted');
    }

    public function test_login_activity_lists_authentication_events(): void
    {
        $admin = $this->userWithRole('Super Admin', 'login-activity-admin@example.test');
        $staff = User::factory()->create(['name' => 'Login Activity Staff', 'email' => 'login-activity-staff@example.test']);
        UserLoginLog::create([
            'authenticatable_id' => $staff->id,
            'authenticatable_type' => User::class,
            'username' => $staff->email,
            'ip_address' => '192.0.2.22',
            'user_agent' => 'Test browser',
            'action' => 'login',
        ]);

        $this->actingAs($admin);
        Livewire::test(LoginLogViewer::class)
            ->assertSee('login-activity-staff@example.test')
            ->assertSee('192.0.2.22')
            ->set('action', 'logout')
            ->assertDontSee('login-activity-staff@example.test');
    }
}
