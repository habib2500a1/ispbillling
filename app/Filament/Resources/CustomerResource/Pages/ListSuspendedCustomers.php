<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Support\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;

class ListSuspendedCustomers extends ListFilteredCustomers
{
    use Concerns\HasBillingAccountListPage;

    protected static ?string $navigationLabel = 'Suspend accounts';

    protected static ?string $title = 'Suspended accounts';

    public static function getNavigationLabel(): string
    {
        return 'Suspend accounts';
    }

    protected function applyFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', CustomerStatus::SUSPENDED)
                ->orWhere(function (Builder $q2): void {
                    $q2->where('network_access_state', 'suspended')
                        ->where('status', '!=', CustomerStatus::TERMINATED);
                });
        });
    }
}
