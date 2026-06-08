<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\InvoiceResource\Pages\Concerns\UsesBillingInvoiceLayout;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDueInvoices extends ListRecords
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
        return 'due';
    }

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
