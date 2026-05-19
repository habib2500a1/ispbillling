<?php

namespace App\Filament\Pages;

use App\Services\Network\NetworkTopologyService;
use Filament\Pages\Page;

class NetworkTopology extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static string $view = 'filament.pages.network-topology';

    protected static ?string $navigationLabel = 'Network topology';

    protected static ?string $title = 'Network topology';

    protected static ?string $navigationGroup = 'Network';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    public string $activeTab = 'fiber';

    /**
     * @return array<string, mixed>
     */
    public function getTopology(): array
    {
        return app(NetworkTopologyService::class)->build();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
