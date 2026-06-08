<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Services\Hr\WorkforceHubDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkforceHubDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_returns_kpi_structure(): void
    {
        Role::findOrCreate('isp-admin');

        Employee::query()->create([
            'tenant_id' => 1,
            'employee_code' => 'EMP001',
            'name' => 'Test Staff',
            'department' => 'Support',
            'designation' => 'Agent',
            'base_salary' => 15000,
            'is_active' => true,
        ]);

        $snapshot = app(WorkforceHubDashboardService::class)->snapshot(1);

        $this->assertArrayHasKey('kpis', $snapshot);
        $this->assertArrayHasKey('total_employees', $snapshot['kpis']);
        $this->assertArrayHasKey('open_tasks', $snapshot['kpis']);
        $this->assertArrayHasKey('hr_analytics', $snapshot);
        $this->assertArrayHasKey('technicians_ops', $snapshot);
    }

    public function test_search_requires_minimum_length(): void
    {
        $results = app(WorkforceHubDashboardService::class)->search('a');

        $this->assertSame([], $results);
    }

    public function test_hr_payroll_hub_page_renders(): void
    {
        Role::findOrCreate('isp-admin');
        $user = \App\Models\User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(\App\Filament\Pages\HrPayrollHub::class)
            ->assertSuccessful()
            ->assertSee('Workforce Operations Center');
    }
}
