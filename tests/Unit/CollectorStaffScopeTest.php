<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Collector\CollectorStaffResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class CollectorStaffScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_report_scope_is_own_user_only(): void
    {
        Role::findOrCreate('cashier', 'web');

        $cashier = User::factory()->create(['tenant_id' => 1]);
        $cashier->assignRole('cashier');
        $cashier->givePermissionTo(['payments.add', 'collections.view']);

        $this->actingAs($cashier);

        $this->assertSame($cashier->id, app(CollectorStaffResolver::class)->scopedCollectorIdForReports());
        $this->assertFalse(app(CollectorStaffResolver::class)->canPickCollector());
    }

    public function test_admin_can_pick_collector_and_sees_all_reports(): void
    {
        Role::findOrCreate('isp-admin', 'web');

        $admin = User::factory()->create(['tenant_id' => 1]);
        $admin->assignRole('isp-admin');

        $this->actingAs($admin);

        $this->assertNull(app(CollectorStaffResolver::class)->scopedCollectorIdForReports());
        $this->assertTrue(app(CollectorStaffResolver::class)->canPickCollector());
    }

    public function test_payment_belongs_to_collector_via_meta_or_recorded_by(): void
    {
        $collector = User::factory()->create(['tenant_id' => 1]);
        $other = User::factory()->create(['tenant_id' => 1]);
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Scope Test',
            'phone' => '01710001111',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        $byRecorded = Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 100,
            'method' => 'cash',
            'recorded_by' => $collector->id,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $byMeta = Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 200,
            'method' => 'cash',
            'recorded_by' => $other->id,
            'status' => 'completed',
            'paid_at' => now(),
            'meta' => ['collector_attributed_to' => $collector->id],
        ]);

        $resolver = app(CollectorStaffResolver::class);

        $third = User::factory()->create(['tenant_id' => 1]);

        $this->assertTrue($resolver->paymentBelongsToCollector($byRecorded, $collector->id));
        $this->assertTrue($resolver->paymentBelongsToCollector($byMeta, $collector->id));
        $this->assertFalse($resolver->paymentBelongsToCollector($byRecorded, $third->id));
        $this->assertFalse($resolver->paymentBelongsToCollector($byMeta, $third->id));
    }
}
