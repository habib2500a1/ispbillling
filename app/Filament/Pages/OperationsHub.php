<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Support\AdminModuleRegistry;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

/**
 * Master index of all ISP operations modules (parity with ISP Digital admin menus).
 */
class OperationsHub extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';

    protected static string $view = 'filament.pages.operations-hub';

    protected static ?string $navigationLabel = 'Module directory';

    protected static ?string $title = 'Operations center';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 1;

    /**
     * @return list<array{group: string, label: string, description: string, url: string, accent: string}>
     */
    public function getModules(): array
    {
        return AdminModuleRegistry::visible();
    }

    public static function canAccess(): bool
    {
        return AdminModuleRegistry::visible() !== [];
    }
}
