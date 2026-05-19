<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make()->schema([
                TextEntry::make('entry_number')->fontFamily('mono'),
                TextEntry::make('entry_date')->date(),
                TextEntry::make('description'),
                TextEntry::make('source_type'),
            ])->columns(2),
            Section::make('Lines')->schema([
                RepeatableEntry::make('lines')
                    ->schema([
                        TextEntry::make('chartAccount.code')->label('Code'),
                        TextEntry::make('chartAccount.name')->label('Account'),
                        TextEntry::make('debit')->money('BDT'),
                        TextEntry::make('credit')->money('BDT'),
                    ])
                    ->columns(4),
            ]),
        ]);
    }
}
