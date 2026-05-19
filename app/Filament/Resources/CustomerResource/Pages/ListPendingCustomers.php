<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource\Pages\Concerns\HasBillingAccountListPage;
use App\Support\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;

class ListPendingCustomers extends ListFilteredCustomers
{
    use HasBillingAccountListPage;

    protected static ?string $navigationLabel = 'Pending accounts';

    protected static ?string $title = 'Pending installation / KYC';

    public static function getNavigationLabel(): string
    {
        return 'Pending accounts';
    }

    protected function applyFilter(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->where(function (Builder $q): void {
                $q->where('kyc_status', 'pending')
                    ->orWhereRaw("COALESCE(meta->>'installation_status', '') = ?", ['pending']);
            });
    }
}
