<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\Finance\FinanceHubDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceHubDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_returns_kpi_structure(): void
    {
        Role::findOrCreate('isp-admin');

        Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => null,
            'amount' => 500,
            'status' => 'completed',
            'method' => 'cash',
            'paid_at' => now(),
        ]);

        $snapshot = app(FinanceHubDashboardService::class)->snapshot();

        $this->assertArrayHasKey('kpis', $snapshot);
        $this->assertArrayHasKey('total_revenue', $snapshot['kpis']);
        $this->assertArrayHasKey('today_collection', $snapshot['kpis']);
        $this->assertArrayHasKey('bank_balance', $snapshot['kpis']);
        $this->assertArrayHasKey('isp_analytics', $snapshot);
        $this->assertArrayHasKey('clv_proxy', $snapshot['isp_analytics']);
    }

    public function test_search_requires_minimum_length(): void
    {
        $results = app(FinanceHubDashboardService::class)->search('a');

        $this->assertSame([], $results);
    }

    public function test_gl_counts_structure(): void
    {
        $counts = app(FinanceHubDashboardService::class)->glCounts(1);

        $this->assertArrayHasKey('accounts', $counts);
        $this->assertArrayHasKey('journals', $counts);
        $this->assertArrayHasKey('banks', $counts);
        $this->assertArrayHasKey('vendors', $counts);
    }

    public function test_accounting_hub_page_renders(): void
    {
        Role::findOrCreate('isp-admin');
        $user = $this->makeStaffUser();

        Livewire::actingAs($user)
            ->test(\App\Filament\Pages\AccountingHub::class)
            ->assertSuccessful()
            ->assertSee('Finance Operations Center');
    }

    private function makeStaffUser(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        return $user;
    }
}
