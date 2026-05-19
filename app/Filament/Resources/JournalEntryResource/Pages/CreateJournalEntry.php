<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use App\Services\Accounting\LedgerService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = collect($data['lines'] ?? [])->map(fn (array $line): array => [
            'account_id' => $line['chart_account_id'],
            'debit' => $line['debit'] ?? 0,
            'credit' => $line['credit'] ?? 0,
            'description' => $line['line_description'] ?? null,
        ])->all();

        return app(LedgerService::class)->post(
            $data['description'],
            $lines,
            $data['entry_date'] ?? now(),
        );
    }
}
