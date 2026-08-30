<?php

namespace Tests\Feature;

use App\Models\CollectionSummary;
use App\Models\Olt;
use App\Models\SaasInvoice;
use App\Models\SaasOperator;
use App\Models\User;
use App\Services\Saas\OperatorProvisioningService;
use App\Services\Saas\SaasBillingService;
use App\Services\Saas\SaasQuotaException;
use App\Services\Saas\SaasQuotaService;
use App\Services\Saas\StaffCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaasBillingAndStaffCashTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        Permission::findOrCreate('dashboard', 'web');
        Permission::findOrCreate('saas-sell', 'web');

        $owner = User::query()->firstOrCreate(
            ['email' => 'owner-saas@isp.com'],
            [
                'name' => 'Owner',
                'mobile' => '01711122401',
                'password' => bcrypt('password'),
            ]
        );
        $owner->assignRole('Super Admin');

        return $owner;
    }

    private function sellBuyer(string $plan = 'starter'): SaasOperator
    {
        $this->actingAs($this->owner());

        return app(OperatorProvisioningService::class)->sell([
            'company' => 'Buyer ISP',
            'contact_name' => 'Buyer Admin',
            'email' => 'buyer-saas@isp.com',
            'password' => 'password12',
            'plan' => $plan,
            'billing_cycle' => 'monthly',
        ]);
    }

    public function test_sell_creates_monthly_invoice_and_due_date(): void
    {
        $operator = $this->sellBuyer();

        $this->assertSame('monthly', $operator->billing_cycle);
        $this->assertSame(1, (int) $operator->max_olts);
        $this->assertNotNull($operator->next_due_at);
        $this->assertTrue($operator->next_due_at->isFuture());
        $this->assertDatabaseHas('saas_invoices', [
            'saas_operator_id' => $operator->id,
            'status' => 'due',
        ]);
        $this->assertFalse((bool) $operator->can_resell);
    }

    public function test_overdue_operator_is_locked_and_unlocked_after_pay(): void
    {
        $operator = $this->sellBuyer();
        $operator->update(['next_due_at' => now()->subDay()]);
        $invoice = $operator->invoices()->first();
        $invoice->update(['due_at' => now()->subDay(), 'status' => 'due']);

        $billing = app(SaasBillingService::class);
        $billing->refreshLock($operator->fresh());
        $this->assertTrue($operator->fresh()->isAccessBlocked());

        $buyer = User::where('email', 'buyer-saas@isp.com')->first();
        $this->actingAs($buyer);
        $this->get('http://localhost/dashboard')->assertRedirect(route('saas.locked'));

        $this->actingAs($this->owner());
        $billing->markPaid($invoice->fresh(), 'bkash');
        $this->assertSame('active', $operator->fresh()->status);
        $this->assertTrue($operator->fresh()->next_due_at->isFuture());

        $this->actingAs($buyer->fresh());
        $this->get('http://localhost/dashboard')->assertOk();
    }

    public function test_olt_quota_blocks_beyond_plan_limit(): void
    {
        $operator = $this->sellBuyer('starter');
        $buyer = User::where('email', 'buyer-saas@isp.com')->first();
        $this->actingAs($buyer);

        Olt::create(['name' => 'OLT-1', 'saas_operator_id' => $operator->id, 'status' => 'active']);

        $this->expectException(SaasQuotaException::class);
        app(SaasQuotaService::class)->assert('olts');
    }

    public function test_lifetime_sell_has_no_invoice_and_binds_tenant_user(): void
    {
        $this->actingAs($this->owner());

        $operator = app(OperatorProvisioningService::class)->sell([
            'company' => 'Free ISP',
            'contact_name' => 'Free Admin',
            'email' => 'free-saas@isp.com',
            'password' => 'password12',
            'plan' => 'lifetime',
            'billing_cycle' => 'lifetime',
        ]);

        $this->assertTrue($operator->isLifetime());
        $this->assertNull($operator->next_due_at);
        $this->assertSame(0, (int) $operator->amount);
        $this->assertSame(0, $operator->invoices()->count());
        $this->assertSame($operator->id, (int) $operator->user->saas_operator_id);
        $this->assertFalse($operator->user->hasRole('Super Admin'));
        $this->assertFalse($operator->user->can('saas-sell'));

        app(SaasBillingService::class)->refreshLock($operator->fresh());
        $this->assertFalse($operator->fresh()->isAccessBlocked());
    }

    public function test_grant_lifetime_clears_due_and_unlocks(): void
    {
        $operator = $this->sellBuyer();
        $operator->update(['next_due_at' => now()->subDay(), 'status' => 'locked', 'lock_reason' => 'unpaid']);

        app(OperatorProvisioningService::class)->grantLifetime($operator->fresh());

        $fresh = $operator->fresh();
        $this->assertTrue($fresh->isLifetime());
        $this->assertNull($fresh->next_due_at);
        $this->assertSame('active', $fresh->status);
        $this->assertSame(0, $fresh->invoices()->where('status', '!=', 'paid')->count());
    }

    public function test_operator_cannot_see_platform_customers_or_count_them(): void
    {
        $this->actingAs($this->owner());
        \App\Models\CustomersInfo::create([
            'customer_unique_id' => 'ANET-OWNER-1',
            'customer_name' => 'Anetbd Client',
            'mobile' => '01700000999',
            'status' => 'active',
        ]);
        Olt::create(['name' => 'ANET-OLT', 'status' => 'active']);

        $operator = $this->sellBuyer('starter');
        $buyer = User::where('email', 'buyer-saas@isp.com')->first();
        $this->actingAs($buyer);

        $this->assertSame(0, \App\Models\CustomersInfo::query()->count());
        $this->assertSame(0, Olt::query()->count());
        $this->assertSame(0, app(SaasQuotaService::class)->count($operator, 'customers'));
        $this->assertSame(0, app(SaasQuotaService::class)->count($operator, 'olts'));

        \App\Models\CustomersInfo::create([
            'customer_unique_id' => 'BUYER-1',
            'customer_name' => 'Buyer Client',
            'mobile' => '01700000888',
            'status' => 'active',
        ]);

        $this->assertSame(1, \App\Models\CustomersInfo::query()->count());
        $this->assertSame('BUYER-1', \App\Models\CustomersInfo::query()->first()->customer_unique_id);
        $this->assertSame($operator->id, (int) \App\Models\CustomersInfo::query()->first()->saas_operator_id);
    }

    public function test_plan_seed_does_not_overwrite_owner_prices(): void
    {
        app(\App\Services\Saas\SaasPlanCatalog::class)->seed();
        $plan = \App\Models\SaasPlan::query()->where('slug', 'starter')->first();
        $plan->update(['monthly_price' => 1234, 'name' => 'Starter Custom']);

        app(\App\Services\Saas\SaasPlanCatalog::class)->seed();

        $plan->refresh();
        $this->assertSame(1234, (int) $plan->monthly_price);
        $this->assertSame('Starter Custom', $plan->name);
    }

    public function test_staff_cash_shows_collected_minus_deposit(): void
    {
        $operator = $this->sellBuyer();
        $staff = User::create([
            'name' => 'Collector',
            'email' => 'collector@isp.com',
            'mobile' => '01711122402',
            'password' => bcrypt('password'),
            'saas_operator_id' => $operator->id,
        ]);

        CollectionSummary::create([
            'customer_collection_unique_id' => 'CID-STAFF-1',
            'collection_amount' => 1500,
            'collection_date' => now()->toDateString(),
            'collected_by' => $staff->email,
        ]);

        $this->actingAs($this->owner());
        $service = app(StaffCashService::class);
        $service->deposit($staff, 500, now()->toDateString(), 'office');

        $row = collect($service->ledger($operator))->firstWhere(fn ($r) => $r['user']->id === $staff->id);
        $this->assertNotNull($row);
        $this->assertEquals(1500.0, $row['collected']);
        $this->assertEquals(500.0, $row['deposited']);
        $this->assertEquals(1000.0, $row['due']);
    }

    public function test_operator_domain_is_saved_and_tls_ask_allows_it(): void
    {
        $operator = $this->sellBuyer();
        $operator->update(['domain' => 'buyer-isp.test']);

        $this->get('/saas/tls-ask?domain=buyer-isp.test')->assertOk();
        $this->get('/saas/tls-ask?domain=www.buyer-isp.test')->assertOk();
        $this->get('/saas/tls-ask?domain=unknown-isp.test')->assertNotFound();
        $this->assertTrue(\App\Services\Saas\SaasDomain::isAllowedHost('buyer-isp.test'));
        $this->assertFalse(\App\Services\Saas\SaasDomain::isAllowedHost('unknown-isp.test'));
        $this->assertTrue(\App\Services\Saas\SaasDomain::isReserved('anetbd.com'));

        $this->get('http://buyer-isp.test/login')->assertOk();
        $this->get('http://unknown-isp.test/login')->assertRedirect();
    }
}
