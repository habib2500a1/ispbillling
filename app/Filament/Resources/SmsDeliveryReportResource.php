<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsDeliveryReportResource\Pages;
use App\Models\SmsDeliveryReport;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SmsDeliveryReportResource extends Resource
{
    protected static ?string $model = SmsDeliveryReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationLabel = 'SMS delivery (DLR)';

    protected static ?string $modelLabel = 'SMS DLR';

    protected static ?string $pluralModelLabel = 'SMS delivery reports';

    protected static ?string $navigationGroup = 'SMS Service';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
        ]) ?? false;
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
                Tables\Columns\TextColumn::make('reported_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('gateway_message_id')->label('Message ID')->searchable()->fontFamily('mono'),
                Tables\Columns\TextColumn::make('recipient')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('delivery_status')->badge()->color(fn (?string $state): string => match ($state) {
                    'delivered' => 'success',
                    'failed', 'rejected', 'undelivered' => 'danger',
                    'pending' => 'warning',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('status_text')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('notificationLog.event')->label('Event')->placeholder('—'),
                Tables\Columns\TextColumn::make('notificationLog.customer.name')->label('Subscriber')->placeholder('—'),
            ])
            ->defaultSort('reported_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('delivery_status')
                    ->options([
                        'delivered' => 'Delivered',
                        'failed' => 'Failed',
                        'pending' => 'Pending',
                        'unknown' => 'Unknown',
                    ]),
                Tables\Filters\Filter::make('failed_only')
                    ->label('Failed only')
                    ->query(fn ($q) => $q->whereIn('delivery_status', ['failed', 'rejected', 'undelivered'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsDeliveryReports::route('/'),
        ];
    }
}
