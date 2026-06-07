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

    /**
     * @return array<string, mixed>
     */
    protected function queryString(): array
    {
        return [
            'tableSearch' => ['except' => ''],
            'tableFilters' => ['except' => null],
        ];
    }

    public function mount(): void
    {
        parent::mount();
        $this->loadDirectoryChrome();
        $this->migrateLegacySearchQuery();
    }

    abstract protected function applyFilter(Builder $query): Builder;

    protected function getTableQuery(): ?Builder
    {
        return $this->applyFilter(
            CustomerResource::clientsDirectoryEloquentQuery($this->getDirectoryPageVariant()),
        );
    }
}
