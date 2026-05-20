<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\BillingDashboardMetricsService;
use Filament\Widgets\Widget;

class BillingExecutiveDashboardWidget extends Widget
{
    protected static string $view = 'filament.widgets.billing-executive-dashboard';

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected static ?int $sort = -25;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return app(BillingDashboardMetricsService::class)->payload();
    }
}
