<?php

namespace Tests\Feature;

use App\Models\StaffExpense;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Expenses\StaffExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_submits_office_expense_pending_approval(): void
    {
        Role::findOrCreate('cashier');
        $staff = User::factory()->create(['tenant_id' => 1]);
        $staff->assignRole('cashier');

        app(StaffExpenseService::class)->ensureDefaultCategories(1);
        $categoryId = \App\Models\StaffExpenseCategory::query()
            ->where('code', 'office_rent')
            ->value('id');

        $expense = app(StaffExpenseService::class)->submit([
            'expense_source' => StaffExpense::SOURCE_OFFICE,
            'category_id' => (int) $categoryId,
            'amount' => 1500,
            'description' => 'May rent',
        ], $staff);

        $this->assertSame(StaffExpense::STATUS_PENDING, $expense->status);
        $this->assertSame(1500.0, (float) $expense->amount);
    }

    public function test_admin_approves_vendor_expense_and_creates_vendor_payment(): void
    {
        Role::findOrCreate('isp-admin');
        $admin = User::factory()->create(['tenant_id' => 1]);
        $admin->assignRole('isp-admin');

        $vendor = Vendor::query()->create([
            'tenant_id' => 1,
            'name' => 'Fiber Supplier',
            'is_active' => true,
        ]);

        app(StaffExpenseService::class)->ensureDefaultCategories(1);
        $categoryId = \App\Models\StaffExpenseCategory::query()
            ->where('code', 'vendor_goods')
            ->value('id');

        $expense = StaffExpense::query()->create([
            'tenant_id' => 1,
            'expense_number' => StaffExpense::generateNumber(1),
            'expense_source' => StaffExpense::SOURCE_VENDOR,
            'vendor_id' => $vendor->id,
            'category_id' => $categoryId,
            'amount' => 2500,
            'payment_method' => 'bank',
            'status' => StaffExpense::STATUS_PENDING,
            'expense_date' => now()->toDateString(),
            'submitted_by' => $admin->id,
        ]);

        $approved = app(StaffExpenseService::class)->approve($expense, $admin->id);

        $this->assertSame(StaffExpense::STATUS_APPROVED, $approved->status);
        $this->assertNotNull($approved->meta['vendor_payment_id'] ?? null);
        $this->assertDatabaseHas('vendor_payments', [
            'vendor_id' => $vendor->id,
            'amount' => 2500,
            'status' => 'completed',
        ]);
    }

    public function test_vendor_expense_with_new_vendor_inline(): void
    {
        Role::findOrCreate('isp-admin');
        $admin = User::factory()->create(['tenant_id' => 1]);
        $admin->assignRole('isp-admin');

        app(StaffExpenseService::class)->ensureDefaultCategories(1);
        $categoryId = \App\Models\StaffExpenseCategory::query()
            ->where('code', 'vendor_goods')
            ->value('id');

        $vendor = Vendor::query()->create([
            'tenant_id' => 1,
            'name' => 'Cable Shop',
            'is_active' => true,
        ]);

        $expense = app(StaffExpenseService::class)->submit([
            'expense_source' => StaffExpense::SOURCE_VENDOR,
            'vendor_id' => $vendor->id,
            'category_id' => (int) $categoryId,
            'amount' => 800,
            'description' => 'Fiber cable',
        ], $admin);

        $this->assertSame(StaffExpense::SOURCE_VENDOR, $expense->expense_source);
        $this->assertSame($vendor->id, $expense->vendor_id);
    }

    public function test_staff_expense_filament_page_loads(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get(\App\Filament\Resources\StaffExpenseResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Staff expenses', false);
    }
}
