<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Resources\StoreDeviceLoanResource;
use App\Models\StoreDeviceLoan;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

trait ListsStoreDeviceLoansTable
{
    public function storeDeviceLoanTable(Table $table, Builder $query): Table
    {
        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('device.display_name')->label('Device')->searchable(),
                Tables\Columns\TextColumn::make('device.serial_number')->label('Serial')->toggleable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('condition_out')->label('Out'),
                Tables\Columns\TextColumn::make('issued_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('due_return_at')
                    ->dateTime()
                    ->color(fn (StoreDeviceLoan $record): ?string => $record->isOverdue() ? 'danger' : null),
                Tables\Columns\TextColumn::make('issue_notes')->limit(40)->toggleable(),
            ])
            ->defaultSort('due_return_at')
            ->headerActions([
                Tables\Actions\Action::make('all_loans')
                    ->label('All loans')
                    ->url(StoreDeviceLoanResource::getUrl())
                    ->icon('heroicon-o-arrow-path-rounded-square'),
            ]);
    }
}
