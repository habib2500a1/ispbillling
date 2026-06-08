<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\Notifications\CommsHubDashboardService;
use App\Support\SmsSidebarRegistry;
use Filament\Pages\Page;

class NotificationsHub extends Page
{
    use HidesHubNavigation;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static string $view = 'filament.pages.notifications-hub';

    protected static ?string $navigationLabel = 'Communication hub';

    protected static ?string $title = 'Communication Command Center';

    protected static ?string $navigationGroup = 'SMS Service';

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed> */
    public array $dashboard = [];

    public string $searchQuery = '';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public string $activeTab = 'dashboard';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->refreshDashboard();
    }

    public function refreshDashboard(): void
    {
        $this->dashboard = app(CommsHubDashboardService::class)->snapshot();
    }

    public function updatedSearchQuery(): void
    {
        $this->searchResults = app(CommsHubDashboardService::class)->search($this->searchQuery);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getNavLinks(): array
    {
        return SmsSidebarRegistry::definitions();
    }

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canSms();
    }
}
