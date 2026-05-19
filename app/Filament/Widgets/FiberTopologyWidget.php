<?php

namespace App\Filament\Widgets;

use App\Services\Network\NetworkTopologyService;
use Filament\Widgets\Widget;

class FiberTopologyWidget extends Widget
{
    protected static string $view = 'filament.widgets.fiber-topology';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '60s';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $topology = app(NetworkTopologyService::class)->build();

        return [
            'summary' => $topology['summary'] ?? [],
            'oltCount' => count($topology['olts'] ?? []),
            'mikrotikCount' => count($topology['mikrotik'] ?? []),
        ];
    }
}
