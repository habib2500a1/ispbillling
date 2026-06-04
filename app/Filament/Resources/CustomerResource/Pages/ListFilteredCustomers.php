<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\UsesClientsDirectoryLayout;
use App\Support\CustomerStatus;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

abstract class ListFilteredCustomers extends ListRecords
{
    use UsesClientsDirectoryLayout;

    protected static string $resource = CustomerResource::class;

    protected static string $view = 'filament.resources.customer-resource.pages.list-customers';

    abstract protected function applyFilter(Builder $query): Builder;

    protected function getTableQuery(): ?Builder
    {
        return $this->applyFilter(
            CustomerResource::clientsDirectoryEloquentQuery($this->getDirectoryPageVariant()),
        );
    }
}
