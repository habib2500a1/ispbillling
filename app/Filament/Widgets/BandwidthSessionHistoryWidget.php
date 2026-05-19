<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Models\BandwidthUsageDaily;
use App\Models\PppSessionLog;
use App\Support\TenantResolver;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class BandwidthSessionHistoryWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Session history';

    protected int|string|array $columnSpan = 'full';

    #[On('bandwidth-refresh')]
    public function refreshTable(): void
    {
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PppSessionLog::query()
                    ->where('tenant_id', TenantResolver::requiredTenantId())
                    ->with(['customer', 'device'])
            )
            ->defaultSort('started_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')->label('Subscriber')->searchable(),
                Tables\Columns\TextColumn::make('username')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('framed_ip')->placeholder('—'),
                Tables\Columns\TextColumn::make('caller_id')->label('MAC')->toggleable(),
                Tables\Columns\TextColumn::make('bytes_in')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBytes((int) $state)),
                Tables\Columns\TextColumn::make('bytes_out')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBytes((int) $state)),
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->dateTime()->placeholder('Active')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'closed' => 'Closed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-user')
                    ->url(fn (PppSessionLog $record): string => CustomerResource::getUrl('view', ['record' => $record->customer_id])),
            ]);
    }
}
