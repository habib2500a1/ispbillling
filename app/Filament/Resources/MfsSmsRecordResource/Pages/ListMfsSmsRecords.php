<?php

namespace App\Filament\Resources\MfsSmsRecordResource\Pages;

use App\Filament\Resources\MfsSmsRecordResource;
use App\Filament\Resources\MfsSmsRecordResource\Widgets\TransferLedgerBanner;
use App\Filament\Resources\PaymentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMfsSmsRecords extends ListRecords
{
    protected static string $resource = MfsSmsRecordResource::class;

    public function getSubheading(): ?string
    {
        return 'Amount-এর পরে Transfer · Linked সারিতে ক্লিক করুন · v2026-05-23';
    }

    public function getTitle(): string
    {
        return 'RCL SMS ledger';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('paymentsTransfer')
                ->label('Payments → Transfer')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->url(PaymentResource::getUrl('index')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TransferLedgerBanner::class,
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with(['payment.customer']);
    }
}
