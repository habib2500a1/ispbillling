<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardInsightsRowWidget;
use App\Filament\Widgets\OnlineUsersChartWidget;
use App\Filament\Widgets\RevenueTrendChartWidget;
use App\Models\User;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardRevenueTrendDedupeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_dashboard_hides_standalone_revenue_chart_widgets(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $user->forceFill([
            'dashboard_preferences' => [
                'widgets' => [
                    DashboardInsightsRowWidget::class,
                    RevenueTrendChartWidget::class,
                    OnlineUsersChartWidget::class,
                ],
            ],
        ])->save();

        $visible = Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->instance()
            ->getVisibleWidgets();

        $classes = array_map(
            fn ($w) => $w instanceof \Filament\Widgets\WidgetConfiguration ? $w->widget : $w,
            $visible,
        );

        $this->assertContains(DashboardInsightsRowWidget::class, $classes);
        $this->assertNotContains(RevenueTrendChartWidget::class, $classes);
        $this->assertNotContains(OnlineUsersChartWidget::class, $classes);
        $this->assertSame(1, count(array_filter($classes, fn (string $c) => $c === DashboardInsightsRowWidget::class)));
    }
}
