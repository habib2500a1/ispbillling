<?php

namespace App\Filament\Pages;

use App\Services\Dashboard\DashboardMetricsService;
use Filament\Pages\Page;

class NocWall extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-tv';

    protected static string $view = 'filament.pages.noc-wall';

    protected static ?string $title = 'NOC wall';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.layouts.noc-wall';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function getWallData(): array
    {
        $service = app(DashboardMetricsService::class);

        return [
            'noc' => $service->nocSnapshot(),
            'gpon' => $service->gponSnapshot(),
            'support' => $service->supportSnapshot(),
            'alerts' => $service->liveAlerts(),
        ];
    }
}
