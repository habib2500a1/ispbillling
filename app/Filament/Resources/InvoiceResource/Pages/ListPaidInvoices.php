<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\InvoiceResource\Pages\Concerns\UsesBillingInvoiceLayout;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPaidInvoices extends ListRecords
{
    use UsesBillingInvoiceLayout;

    protected static string $resource = InvoiceResource::class;

    protected static string $view = 'filament.resources.invoice-resource.pages.list-invoices';

    public function booted(): void
    {
        $this->bootUsesBillingInvoiceLayout();
    }

    protected function getBillingListVariant(): string
    {
        return 'paid';
    }

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
