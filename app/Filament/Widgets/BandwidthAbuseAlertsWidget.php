<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Models\BandwidthAbuseAlert;
use App\Support\TenantResolver;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class BandwidthAbuseAlertsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Abuse detection';

    protected int|string|array $columnSpan = 'full';

    #[On('bandwidth-refresh')]
    public function refreshTable(): void
    {
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BandwidthAbuseAlert::query()
                    ->where('tenant_id', TenantResolver::requiredTenantId())
                    ->with('customer')
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')->label('Subscriber')->searchable(),
                Tables\Columns\TextColumn::make('alert_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => BandwidthAbuseAlert::typeLabel($state)),
                Tables\Columns\TextColumn::make('severity')->badge(),
                Tables\Columns\TextColumn::make('message')->wrap()->limit(80),
                Tables\Columns\TextColumn::make('created_at')->since(),
                Tables\Columns\IconColumn::make('resolved_at')
                    ->label('Open')
                    ->boolean()
                    ->getStateUsing(fn (BandwidthAbuseAlert $record): bool => $record->isOpen()),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('open')
                    ->label('Status')
                    ->trueLabel('Open only')
                    ->falseLabel('Resolved')
                    ->queries(
                        true: fn ($query) => $query->whereNull('resolved_at'),
                        false: fn ($query) => $query->whereNotNull('resolved_at'),
                        blank: fn ($query) => $query,
                    )
                    ->default(true),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BandwidthAbuseAlert $record): bool => $record->isOpen())
                    ->requiresConfirmation()
                    ->action(fn (BandwidthAbuseAlert $record) => $record->update(['resolved_at' => now()])),
                Tables\Actions\Action::make('subscriber')
                    ->icon('heroicon-o-user')
                    ->url(fn (BandwidthAbuseAlert $record): string => CustomerResource::getUrl('view', ['record' => $record->customer_id])),
            ]);
    }
}
