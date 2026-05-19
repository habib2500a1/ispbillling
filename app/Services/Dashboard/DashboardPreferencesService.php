<?php

namespace App\Services\Dashboard;

use App\Models\User;

final class DashboardPreferencesService
{
    /** @var list<class-string> */
    public const DEFAULT_WIDGETS = [
        \App\Filament\Widgets\OperationsCommandCenterWidget::class,
        \App\Filament\Widgets\DashboardCommandStripWidget::class,
        \App\Filament\Widgets\RevenueTrendChartWidget::class,
        \App\Filament\Widgets\OnlineUsersChartWidget::class,
    ];

    /** @var array<string, class-string|null> */
    private const LEGACY_WIDGET_MAP = [
        'App\\Filament\\Widgets\\SmartIspDashboardWidget' => \App\Filament\Widgets\OperationsCommandCenterWidget::class,
        'App\\Filament\\Widgets\\DashboardHeroWidget' => null,
        'App\\Filament\\Widgets\\ExecutiveKpiGridWidget' => null,
        'App\\Filament\\Widgets\\UnifiedOperationsWidget' => null,
        'App\\Filament\\Widgets\\SmartOpsCommandCenterWidget' => null,
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
            if (! is_string($class)) {
                continue;
            }

            $class = self::LEGACY_WIDGET_MAP[$class] ?? $class;

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            if (isset($allowed[$class]) && ! in_array($class, $ordered, true)) {
                $ordered[] = $class;
            }
        }

        if ($ordered === []) {
            return self::DEFAULT_WIDGETS;
        }

        if (! in_array(\App\Filament\Widgets\OperationsCommandCenterWidget::class, $ordered, true)) {
            array_unshift($ordered, \App\Filament\Widgets\OperationsCommandCenterWidget::class);
        }

        return $ordered;
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

    /**
     * Fix stored preferences after widget renames (safe to run repeatedly).
     */
    public function migrateStoredPreferences(): int
    {
        $updated = 0;

        User::query()
            ->whereNotNull('dashboard_preferences')
            ->each(function (User $user) use (&$updated): void {
                $prefs = $user->dashboard_preferences ?? [];
                $saved = $prefs['widgets'] ?? null;

                if (! is_array($saved) || $saved === []) {
                    return;
                }

                $migrated = false;
                $newWidgets = [];

                foreach ($saved as $class) {
                    if (! is_string($class)) {
                        $migrated = true;
                        continue;
                    }

                    $mapped = self::LEGACY_WIDGET_MAP[$class] ?? $class;

                    if ($mapped === null || ! class_exists($mapped)) {
                        $migrated = true;
                        continue;
                    }

                    if (! in_array($mapped, $newWidgets, true)) {
                        $newWidgets[] = $mapped;
                    }

                    if ($mapped !== $class) {
                        $migrated = true;
                    }
                }

                if (! in_array(\App\Filament\Widgets\OperationsCommandCenterWidget::class, $newWidgets, true)) {
                    array_unshift($newWidgets, \App\Filament\Widgets\OperationsCommandCenterWidget::class);
                    $migrated = true;
                }

                $newWidgets = array_values(array_intersect($newWidgets, self::DEFAULT_WIDGETS));

                if ($newWidgets === []) {
                    $newWidgets = self::DEFAULT_WIDGETS;
                    $migrated = true;
                }

                if ($migrated) {
                    $prefs['widgets'] = $newWidgets;
                    $user->forceFill(['dashboard_preferences' => $prefs])->save();
                    $updated++;
                }
            });

        return $updated;
    }
}
