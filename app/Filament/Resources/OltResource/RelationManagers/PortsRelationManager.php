<?php

namespace App\Filament\Resources\OltResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PortsRelationManager extends RelationManager
{
    protected static string $relationship = 'ports';

    protected static ?string $title = 'PON ports';

    protected static bool $isLazy = true;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('card_index')
                    ->label('Line card')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(255),
                Forms\Components\TextInput::make('pon_index')
                    ->label('PON index')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(255),
                Forms\Components\TextInput::make('label')
                    ->maxLength(64)
                    ->helperText('Optional display label; defaults to card/PON.'),
                Forms\Components\Select::make('admin_status')
                    ->options([
                        'enabled' => 'Enabled',
                        'disabled' => 'Disabled',
                        'maintenance' => 'Maintenance',
                    ])
                    ->required()
                    ->default('enabled'),
                Forms\Components\Select::make('oper_status')
                    ->options([
                        'up' => 'Up',
                        'down' => 'Down',
                        'error' => 'Error',
                        'unknown' => 'Unknown',
                    ])
                    ->required()
                    ->default('unknown'),
                Forms\Components\TextInput::make('utilization_percent')
                    ->label('Utilization %')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                Forms\Components\TextInput::make('fiber_distance_m')
                    ->label('Fiber distance (m)')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('service_profile')
                    ->maxLength(128),
                Forms\Components\DateTimePicker::make('last_polled_at'),
                Forms\Components\KeyValue::make('meta')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('card_index')
                    ->label('Card')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('pon_index')
                    ->label('PON')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('admin_status')
                    ->badge(),
                Tables\Columns\TextColumn::make('oper_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'up' => 'success',
                        'down' => 'danger',
                        'error' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('onus_count')
                    ->counts('onus')
                    ->label('ONUs')
                    ->sortable(),
                Tables\Columns\TextColumn::make('utilization_percent')
                    ->label('Util %')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fiber_distance_m')
                    ->label('Fiber m')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_polled_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['device_id'] = $this->getOwnerRecord()->getKey();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('enable_ports')
                        ->label('Mark enabled')
                        ->icon('heroicon-o-check-circle')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $records->each->update(['admin_status' => 'enabled']);
                            Notification::make()->title('Ports set to enabled')->success()->send();
                        }),
                    Tables\Actions\BulkAction::make('disable_ports')
                        ->label('Mark disabled')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $records->each->update(['admin_status' => 'disabled']);
                            Notification::make()->title('Ports set to disabled')->success()->send();
                        }),
                ]),
            ]);
    }
}
