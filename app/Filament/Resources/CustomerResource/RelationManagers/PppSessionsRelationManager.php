<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\BandwidthUsageDaily;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PppSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'pppSessions';

    protected static ?string $title = 'Sessions & usage';

    protected static ?string $icon = 'heroicon-o-chart-bar-square';

    protected static bool $isLazy = true;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('username')
            ->columns([
                Tables\Columns\TextColumn::make('username')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('framed_ip')->label('IP')->placeholder('—'),
                Tables\Columns\TextColumn::make('caller_id')->label('MAC')->placeholder('—'),
                Tables\Columns\TextColumn::make('device.display_name')->label('Device')->placeholder('—'),
                Tables\Columns\TextColumn::make('bytes_in')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBytes((int) $state)),
                Tables\Columns\TextColumn::make('bytes_out')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBytes((int) $state)),
                Tables\Columns\TextColumn::make('peak_rate_in_bps')
                    ->label('Peak')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBps((int) $state)),
                Tables\Columns\TextColumn::make('started_at')->dateTime(),
                Tables\Columns\TextColumn::make('ended_at')->dateTime()->placeholder('Online'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'closed' => 'Closed']),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
