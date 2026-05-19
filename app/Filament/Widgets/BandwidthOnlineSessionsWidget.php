<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Models\BandwidthUsageDaily;
use App\Models\PppSessionLog;
use App\Support\BandwidthDirection;
use App\Support\TenantResolver;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class BandwidthOnlineSessionsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Online users (live traffic)';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    #[On('bandwidth-refresh')]
    public function refreshTable(): void
    {
        //
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PppSessionLog::query()
                    ->where('tenant_id', TenantResolver::requiredTenantId())
                    ->where('status', 'active')
                    ->with(['customer:id,name,customer_code,phone', 'mikrotikServer:id,name'])
            )
            ->searchPlaceholder('Search ID, name, phone, PPP user…')
            ->columns([
                Tables\Columns\TextColumn::make('customer.customer_code')
                    ->label('ID')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('customer.phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('username')
                    ->label('PPP user')
                    ->searchable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('live_download')
                    ->label('Live ↓')
                    ->state(fn (PppSessionLog $record): ?int => $record->liveDownloadBps())
                    ->formatStateUsing(fn (?int $state): string => BandwidthDirection::formatBps($state))
                    ->description(fn (PppSessionLog $record): string => '↑ '.BandwidthDirection::formatBps($record->liveUploadBps())),
                Tables\Columns\TextColumn::make('framed_ip')
                    ->label('IP')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('bytes_in')
                    ->label('Total down')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBytes((int) $state))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Online since')
                    ->since()
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Open')
                    ->icon('heroicon-o-user')
                    ->visible(fn (PppSessionLog $record): bool => $record->customer_id !== null)
                    ->url(fn (PppSessionLog $record): string => CustomerResource::getUrl('edit', ['record' => $record->customer_id])),
            ])
            ->defaultSort('started_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('No active sessions')
            ->emptyStateDescription('Click Sync now to load online users from MikroTik.');
    }
}
