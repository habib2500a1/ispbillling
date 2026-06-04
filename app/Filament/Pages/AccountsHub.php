<?php

namespace App\Filament\Pages;

use App\Services\Accounts\AccountsDashboardService;
use App\Support\Rbac\StaffCapability;
use Carbon\Carbon;
use Filament\Pages\Page;

class AccountsHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string $view = 'filament.pages.accounts-hub';

    protected static ?string $navigationLabel = 'Accounts dashboard';

    protected static ?string $title = 'Accounts dashboard';

    protected static ?string $slug = 'accounts-hub';

    protected static bool $shouldRegisterNavigation = false;

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        $cap = StaffCapability::for($user);

        return $cap->canAccounting() || $cap->canBilling() || $cap->canPayments();
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatsProperty(): array
    {
        return app(AccountsDashboardService::class)->stats(
            Carbon::parse($this->dateFrom)->startOfDay(),
            Carbon::parse($this->dateTo)->endOfDay(),
        );
    }
}
