<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreDeviceLoanResource\Pages;
use App\Models\Customer;
use App\Models\Device;
use App\Models\StoreDeviceLoan;
use App\Services\Inventory\StoreDeviceLoanService;
use App\Support\Rbac\StaffCapability;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StoreDeviceLoanResource extends Resource
{
    protected static ?string $model = StoreDeviceLoan::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Support device loans';

    protected static ?string $slug = 'store-device-loans';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return StaffCapability::for(auth()->user())->canAccessModuleGroup('Inventory');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('device_id')
                ->label('Device')
                ->options(fn (): array => Device::query()
                    ->where('type', '!=', 'olt')
                    ->whereDoesntHave('storeDeviceLoans', fn ($q) => $q->where('status', StoreDeviceLoan::STATUS_ISSUED))
                    ->orderBy('display_name')
                    ->limit(200)
                    ->pluck('display_name', 'id')
                    ->all())
                ->searchable()
                ->required(),
            Forms\Components\Select::make('customer_id')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                    ->where('name', 'like', '%'.$search.'%')
                    ->limit(20)
                    ->pluck('name', 'id')
                    ->all())
                ->required(),
            Forms\Components\Select::make('condition_out')
                ->options(['G' => 'Good', 'R' => 'Repair needed'])
                ->default('G'),
            Forms\Components\DateTimePicker::make('due_return_at'),
            Forms\Components\Textarea::make('issue_notes')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device.display_name')->label('Device'),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('issued_at')->dateTime(),
                Tables\Columns\TextColumn::make('due_return_at')->dateTime()->color(
                    fn (StoreDeviceLoan $record): ?string => $record->status === StoreDeviceLoan::STATUS_ISSUED
                        && $record->due_return_at?->isPast()
                        ? 'danger'
                        : null,
                ),
                Tables\Columns\TextColumn::make('returned_at')->dateTime(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('return')
                    ->label('Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn (StoreDeviceLoan $record): bool => $record->status === StoreDeviceLoan::STATUS_ISSUED)
                    ->form([
                        Forms\Components\Select::make('condition_in')
                            ->options(['G' => 'Good', 'R' => 'Repair needed'])
                            ->default('G')
                            ->required(),
                        Forms\Components\Textarea::make('return_notes')->rows(2),
                    ])
                    ->action(function (StoreDeviceLoan $record, array $data): void {
                        app(StoreDeviceLoanService::class)->returnDevice(
                            $record,
                            auth()->user(),
                            (string) ($data['condition_in'] ?? 'G'),
                            $data['return_notes'] ?? null,
                        );
                        Notification::make()->title('Device returned')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Issue device'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreDeviceLoans::route('/'),
            'create' => Pages\CreateStoreDeviceLoan::route('/create'),
        ];
    }
}
