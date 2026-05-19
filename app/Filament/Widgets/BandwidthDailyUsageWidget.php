<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Models\BandwidthUsageDaily;
use App\Support\TenantResolver;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class BandwidthDailyUsageWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Daily usage';

    protected int|string|array $columnSpan = 'full';

    #[On('bandwidth-refresh')]
    public function refreshTable(): void
    {
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BandwidthUsageDaily::query()
                    ->where('tenant_id', TenantResolver::requiredTenantId())
                    ->with('customer')
            )
            ->defaultSort('usage_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')->label('Subscriber')->searchable(),
                Tables\Columns\TextColumn::make('usage_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('bytes_in')
                    ->label('Download')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBytes((int) $state)),
                Tables\Columns\TextColumn::make('bytes_out')
                    ->label('Upload')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBytes((int) $state)),
                Tables\Columns\TextColumn::make('peak_rate_in_bps')
                    ->label('Peak down')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBps((int) $state)),
                Tables\Columns\TextColumn::make('online_seconds')
                    ->label('Time online')
                    ->formatStateUsing(fn ($state): string => gmdate('H:i:s', (int) $state)),
                Tables\Columns\TextColumn::make('session_count')->label('Sessions'),
            ])
            ->filters([
                Tables\Filters\Filter::make('this_month')
                    ->label('This month')
                    ->query(fn ($query) => $query->where('usage_date', '>=', now()->startOfMonth())),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-user')
                    ->url(fn (BandwidthUsageDaily $record): string => CustomerResource::getUrl('view', ['record' => $record->customer_id])),
            ]);
    }
}
