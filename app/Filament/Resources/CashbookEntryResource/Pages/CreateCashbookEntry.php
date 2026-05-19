<?php

namespace App\Filament\Resources\CashbookEntryResource\Pages;

use App\Filament\Resources\CashbookEntryResource;
use App\Models\ChartOfAccount;
use App\Services\Accounting\CashbookService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCashbookEntry extends CreateRecord
{
    protected static string $resource = CashbookEntryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $accountCode = null;
        if (! empty($data['chart_account_id'])) {
            $accountCode = ChartOfAccount::find($data['chart_account_id'])?->code;
        }

        return app(CashbookService::class)->record(
            $data['direction'],
            (float) $data['amount'],
            $data['party_name'],
            $accountCode,
            $data['payment_method'] ?? 'cash',
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $data['entry_date'] ?? now(),
        );
    }
}
