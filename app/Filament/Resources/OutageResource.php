<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OutageResource\Pages;
use App\Models\Customer;
use App\Models\Outage;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use App\Support\SupportPanelAccess;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OutageResource extends Resource
{
    protected static ?string $model = Outage::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 25;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('area_id')
                    ->label('Area (blank = all areas)')
                    ->relationship('area', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('started_at')->required(),
                Forms\Components\DateTimePicker::make('ended_at'),
                Forms\Components\Toggle::make('is_active')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('area.name')->label('Area')->placeholder('All'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\Action::make('notify_area')
                    ->label('SMS area')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->visible(fn (Outage $record): bool => (bool) $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Send outage SMS to subscribers')
                    ->action(function (Outage $record): void {
                        $message = trim(($record->title ?? '').': '.($record->description ?? ''));
                        $query = Customer::query()
                            ->where('tenant_id', $record->tenant_id)
                            ->where('status', 'active');
                        if ($record->area_id) {
                            $query->where('area_id', $record->area_id);
                        }
                        $count = 0;
                        $customerLines = [];
                        $dispatcher = app(NotificationDispatcher::class);
                        foreach ($query->cursor() as $customer) {
                            $dispatcher->notifyCustomer($customer, NotificationEvent::OUTAGE, ['message' => $message]);
                            $customerLines[] = $customer->name.' ('.($customer->customer_code ?? (string) $customer->id).')';
                            $count++;
                        }
                        $areaName = $record->area?->name ?? 'All areas';
                        $dispatcher->notifyOps((int) $record->tenant_id, NotificationEvent::OUTAGE, [
                            'message' => $areaName.': '.$message,
                            'count' => $count,
                            'customer_list' => $customerLines === []
                                ? '—'
                                : (count($customerLines) <= 25
                                    ? implode("\n", $customerLines)
                                    : implode("\n", array_slice($customerLines, 0, 25))."\n… +".(count($customerLines) - 25).' more'),
                        ]);
                        Notification::make()
                            ->title("Outage SMS sent to {$count} subscribers")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageOutages::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return SupportPanelAccess::manageOutages(auth()->user());
    }

    public static function canCreate(): bool
    {
        return SupportPanelAccess::manageOutages(auth()->user());
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return SupportPanelAccess::manageOutages(auth()->user());
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return SupportPanelAccess::manageOutages(auth()->user());
    }
}
