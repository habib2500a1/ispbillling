<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mobile\StaffBillingMobileService;
use App\Services\Mobile\StaffMobileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileStaffBillingTest extends TestCase
{
    use RefreshDatabase;

    private function staffUser(Tenant $tenant): User
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('super-admin');

        return $user;
    }

    private function seedBillingScenario(Tenant $tenant): array
    {
        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => '20M',
            'type' => 'residential',
            'download_mbps' => 20,
            'price_monthly' => 800,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $dueCustomer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'DUE001',
            'name' => 'Due Customer',
            'phone' => '01710000001',
            'status' => 'active',
            'billing_day' => 5,
            'package_id' => $package->id,
        ]);

        Invoice::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_id' => $dueCustomer->id,
            'invoice_number' => 'INV-DUE-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 800,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 800,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $paidCustomer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'PAID001',
            'name' => 'Paid Customer',
            'phone' => '01710000002',
            'status' => 'active',
            'billing_day' => 5,
            'package_id' => $package->id,
        ]);

        $paidInvoice = Invoice::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_id' => $paidCustomer->id,
            'invoice_number' => 'INV-PAID-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 50,
            'total' => 500,
            'amount_paid' => 500,
            'status' => 'paid',
        ]);

        Payment::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_id' => $paidCustomer->id,
            'invoice_id' => $paidInvoice->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => null,
        ]);

        return compact('dueCustomer', 'paidCustomer', 'package');
    }

    public function test_billing_summary_matches_dashboard(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Billing ISP', 'slug' => 'billing-isp', 'is_active' => true]);
        $user = $this->staffUser($tenant);
        $this->seedBillingScenario($tenant);

        Sanctum::actingAs($user, ['staff']);

        $dashboard = $this->getJson('/api/v1/staff/dashboard')->assertOk()->json('billing');
        $summary = $this->getJson('/api/v1/staff/billing/summary')->assertOk()->json('billing');

        $this->assertSame($dashboard['monthly_bill'], $summary['monthly_bill']);
        $this->assertSame($dashboard['collected_bill'], $summary['collected_bill']);
        $this->assertSame($dashboard['due'], $summary['due']);
    }

    public function test_billing_due_list_returns_customer_with_balance(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Due ISP', 'slug' => 'due-isp', 'is_active' => true]);
        $user = $this->staffUser($tenant);
        $seed = $this->seedBillingScenario($tenant);

        Sanctum::actingAs($user, ['staff']);

        $this->getJson('/api/v1/staff/billing/due')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']])
            ->assertJsonFragment(['customer_code' => 'DUE001', 'balance_due' => 800.0])
            ->assertJsonMissing(['customer_code' => 'PAID001']);
    }

    public function test_billing_invoices_filters_due_and_paid(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Inv ISP', 'slug' => 'inv-isp', 'is_active' => true]);
        $user = $this->staffUser($tenant);
        $this->seedBillingScenario($tenant);

        Sanctum::actingAs($user, ['staff']);

        $due = $this->getJson('/api/v1/staff/billing/invoices?status=due')->assertOk()->json('data');
        $this->assertNotEmpty($due);
        $this->assertTrue(collect($due)->contains(fn ($r) => $r['invoice_number'] === 'INV-DUE-1'));

        $paid = $this->getJson('/api/v1/staff/billing/invoices?status=paid')->assertOk()->json('data');
        $this->assertTrue(collect($paid)->contains(fn ($r) => $r['invoice_number'] === 'INV-PAID-1'));
    }

    public function test_billing_collections_lists_payment(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Col ISP', 'slug' => 'col-isp', 'is_active' => true]);
        $user = $this->staffUser($tenant);
        $this->seedBillingScenario($tenant);

        Sanctum::actingAs($user, ['staff']);

        $body = $this->getJson('/api/v1/staff/billing/collections')->assertOk()->json();

        $this->assertArrayHasKey('summary', $body);
        $this->assertGreaterThanOrEqual(500.0, (float) ($body['summary']['month_collected'] ?? 0));
        $this->assertNotEmpty($body['data']);
        $this->assertSame('PAID001', $body['data'][0]['customer_code'] ?? collect($body['data'])->firstWhere('customer_code', 'PAID001')['customer_code'] ?? null);
    }

    public function test_staff_can_record_payment_via_api(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Pay ISP', 'slug' => 'pay-isp', 'is_active' => true]);
        $user = $this->staffUser($tenant);
        $seed = $this->seedBillingScenario($tenant);

        Sanctum::actingAs($user, ['staff']);

        $invoice = Invoice::query()->where('customer_id', $seed['dueCustomer']->id)->first();

        $response = $this->postJson('/api/v1/staff/payments', [
            'customer_id' => $seed['dueCustomer']->id,
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'method' => 'cash',
            'notes' => 'Partial collection test — balance remains.',
        ]);
        $response
            ->assertCreated()
            ->assertJsonPath('payment.amount', 400);

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertEqualsWithDelta(400.0, (float) $invoice->amount_paid, 0.01);
    }

    public function test_customer_search_includes_balance_due(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Search ISP', 'slug' => 'search-isp', 'is_active' => true]);
        $user = $this->staffUser($tenant);
        $this->seedBillingScenario($tenant);

        Sanctum::actingAs($user, ['staff']);

        $this->getJson('/api/v1/staff/customers/search?q=DUE001')
            ->assertOk()
            ->assertJsonFragment(['customer_code' => 'DUE001'])
            ->assertJsonPath('data.0.balance_due', 800);
    }

    public function test_billing_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/staff/billing/summary')->assertUnauthorized();
        $this->getJson('/api/v1/staff/billing/due')->assertUnauthorized();
        $this->getJson('/api/v1/staff/billing/invoices')->assertUnauthorized();
        $this->getJson('/api/v1/staff/billing/collections')->assertUnauthorized();
    }

    public function test_staff_can_extend_service_via_api(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Ext ISP', 'slug' => 'ext-isp', 'is_active' => true]);
        $user = $this->staffUser($tenant);
        $seed = $this->seedBillingScenario($tenant);
        $customer = $seed['dueCustomer'];
        $customer->update(['service_expires_at' => now()->subDay()->toDateString()]);

        Sanctum::actingAs($user, ['staff']);

        $this->postJson("/api/v1/staff/customers/{$customer->id}/extend-service", ['days' => 30])
            ->assertOk()
            ->assertJsonStructure(['service_expires_at', 'expire_day']);

        $customer->refresh();
        $this->assertTrue($customer->service_expires_at->isFuture());
    }

    public function test_due_list_includes_mobile_card_fields(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Due ISP', 'slug' => 'due-isp', 'is_active' => true]);
        $user = $this->staffUser($tenant);
        $this->seedBillingScenario($tenant);

        Sanctum::actingAs($user, ['staff']);

        $row = $this->getJson('/api/v1/staff/billing/due')
            ->assertOk()
            ->json('data.0');

        $this->assertArrayHasKey('username', $row);
        $this->assertArrayHasKey('expire_day', $row);
        $this->assertArrayHasKey('network_on', $row);
        $this->assertArrayHasKey('monthly_bill', $row);
    }
}
