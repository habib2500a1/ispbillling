<?php

namespace App\Filament\Widgets;

use App\Services\Mikrotik\MikrotikFleetCoordinator;
use Filament\Widgets\Widget;

class MikrotikFleetHealthWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.widgets.mikrotik-fleet-health';

    protected static ?int $sort = -7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '120s';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $fleet = app(MikrotikFleetCoordinator::class);
        $servers = $fleet->fleetSummary();

        $online = collect($servers)->where('status', 'online')->count();
        $offline = count($servers) - $online;

        return [
            'servers' => $servers,
            'online' => $online,
            'offline' => $offline,
            'total' => count($servers),
        ];
    }
}
