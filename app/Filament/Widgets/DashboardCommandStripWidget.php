<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ChecksDashboardWidgetAccess;
use App\Filament\Concerns\HasDashboardLazySkeleton;
use Filament\Widgets\Widget;

class DashboardCommandStripWidget extends Widget
{
    use ChecksDashboardWidgetAccess;
    use HasDashboardLazySkeleton;

    protected static string $view = 'filament.widgets.dashboard-command-strip';

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected static ?int $sort = -7;

    protected int|string|array $columnSpan = 'full';

    protected function dashboardSkeletonVariant(): string
    {
        return 'strip';
    }

    protected function dashboardSkeletonHeight(): ?string
    {
        return '3.5rem';
    }
}
