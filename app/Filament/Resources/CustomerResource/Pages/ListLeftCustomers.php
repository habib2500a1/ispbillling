<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Support\CustomerAccountScopes;
use Illuminate\Database\Eloquent\Builder;

class ListLeftCustomers extends ListFilteredCustomers
{
    protected static ?string $navigationLabel = 'Left accounts';

    protected static ?string $title = 'Left accounts';

    public static function getNavigationLabel(): string
    {
        return 'Left accounts';
    }

    public function getSubheading(): ?string
    {
        return 'Archived / left subscribers — history preserved, line inactive.';
    }

    public function getPageTitle(): string
    {
        return 'Left clients';
    }

    protected function applyFilter(Builder $query): Builder
    {
        return CustomerAccountScopes::applyLeft($query);
    }
}
