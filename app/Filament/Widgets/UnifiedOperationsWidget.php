<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use Filament\Widgets\Widget;

class UnifiedOperationsWidget extends Widget
{
    protected static string $view = 'filament.widgets.unified-operations';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = -8;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $service = app(DashboardMetricsService::class);

        return [
            'snapshot' => $service->snapshot(),
            'billing' => $service->billingSnapshot(),
            'noc' => $service->nocSnapshot(),
            'gpon' => $service->gponSnapshot(),
            'support' => $service->supportSnapshot(),
            'mikrotik' => $service->mikrotikSnapshot(),
            'alerts' => $service->liveAlerts(),
        ];
    }
}
