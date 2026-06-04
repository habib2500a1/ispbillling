<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Support\Rbac\StaffCapability;
use Illuminate\Support\Facades\DB;

final class DashboardPreferencesService
{
    /** @var list<class-string> */
    public const DEFAULT_WIDGETS = [
        \App\Filament\Widgets\TodaySnapshotWidget::class,
        \App\Filament\Widgets\PendingMfsVerifyAlertWidget::class,
        \App\Filament\Widgets\BillingExecutiveDashboardWidget::class,
        \App\Filament\Widgets\OperationsCommandCenterWidget::class,
        \App\Filament\Widgets\DashboardCommandStripWidget::class,
        \App\Filament\Widgets\DashboardInsightsRowWidget::class,
    ];

    /** @return array<class-string, string> */
    public static function layoutWidgetLabels(): array
    {
        return [
            \App\Filament\Widgets\TodaySnapshotWidget::class => 'Today snapshot (collection, due, expiring)',
            \App\Filament\Widgets\PendingMfsVerifyAlertWidget::class => 'MFS pending verify alert',
            \App\Filament\Widgets\BillingExecutiveDashboardWidget::class => 'Billing overview (KPIs + chart)',
            \App\Filament\Widgets\OperationsCommandCenterWidget::class => 'Operations command center',
            \App\Filament\Widgets\DashboardCommandStripWidget::class => 'Quick tools strip',
            \App\Filament\Widgets\DashboardInsightsRowWidget::class => 'Insights row (revenue + online)',
        ];
    }

    /** Standalone chart widgets — replaced by {@see DashboardInsightsRowWidget}. */
    private const STANDALONE_CHART_WIDGETS = [
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
        'App\\Filament\\Widgets\\DashboardLayoutCustomizer' => null,
        'App\\Filament\\Widgets\\RevenueTrendChartWidget' => \App\Filament\Widgets\DashboardInsightsRowWidget::class,
        'App\\Filament\\Widgets\\OnlineUsersChartWidget' => \App\Filament\Widgets\DashboardInsightsRowWidget::class,
    ];

    /** @return list<class-string> */
    public function widgetsFor(?User $user): array
    {
        $capability = StaffCapability::for($user);
        $permitted = array_flip($capability->allowedDashboardWidgets());

        if ($permitted === []) {
            return [];
        }

        $prefs = $user?->dashboard_preferences ?? [];
        $saved = $prefs['widgets'] ?? null;

        if (! is_array($saved) || $saved === []) {
            return array_values(array_intersect(self::DEFAULT_WIDGETS, array_keys($permitted)));
        }

        $ordered = [];

        foreach ($saved as $class) {
            if (! is_string($class)) {
                continue;
            }

            $class = self::LEGACY_WIDGET_MAP[$class] ?? $class;

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            if (isset($permitted[$class]) && ! in_array($class, $ordered, true)) {
                $ordered[] = $class;
            }
        }

        if ($ordered === []) {
            return array_values(array_intersect(self::DEFAULT_WIDGETS, array_keys($permitted)));
        }

        return self::dedupeInsightsWidget($ordered);
    }

    /**
     * @param  list<class-string>  $widgets
     * @return list<class-string>
     */
    public static function dedupeInsightsWidget(array $widgets): array
    {
        $insights = \App\Filament\Widgets\DashboardInsightsRowWidget::class;
        $seenInsights = false;
        $out = [];

        foreach ($widgets as $class) {
            if (in_array($class, self::STANDALONE_CHART_WIDGETS, true)) {
                continue;
            }

            if ($class === $insights) {
                if ($seenInsights) {
                    continue;
                }

                $seenInsights = true;
            }

            $out[] = $class;
        }

        return array_values($out);
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

            $prefs['widgets'] = $this->normalizeWidgetListFor($locked, $widgets);

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
        return $this->normalizeWidgetListFor(auth()->user(), $widgets);
    }

    /**
     * @param  list<class-string>  $widgets
     * @return list<class-string>
     */
    public function normalizeWidgetListFor(?User $user, array $widgets): array
    {
        $permitted = array_flip(StaffCapability::for($user)->allowedDashboardWidgets());
        $ordered = [];

        foreach ($widgets as $class) {
            if (! is_string($class)) {
                continue;
            }

            $class = self::LEGACY_WIDGET_MAP[$class] ?? $class;

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            if (isset($permitted[$class]) && ! in_array($class, $ordered, true)) {
                $ordered[] = $class;
            }
        }

        if ($ordered === []) {
            return self::dedupeInsightsWidget(StaffCapability::for($user)->allowedDashboardWidgets());
        }

        return self::dedupeInsightsWidget(array_values($ordered));
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

        $repaired = $this->normalizeWidgetListFor($user, $saved);

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
        $insights = \App\Filament\Widgets\DashboardInsightsRowWidget::class;
        $insightsCount = 0;

        foreach ($saved as $class) {
            if (! is_string($class)) {
                return true;
            }

            if (in_array($class, self::STANDALONE_CHART_WIDGETS, true)) {
                return true;
            }

            if ($class === $insights) {
                $insightsCount++;
            }

            if (array_key_exists($class, self::LEGACY_WIDGET_MAP) && self::LEGACY_WIDGET_MAP[$class] !== $class) {
                return true;
            }
        }

        return $insightsCount > 1;
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

                $newWidgets = $this->normalizeWidgetListFor($user, $saved);
                $migrated = $newWidgets !== $saved || $this->prefsNeedLegacyStrip($saved);

                if ($migrated) {
                    $prefs['widgets'] = $newWidgets;
                    $user->forceFill(['dashboard_preferences' => $prefs])->save();
                    $updated++;
                }
            });

        return $updated;
    }
}
