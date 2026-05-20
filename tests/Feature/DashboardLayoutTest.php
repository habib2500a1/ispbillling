<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Services\Dashboard\DashboardPreferencesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_dashboard_layout_persists_widget_order(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $reordered = [
            \App\Filament\Widgets\OnlineUsersChartWidget::class,
            \App\Filament\Widgets\BillingExecutiveDashboardWidget::class,
            \App\Filament\Widgets\OperationsCommandCenterWidget::class,
        ];

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->set('layoutOrder', $reordered)
            ->set('layoutCompact', false)
            ->call('saveDashboardLayout')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame($reordered, $user->dashboard_preferences['widgets']);
        $this->assertFalse($user->dashboard_preferences['compact']);
        $this->assertSame(
            $reordered,
            app(DashboardPreferencesService::class)->widgetsFor($user),
        );
    }

    public function test_save_preserves_extra_preference_keys(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create([
            'dashboard_preferences' => [
                'widgets' => DashboardPreferencesService::DEFAULT_WIDGETS,
                'compact' => true,
                'custom_note' => 'keep-me',
            ],
        ]);
        $user->assignRole('super-admin');

        app(DashboardPreferencesService::class)->savePreferences(
            $user,
            [
                \App\Filament\Widgets\RevenueTrendChartWidget::class,
                \App\Filament\Widgets\BillingExecutiveDashboardWidget::class,
                \App\Filament\Widgets\OperationsCommandCenterWidget::class,
            ],
            false,
        );

        $user->refresh();

        $this->assertSame('keep-me', $user->dashboard_preferences['custom_note'] ?? null);
        $this->assertFalse($user->dashboard_preferences['compact']);
    }

    public function test_layout_widget_labels_match_defaults_only(): void
    {
        $labels = DashboardPreferencesService::layoutWidgetLabels();

        $this->assertSame(
            array_keys($labels),
            DashboardPreferencesService::DEFAULT_WIDGETS,
        );
    }
}
