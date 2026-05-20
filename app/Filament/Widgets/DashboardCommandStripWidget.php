<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ChecksDashboardWidgetAccess;
use Filament\Widgets\Widget;

class DashboardCommandStripWidget extends Widget
{
    use ChecksDashboardWidgetAccess;
    protected static string $view = 'filament.widgets.dashboard-command-strip';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = -7;

    protected int|string|array $columnSpan = 'full';
}
