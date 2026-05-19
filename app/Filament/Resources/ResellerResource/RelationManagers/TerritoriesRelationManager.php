<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\Area;
use App\Models\ResellerTerritory;
use App\Models\Subzone;
use App\Models\Zone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
                    ->options(Area::query()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->nullable(),
                Forms\Components\Select::make('zone_id')
                    ->label('Zone')
                    ->options(function (Get $get): array {
                        $q = Zone::query();
                        if ($areaId = $get('area_id')) {
                            $q->where('area_id', $areaId);
                        }

                        return $q->pluck('name', 'id')->all();
                    })
                    ->searchable()
                    ->nullable(),
                Forms\Components\Select::make('subzone_id')
                    ->label('Subzone')
                    ->options(function (Get $get): array {
                        $q = Subzone::query();
                        if ($zoneId = $get('zone_id')) {
                            $q->where('zone_id', $zoneId);
                        }

                        return $q->pluck('name', 'id')->all();
                    })
                    ->searchable()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('area.name')->label('Area')->placeholder('—'),
                Tables\Columns\TextColumn::make('zone.name')->label('Zone')->placeholder('—'),
                Tables\Columns\TextColumn::make('subzone.name')->label('Subzone')->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
