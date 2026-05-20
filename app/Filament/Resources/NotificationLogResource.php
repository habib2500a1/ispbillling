<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationLogResource\Pages;
use App\Models\NotificationLog;
use App\Support\NotificationChannel;
use App\Support\Rbac\StaffCapability;
use App\Support\NotificationEvent;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationLabel = 'Notification log';

    protected static ?string $modelLabel = 'notification';

    protected static ?string $pluralModelLabel = 'notification log';

    protected static ?string $navigationGroup = 'SMS Service';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return StaffCapability::for(auth()->user())->canSms();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Subscriber')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('event')
                    ->formatStateUsing(fn (string $state): string => NotificationEvent::labels()[$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('channel')
                    ->formatStateUsing(fn (string $state): string => NotificationChannel::labels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('recipient')->limit(24)->fontFamily('mono'),
                Tables\Columns\TextColumn::make('status')->badge()->color(
                    fn (string $state): string => NotificationLog::statusColor($state)
                ),
                Tables\Columns\TextColumn::make('message')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('error')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Subscriber')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'skipped' => 'Skipped',
                        'pending' => 'Pending',
                    ]),
                Tables\Filters\SelectFilter::make('channel')
                    ->options(NotificationChannel::labels()),
                Tables\Filters\SelectFilter::make('event')
                    ->options(NotificationEvent::labels()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationLogs::route('/'),
            'sms-report' => Pages\ListSmsReport::route('/sms-report'),
            'delivered' => Pages\ListDeliveredSms::route('/delivered'),
            'pending' => Pages\ListPendingSms::route('/pending'),
            'failed' => Pages\ListFailedSms::route('/failed'),
        ];
    }
}
