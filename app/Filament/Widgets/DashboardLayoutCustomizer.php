<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Legacy stub — layout customizer lives on {@see \App\Filament\Pages\Dashboard}.
 * Kept so stale Livewire snapshots from older sessions do not 500 the panel.
 */
class DashboardLayoutCustomizer extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-layout-customizer-stub';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 99;

    protected int|string|array $columnSpan = 'full';
}
