<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use Filament\Widgets\Widget;

class ExecutiveKpiGridWidget extends Widget
{
    protected static string $view = 'filament.widgets.executive-kpi-grid';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = -9;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'grid' => app(DashboardMetricsService::class)->kpiGrid(),
        ];
    }
}
