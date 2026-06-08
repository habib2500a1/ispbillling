<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\Billing\AdminBillingNoticesService;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class BillingNoticesPage extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static string $view = 'filament.pages.billing-notices';

    protected static ?string $navigationLabel = 'Billing notices';

    protected static ?string $title = 'Billing notices';

    protected static ?string $slug = 'billing-notices';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        $cap = StaffCapability::for(auth()->user());

        return $cap->canBilling() || $cap->canPayments();
    }

    /**
     * @return array<string, string|bool>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-billing-module',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getNotices(): array
    {
        return app(AdminBillingNoticesService::class)->payload();
    }
}
