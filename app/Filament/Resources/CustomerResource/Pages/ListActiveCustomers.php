<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource\Pages\Concerns\HasBillingAccountListPage;
use App\Support\CustomerAccountScopes;
use Illuminate\Database\Eloquent\Builder;

class ListActiveCustomers extends ListFilteredCustomers
{
    use HasBillingAccountListPage;

    protected static ?string $navigationLabel = 'Active accounts';

    protected static ?string $title = 'Active accounts';

    public static function getNavigationLabel(): string
    {
        return 'Active accounts';
    }

    protected function applyFilter(Builder $query): Builder
    {
        return CustomerAccountScopes::applyActive($query);
    }
}
