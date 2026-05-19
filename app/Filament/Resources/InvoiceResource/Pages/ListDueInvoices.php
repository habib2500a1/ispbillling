<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDueInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return 'Due bills';
    }

    public function getHeading(): string
    {
        return 'Due bills';
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->whereIn('status', ['open', 'partial', 'draft']);
    }
}
