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
}
