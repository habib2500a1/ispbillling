<?php

namespace Tests\Feature;

use App\Filament\Widgets\TodaySnapshotWidget;
use App\Models\User;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TodaySnapshotWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_widget_renders_constrained_icons(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $html = Livewire::actingAs($user)
            ->test(TodaySnapshotWidget::class)
            ->html();

        $this->assertStringContainsString('isp-today-strip', $html);
        $this->assertStringContainsString('isp-today-tile__icon', $html);
        $this->assertStringContainsString('width="24"', $html);
        $this->assertStringContainsString('isp-today-tile__icon-svg', $html);
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'isp-today-tile isp-today-tile--'));
    }

    public function test_admin_dashboard_includes_today_snapshot_markup(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('isp-today-snapshot-wi', false)
            ->assertSee('Collected today', false);
    }
}
