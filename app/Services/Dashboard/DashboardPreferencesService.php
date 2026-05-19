<?php

namespace App\Services\Dashboard;

use App\Models\User;

final class DashboardPreferencesService
{
    /** @var list<class-string> */
    public const DEFAULT_WIDGETS = [
        \App\Filament\Widgets\DashboardHeroWidget::class,
        \App\Filament\Widgets\ExecutiveKpiGridWidget::class,
        \App\Filament\Widgets\UnifiedOperationsWidget::class,
        \App\Filament\Widgets\SmartOpsCommandCenterWidget::class,
        \App\Filament\Widgets\MikrotikFleetHealthWidget::class,
        \App\Filament\Widgets\MikrotikSessionIntegrityWidget::class,
        \App\Filament\Widgets\DashboardCommandStripWidget::class,
        \App\Filament\Widgets\SubscriberLifecycleWidget::class,
        \App\Filament\Widgets\RevenueTrendChartWidget::class,
        \App\Filament\Widgets\OnlineUsersChartWidget::class,
        \App\Filament\Widgets\BandwidthLiveCompareStatsWidget::class,
        \App\Filament\Widgets\BandwidthLiveChartWidget::class,
        \App\Filament\Widgets\FiberTopologyWidget::class,
    ];

    /** @return list<class-string> */
    public function widgetsFor(?User $user): array
    {
        $prefs = $user?->dashboard_preferences ?? [];
        $saved = $prefs['widgets'] ?? null;

        if (! is_array($saved) || $saved === []) {
            return self::DEFAULT_WIDGETS;
        }

        $allowed = array_flip(self::DEFAULT_WIDGETS);
        $ordered = [];
        foreach ($saved as $class) {
            if (is_string($class) && isset($allowed[$class]) && class_exists($class)) {
                $ordered[] = $class;
            }
        }

        return $ordered !== [] ? $ordered : self::DEFAULT_WIDGETS;
    }

    /**
     * @param  list<class-string>  $widgets
     */
    public function saveWidgets(User $user, array $widgets): void
    {
        $prefs = $user->dashboard_preferences ?? [];
        $prefs['widgets'] = array_values(array_intersect($widgets, self::DEFAULT_WIDGETS));
        $user->forceFill(['dashboard_preferences' => $prefs])->save();
    }

    public function isCompact(?User $user): bool
    {
        return (bool) ($user?->dashboard_preferences['compact'] ?? true);
    }
}
