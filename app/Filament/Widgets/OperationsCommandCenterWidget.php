<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\OperationsDashboardService;
use Filament\Widgets\Widget;

class OperationsCommandCenterWidget extends Widget
{
    protected static string $view = 'filament.widgets.operations-command-center';

    protected static bool $isDiscovered = true;

    protected static bool $isLazy = true;

    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'ops' => app(OperationsDashboardService::class)->payload(),
        ];
    }
}
