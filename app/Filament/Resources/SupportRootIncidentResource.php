<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportRootIncidentResource\Pages;
use App\Models\Device;
use App\Models\SupportRootIncident;
use App\Models\SupportTicket;
use App\Support\SupportPanelAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportRootIncidentResource extends Resource
{
    protected static ?string $model = SupportRootIncident::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Root incidents';

    protected static ?string $modelLabel = 'Root incident';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('incident_number')
                ->disabled()
                ->dehydrated(false),
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('description')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->options(SupportRootIncident::STATUSES)
                ->required(),
            Forms\Components\Select::make('olt_device_id')
                ->label('OLT')
                ->options(fn (): array => Device::query()->where('type', 'olt')->orderBy('display_name')->pluck('display_name', 'id')->all())
                ->searchable()
                ->nullable(),
            Forms\Components\TextInput::make('ticket_count')
                ->numeric()
                ->disabled()
                ->dehydrated(false),
            Forms\Components\DateTimePicker::make('detected_at'),
            Forms\Components\DateTimePicker::make('resolved_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('incident_number')
                    ->label('Incident')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('olt.display_name')
                    ->label('OLT')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('ticket_count')
                    ->label('Affected')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('detected_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('resolved_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('detected_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(SupportRootIncident::STATUSES),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SupportRootIncident $record): bool => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function (SupportRootIncident $record): void {
                        $record->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                        ]);
                    }),
                Tables\Actions\Action::make('primaryTicket')
                    ->label('Primary ticket')
                    ->icon('heroicon-o-ticket')
                    ->url(fn (SupportRootIncident $record): ?string => $record->primary_ticket_id
                        ? SupportTicketResource::getUrl('edit', ['record' => $record->primaryTicket?->ticket_number ?? $record->primary_ticket_id])
                        : null)
                    ->visible(fn (SupportRootIncident $record): bool => $record->primary_ticket_id !== null),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            Pages\RelationManagers\TicketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportRootIncidents::route('/'),
            'edit' => Pages\EditSupportRootIncident::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['olt:id,display_name']);
    }

    public static function canViewAny(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return SupportPanelAccess::manageTickets(auth()->user());
    }
}
