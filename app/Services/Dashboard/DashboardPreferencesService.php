<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DashboardPreferencesService
{
    /** @var list<class-string> */
    public const DEFAULT_WIDGETS = [
        \App\Filament\Widgets\BillingExecutiveDashboardWidget::class,
        \App\Filament\Widgets\OperationsCommandCenterWidget::class,
        \App\Filament\Widgets\DashboardCommandStripWidget::class,
        \App\Filament\Widgets\RevenueTrendChartWidget::class,
        \App\Filament\Widgets\OnlineUsersChartWidget::class,
    ];

    /** @return array<class-string, string> */
    public static function layoutWidgetLabels(): array
    {
        return [
            \App\Filament\Widgets\BillingExecutiveDashboardWidget::class => 'Billing overview (KPIs + chart)',
            \App\Filament\Widgets\OperationsCommandCenterWidget::class => 'Operations command center',
            \App\Filament\Widgets\DashboardCommandStripWidget::class => 'Quick tools strip',
            \App\Filament\Widgets\RevenueTrendChartWidget::class => 'Revenue chart',
            \App\Filament\Widgets\OnlineUsersChartWidget::class => 'Online users chart',
        ];
    }

    /** @var array<string, class-string|null> */
    private const LEGACY_WIDGET_MAP = [
        'App\\Filament\\Widgets\\SmartIspDashboardWidget' => \App\Filament\Widgets\OperationsCommandCenterWidget::class,
        'App\\Filament\\Widgets\\DashboardHeroWidget' => null,
        'App\\Filament\\Widgets\\ExecutiveKpiGridWidget' => null,
        'App\\Filament\\Widgets\\UnifiedOperationsWidget' => null,
        'App\\Filament\\Widgets\\SmartOpsCommandCenterWidget' => null,
        'App\\Filament\\Widgets\\DashboardLayoutCustomizer' => null,
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

        if (! in_array(\App\Filament\Widgets\BillingExecutiveDashboardWidget::class, $ordered, true)) {
            array_unshift($ordered, \App\Filament\Widgets\BillingExecutiveDashboardWidget::class);
        }

        if (! in_array(\App\Filament\Widgets\OperationsCommandCenterWidget::class, $ordered, true)) {
            $opsIndex = array_search(\App\Filament\Widgets\BillingExecutiveDashboardWidget::class, $ordered, true);
            array_splice($ordered, $opsIndex === false ? 0 : $opsIndex + 1, 0, [
                \App\Filament\Widgets\OperationsCommandCenterWidget::class,
            ]);
        }

        return $ordered;
    }

    /**
     * @param  list<class-string>  $widgets
     */
    public function saveWidgets(User $user, array $widgets): void
    {
        $this->savePreferences($user, $widgets, null);
    }

    /**
     * Atomically save layout — merges into existing JSON so other keys are not lost.
     *
     * @param  list<class-string>  $widgets
     */
    public function savePreferences(User $user, array $widgets, ?bool $compact = null): void
    {
        DB::transaction(function () use ($user, $widgets, $compact): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $prefs = is_array($locked->dashboard_preferences)
                ? $locked->dashboard_preferences
                : [];

            $prefs['widgets'] = $this->normalizeWidgetList($widgets);

            if ($compact !== null) {
                $prefs['compact'] = $compact;
            }

            $locked->forceFill(['dashboard_preferences' => $prefs])->save();
        });

        $user->refresh();
    }

    /**
     * @param  list<class-string>  $widgets
     * @return list<class-string>
     */
    public function normalizeWidgetList(array $widgets): array
    {
        $allowed = array_flip(self::DEFAULT_WIDGETS);
        $ordered = [];

        foreach ($widgets as $class) {
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

        if (! in_array(\App\Filament\Widgets\BillingExecutiveDashboardWidget::class, $ordered, true)) {
            array_unshift($ordered, \App\Filament\Widgets\BillingExecutiveDashboardWidget::class);
        }

        if (! in_array(\App\Filament\Widgets\OperationsCommandCenterWidget::class, $ordered, true)) {
            $opsIndex = array_search(\App\Filament\Widgets\BillingExecutiveDashboardWidget::class, $ordered, true);
            array_splice($ordered, $opsIndex === false ? 0 : $opsIndex + 1, 0, [
                \App\Filament\Widgets\OperationsCommandCenterWidget::class,
            ]);
        }

        return array_values($ordered);
    }

    public function repairUserPreferences(?User $user): void
    {
        if ($user === null) {
            return;
        }

        $prefs = is_array($user->dashboard_preferences) ? $user->dashboard_preferences : [];
        $saved = $prefs['widgets'] ?? null;

        if (! is_array($saved) || $saved === []) {
            return;
        }

        $repaired = $this->normalizeWidgetList($saved);

        if ($repaired === $saved && ! $this->prefsNeedLegacyStrip($saved)) {
            return;
        }

        $prefs['widgets'] = $repaired;
        $user->forceFill(['dashboard_preferences' => $prefs])->save();
    }

    /**
     * @param  list<mixed>  $saved
     */
    private function prefsNeedLegacyStrip(array $saved): bool
    {
        foreach ($saved as $class) {
            if (! is_string($class)) {
                return true;
            }

            if (array_key_exists($class, self::LEGACY_WIDGET_MAP) && self::LEGACY_WIDGET_MAP[$class] !== $class) {
                return true;
            }
        }

        return false;
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
