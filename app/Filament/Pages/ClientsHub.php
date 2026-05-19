<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Resources\CustomerResource;
use App\Services\Clients\ClientsDashboardService;
use Filament\Pages\Page;

class ClientsHub extends Page
{
    use HidesHubNavigation;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static string $view = 'filament.pages.clients-hub';

    protected static ?string $slug = 'clients-hub';

    protected static ?string $title = 'Clients';

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return app(ClientsDashboardService::class)->summary();
    }

    public static function canAccess(): bool
    {
        return CustomerResource::canViewAny();
    }
}
