<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Services\Accounting\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrManagementPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_all_hr_management_pages(): void
    {
        $admin = $this->makeAdminUser();

        $paths = [
            '/admin/hr-payroll-hub',
            '/admin/employees',
            '/admin/attendance-records',
            '/admin/hr-leave-management',
            '/admin/hr-advance-salary',
            '/admin/hr-payroll-generation',
            '/admin/hr-salary-policies',
            '/admin/hr-salary-policies',
            '/admin/hr-reports',
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_leave_request_page_lists_employee_leave(): void
    {
        $admin = $this->makeAdminUser();
        $employee = Employee::query()->create([
            'tenant_id' => 1,
            'name' => 'Leave Test',
            'base_salary' => 10000,
            'is_active' => true,
        ]);

        app(\App\Services\Hr\EmployeeLeaveRequestService::class)->create([
            'employee_id' => $employee->id,
            'leave_type' => 'annual',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'reason' => 'Family event',
        ]);

        $this->actingAs($admin)
            ->get('/admin/hr-leave-management')
            ->assertOk()
            ->assertSee('Leave Test')
            ->assertSee('New Leave Request');
    }

    public function test_advance_salary_request_updates_wallet_balance(): void
    {
        $employee = Employee::query()->create([
            'tenant_id' => 1,
            'name' => 'Advance Test',
            'employee_code' => 'EMP-0099',
            'base_salary' => 15000,
            'wallet_balance' => 0,
            'is_active' => true,
        ]);

        app(\App\Services\Hr\EmployeeAdvanceSalaryService::class)->createRequest([
            'employee_id' => $employee->id,
            'amount' => 2500,
            'request_date' => now()->toDateString(),
            'purpose' => 'Emergency',
            'return_type' => \App\Models\EmployeeAdvanceSalaryRequest::RETURN_NEXT_SALARY,
            'deduction_month' => now()->format('Y-m-01'),
        ]);

        $employee->refresh();
        $this->assertEqualsWithDelta(2500.0, (float) $employee->wallet_balance, 0.01);
        $this->assertDatabaseHas('employee_advance_salary_requests', [
            'employee_id' => $employee->id,
            'amount' => 2500,
        ]);
    }

    public function test_payroll_generation_still_works(): void
    {
        Employee::query()->create([
            'tenant_id' => 1,
            'name' => 'Payroll Staff',
            'base_salary' => 12000,
            'is_active' => true,
        ]);

        $run = app(PayrollService::class)->generateDraft((int) now()->month, (int) now()->year);

        $this->assertSame('draft', $run->status);
        $this->assertGreaterThan(0, (float) $run->total_net);
    }
}
