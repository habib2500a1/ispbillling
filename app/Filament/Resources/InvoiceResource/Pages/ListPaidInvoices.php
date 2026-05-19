<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPaidInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return 'Paid bills';
    }

    public function getHeading(): string
    {
        return 'Paid bills';
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('status', 'paid');
    }
}
