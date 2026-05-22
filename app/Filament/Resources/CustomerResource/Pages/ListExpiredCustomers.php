<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Support\CustomerAccountScopes;
use Illuminate\Database\Eloquent\Builder;

class ListExpiredCustomers extends ListFilteredCustomers
{
    use Concerns\HasBillingAccountListPage;

    protected static ?string $navigationLabel = 'Expired accounts';

    protected static ?string $title = 'Expired accounts';

    public static function getNavigationLabel(): string
    {
        return 'Expired accounts';
    }

    protected function applyFilter(Builder $query): Builder
    {
        return CustomerAccountScopes::applyExpired($query);
    }
}
