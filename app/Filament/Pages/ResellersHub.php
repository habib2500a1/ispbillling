<?php

namespace App\Filament\Pages;

use App\Filament\Pages\ResellerPackagePricesPage;
use App\Filament\Pages\ResellerReportPage;
use App\Filament\Pages\ResellerWalletHubPage;
use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use Filament\Pages\Page;

class ResellersHub extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static string $view = 'filament.pages.resellers-hub';

    protected static ?string $navigationLabel = 'Reseller & franchise';

    protected static ?string $title = 'Reseller & franchise management';

    protected static ?string $navigationGroup = 'Resellers';

    protected static ?int $navigationSort = 1;

    /**
     * @return array<string, int|float>
     */
    public function getStats(): array
    {
        return [
            'total' => Reseller::query()->count(),
            'active' => Reseller::query()->where('is_active', true)->count(),
            'franchises' => Reseller::query()->where('franchise_type', 'franchise')->count(),
            'white_label' => Reseller::query()->where('white_label_enabled', true)->count(),
            'wallet_total' => (float) Reseller::query()->sum('wallet_balance'),
            'pending_commission' => (float) ResellerCommission::query()
                ->where('status', ResellerCommission::STATUS_PENDING)
                ->sum('commission_amount'),
        ];
    }

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canResellers();
    }
}
