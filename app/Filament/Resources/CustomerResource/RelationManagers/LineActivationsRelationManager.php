<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LineActivationsRelationManager extends RelationManager
{
    protected static string $relationship = 'lineActivations';

    protected static ?string $title = 'Line activations & charges';

    protected static ?string $icon = 'heroicon-o-bolt';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('line_charge')
                    ->label('Line charge')
                    ->money('BDT')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('device_charge')
                    ->label('Device')
                    ->money('BDT')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('total_charged')
                    ->label('Total')
                    ->money('BDT')
                    ->weight('bold')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('wallet_applied')
                    ->label('Wallet used')
                    ->money('BDT')
                    ->alignEnd()
                    ->color('success'),
                Tables\Columns\TextColumn::make('cash_collected')
                    ->label('Cash')
                    ->money('BDT')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('device.display_name')
                    ->label('Device issued')
                    ->placeholder('—')
                    ->description(fn ($record) => $record->device?->serial_number),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->url(fn ($record) => $record->invoice_id
                        ? \App\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $record->invoice_id])
                        : null)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('performer.name')
                    ->label('Staff')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->emptyStateHeading('No line activations yet')
            ->emptyStateDescription('Use “Assign new line” to add connection charge, link a device, and apply wallet.');
    }
}
