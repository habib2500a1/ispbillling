<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardCommandStripWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-command-strip';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = -7;

    protected int|string|array $columnSpan = 'full';
}
