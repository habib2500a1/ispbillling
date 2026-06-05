<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerStaff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rbac\RolePermissionService;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerType;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 6 — staff / customer / reseller API permission edge cases.
 */
class ApiPermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    private function syncPermissions(): void
    {
        app(RolePermissionService::class)->syncCatalog();
    }

    private function tenant(string $slug = 'audit-isp'): Tenant
    {
        $tenant = Tenant::query()->create(['name' => 'Audit ISP', 'slug' => $slug, 'is_active' => true]);
        TenantResolver::fake($tenant->id);

        return $tenant;
    }

    private function package(int $tenantId): Package
    {
        return Package::query()->create([
            'tenant_id' => $tenantId,
            'name' => '10 Mbps',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);
    }

    private function billingPayment(Tenant $tenant): Payment
    {
        $package = $this->package($tenant->id);

        $customer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'AUD001',
            'name' => 'Audit Customer',
            'phone' => '01719990001',
            'status' => 'active',
            'billing_day' => 5,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AUD-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 500,
            'status' => 'paid',
        ]);

        return Payment::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'receipt_number' => 'RCP-AUD-1',
        ]);
    }

    public function test_staff_with_permission_only_no_role_can_access_receipt_endpoints(): void
    {
        $this->syncPermissions();
        $tenant = $this->tenant('perm-only-isp');
        $payment = $this->billingPayment($tenant);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->givePermissionTo('billing.view');

        Sanctum::actingAs($user, ['staff']);

        $this->getJson("/api/v1/staff/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertJsonStructure(['data' => ['receipt_number', 'customer', 'amounts']]);

        $this->get("/api/v1/staff/payments/{$payment->id}/receipt-pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_staff_without_billing_permissions_gets_forbidden(): void
    {
        $this->syncPermissions();
        $tenant = $this->tenant('no-perm-isp');
        $payment = $this->billingPayment($tenant);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($user, ['staff']);

        $this->getJson("/api/v1/staff/payments/{$payment->id}/receipt")->assertForbidden();
        $this->getJson('/api/v1/staff/billing/due')->assertForbidden();
    }

    public function test_staff_with_collections_view_but_no_role_can_list_collections(): void
    {
        $this->syncPermissions();
        $tenant = $this->tenant('collections-isp');
        $this->billingPayment($tenant);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->givePermissionTo('collections.view');

        Sanctum::actingAs($user, ['staff']);

        $this->getJson('/api/v1/staff/billing/collections')
            ->assertOk()
            ->assertJsonStructure(['data', 'summary']);
    }

    public function test_customer_api_rejects_invalid_token(): void
    {
        $this->getJson('/api/v1/customer/dashboard')->assertUnauthorized();

        $this->withToken('invalid-token')
            ->getJson('/api/v1/customer/dashboard')
            ->assertUnauthorized();
    }

    public function test_reseller_api_denies_customer_create_without_permission(): void
    {
        Role::findOrCreate('isp-admin');
        $admin = User::factory()->create(['tenant_id' => 1]);
        $admin->assignRole('isp-admin');

        $package = $this->package(1);

        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Limited API Partner',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'is_active' => true,
            'primary_user_id' => $admin->id,
            'portal_password' => Hash::make('api-secret'),
            'portal_permissions' => [ResellerPortalPermission::CUSTOMER_VIEW],
        ]);

        $token = $this->postJson('/api/v1/reseller/login', [
            'login' => $reseller->code,
            'password' => 'api-secret',
            'device_name' => 'test',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->postJson('/api/v1/reseller/customers', [
                'name' => 'Blocked Sub',
                'phone' => '01710009988',
                'address' => 'Test',
                'package_id' => $package->id,
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', ResellerPortalPermission::CUSTOMER_CREATE);
    }

    public function test_reseller_api_denies_payment_collect_without_permission(): void
    {
        Role::findOrCreate('isp-admin');
        $admin = User::factory()->create(['tenant_id' => 1]);
        $admin->assignRole('isp-admin');

        $package = $this->package(1);

        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'View Only Partner',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'is_active' => true,
            'primary_user_id' => $admin->id,
            'portal_password' => Hash::make('api-secret'),
            'portal_permissions' => [
                ResellerPortalPermission::CUSTOMER_VIEW,
                ResellerPortalPermission::BILLING_VIEW,
            ],
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'name' => 'Due Sub',
            'phone' => '01710008877',
            'package_id' => $package->id,
            'customer_code' => 'RSL-DUE',
            'status' => 'active',
            'billing_day' => 5,
        ]);

        $token = $this->postJson('/api/v1/reseller/login', [
            'login' => $reseller->code,
            'password' => 'api-secret',
            'device_name' => 'test',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->postJson("/api/v1/reseller/customers/{$customer->id}/payments", [
                'amount' => 100,
                'method' => 'cash',
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', ResellerPortalPermission::PAYMENT_COLLECT);
    }

    public function test_reseller_staff_login_inherits_limited_portal_permissions(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Staff Perm Partner',
            'code' => 'RSL-STAFF',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'is_active' => true,
            'portal_password' => Hash::make('owner-secret'),
            'portal_permissions' => ResellerPortalPermission::defaultsFor(ResellerType::FRANCHISE),
        ]);

        ResellerStaff::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'name' => 'Desk Staff',
            'login' => 'desk.staff',
            'password' => Hash::make('staff-secret'),
            'portal_permissions' => [ResellerPortalPermission::CUSTOMER_VIEW],
            'is_active' => true,
        ]);

        $token = $this->postJson('/api/v1/reseller/login', [
            'login' => 'desk.staff',
            'password' => 'staff-secret',
            'device_name' => 'test',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/reseller/me')
            ->assertOk()
            ->assertJsonPath('actor.type', 'staff')
            ->assertJsonPath('permissions', [ResellerPortalPermission::CUSTOMER_VIEW]);

        $this->withToken($token)
            ->postJson('/api/v1/reseller/customers', [
                'name' => 'Should Fail',
                'phone' => '01710007766',
                'address' => 'Test',
                'package_id' => $this->package(1)->id,
            ])
            ->assertForbidden();
    }

    public function test_reseller_portal_collect_route_blocked_without_payment_permission(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Portal View Partner',
            'code' => 'RSL-VIEW',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'is_active' => true,
            'portal_password' => Hash::make('secret'),
            'portal_permissions' => [ResellerPortalPermission::CUSTOMER_VIEW],
        ]);

        $package = $this->package(1);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'name' => 'Portal Sub',
            'phone' => '01710006655',
            'package_id' => $package->id,
            'customer_code' => 'RSL-V001',
            'status' => 'active',
            'billing_day' => 5,
        ]);

        $this->actingAs($reseller, 'reseller')
            ->get(route('reseller.customers.collect', $customer))
            ->assertForbidden();
    }

    public function test_admin_permission_matrix_page_loads_for_isp_admin(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get('/admin/permission-matrix')
            ->assertOk()
            ->assertSee('Permission matrix', false);
    }
}
