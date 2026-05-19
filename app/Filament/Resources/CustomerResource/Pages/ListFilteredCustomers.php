<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Support\CustomerStatus;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

abstract class ListFilteredCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    abstract protected function applyFilter(Builder $query): Builder;

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        return $query === null ? null : $this->applyFilter($query);
    }
}
