<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Support\CustomerAccountScopes;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLeftCustomers extends ListRecords
{
    use Concerns\HasBillingAccountListPage;

    protected static string $resource = CustomerResource::class;

    protected static ?string $navigationLabel = 'Left accounts';

    protected static ?string $title = 'Left accounts';

    public static function getNavigationLabel(): string
    {
        return 'Left accounts';
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        return $query === null ? null : CustomerAccountScopes::applyLeft($query);
    }
}
