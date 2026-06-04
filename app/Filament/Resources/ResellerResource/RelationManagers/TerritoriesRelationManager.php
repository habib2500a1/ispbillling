<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\Area;
use App\Models\ResellerTerritory;
use App\Models\Subzone;
use App\Models\Zone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TerritoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'territories';

    protected static ?string $title = 'Territory coverage';

    protected static ?string $icon = 'heroicon-o-map';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('area_id')
                    ->label('Area')
                    ->options(fn (): array => Area::query()
                        ->where('tenant_id', $this->getOwnerRecord()->tenant_id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('zone_id', null);
                        $set('subzone_id', null);
                    })
                    ->helperText('Select area to cover entire area, or narrow down with zone/subzone.'),
                Forms\Components\Select::make('zone_id')
                    ->label('Zone')
                    ->options(function (Get $get): array {
                        $areaId = $get('area_id');
                        if (! $areaId) {
                            return [];
                        }

                        return Zone::query()
                            ->where('area_id', $areaId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('subzone_id', null))
                    ->disabled(fn (Get $get): bool => ! $get('area_id'))
                    ->helperText('Optional: narrow to specific zone.'),
                Forms\Components\Select::make('subzone_id')
                    ->label('Subzone')
                    ->options(function (Get $get): array {
                        $zoneId = $get('zone_id');
                        if (! $zoneId) {
                            return [];
                        }

                        return Subzone::query()
                            ->where('zone_id', $zoneId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->disabled(fn (Get $get): bool => ! $get('zone_id'))
                    ->helperText('Optional: narrow to specific subzone.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('area.name')
                    ->label('Area')
                    ->placeholder('—')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Zone')
                    ->placeholder('All zones')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subzone.name')
                    ->label('Subzone')
                    ->placeholder('All subzones')
                    ->sortable(),
                Tables\Columns\TextColumn::make('coverage')
                    ->label('Coverage level')
                    ->badge()
                    ->getStateUsing(function (ResellerTerritory $record): string {
                        if ($record->subzone_id) {
                            return 'Subzone';
                        }
                        if ($record->zone_id) {
                            return 'Zone';
                        }
                        if ($record->area_id) {
                            return 'Area';
                        }

                        return 'Global';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Subzone' => 'info',
                        'Zone' => 'warning',
                        'Area' => 'success',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('area.name')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add territory')
                    ->before(function (array $data): void {
                        // BUG FIX: Validate at least one location is selected
                        if (blank($data['area_id']) && blank($data['zone_id']) && blank($data['subzone_id'])) {
                            Notification::make()
                                ->title('Invalid territory')
                                ->body('Please select at least an area.')
                                ->danger()
                                ->send();
                            throw new \Exception('At least one location must be selected.');
                        }

                        // Check for duplicate territory
                        $exists = ResellerTerritory::query()
                            ->where('reseller_id', $this->getOwnerRecord()->getKey())
                            ->where('area_id', $data['area_id'] ?? null)
                            ->where('zone_id', $data['zone_id'] ?? null)
                            ->where('subzone_id', $data['subzone_id'] ?? null)
                            ->exists();

                        if ($exists) {
                            Notification::make()
                                ->title('Duplicate territory')
                                ->body('This territory is already assigned to this reseller.')
                                ->danger()
                                ->send();
                            throw new \Exception('Duplicate territory.');
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No territories assigned')
            ->emptyStateDescription('Assign geographic territories to control where this reseller can operate.')
            ->emptyStateIcon('heroicon-o-map');
    }
}
