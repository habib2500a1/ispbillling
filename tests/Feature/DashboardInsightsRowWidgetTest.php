<?php

namespace Tests\Feature;

use App\Filament\Widgets\DashboardInsightsRowWidget;
use App\Models\User;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardInsightsRowWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_insights_row_renders_revenue_and_online_panels(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $html = Livewire::actingAs($user)
            ->test(DashboardInsightsRowWidget::class)
            ->html();

        $this->assertStringContainsString('isp-dash-insights', $html);
        $this->assertStringContainsString('isp-dash-insights--2col', $html);
        $this->assertStringContainsString('Revenue trend', $html);
        $this->assertStringContainsString('Online subscribers', $html);
        $this->assertStringContainsString('isp-rev-chart-table', $html);
        $this->assertStringContainsString('background:#0d9488', $html);
        $this->assertStringContainsString('background:#6366f1', $html);
    }

    public function test_legacy_chart_widgets_map_to_insights_in_preferences(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $user->forceFill([
            'dashboard_preferences' => [
                'widgets' => [
                    \App\Filament\Widgets\RevenueTrendChartWidget::class,
                    \App\Filament\Widgets\OnlineUsersChartWidget::class,
                ],
            ],
        ])->save();

        $widgets = app(\App\Services\Dashboard\DashboardPreferencesService::class)->widgetsFor($user);

        $this->assertSame(
            [\App\Filament\Widgets\DashboardInsightsRowWidget::class],
            $widgets,
        );

        $deduped = \App\Services\Dashboard\DashboardPreferencesService::dedupeInsightsWidget([
            \App\Filament\Widgets\DashboardInsightsRowWidget::class,
            \App\Filament\Widgets\DashboardInsightsRowWidget::class,
            \App\Filament\Widgets\RevenueTrendChartWidget::class,
        ]);

        $this->assertSame(
            [\App\Filament\Widgets\DashboardInsightsRowWidget::class],
            $deduped,
        );
    }
}
