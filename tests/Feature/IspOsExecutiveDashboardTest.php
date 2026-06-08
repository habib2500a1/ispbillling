<?php

namespace Tests\Feature;

use App\Services\IspOs\GlobalOperationsSearchService;
use App\Services\IspOs\IspOsExecutiveDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IspOsExecutiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_snapshot_structure(): void
    {
        $snapshot = app(IspOsExecutiveDashboardService::class)->snapshot(1);

        $this->assertArrayHasKey('executive_kpis', $snapshot);
        $this->assertArrayHasKey('command_centers', $snapshot);
        $this->assertArrayHasKey('intelligence', $snapshot);
        $this->assertNotEmpty($snapshot['command_centers']);
    }

    public function test_global_ops_search_requires_two_chars(): void
    {
        $this->assertSame([], app(GlobalOperationsSearchService::class)->search('a'));
    }

    public function test_isp_os_hub_renders(): void
    {
        Role::findOrCreate('isp-admin');
        $user = \App\Models\User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(\App\Filament\Pages\IspOsHub::class)
            ->assertSuccessful()
            ->assertSee('Executive command center')
            ->assertSee('Command centers');
    }
}
