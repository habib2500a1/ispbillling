<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Pages\FiberPlantMap;
use App\Filament\Resources\PopBoxResource\Pages;
use App\Models\Area;
use App\Models\PopBox;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PopBoxResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = PopBox::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Network';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'POP / boxes';

    protected static ?int $navigationSort = 25;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('area_id')->label('Area')->options(fn () => Area::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
            Forms\Components\TextInput::make('code')->required()->maxLength(64),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('address')->columnSpanFull(),
            Forms\Components\TextInput::make('latitude')->numeric(),
            Forms\Components\TextInput::make('longitude')->numeric(),
            Forms\Components\TextInput::make('capacity')->numeric()->integer(),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->fontFamily('mono')->searchable(),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('area.name')->label('Area'),
            Tables\Columns\TextColumn::make('capacity'),
            Tables\Columns\TextColumn::make('coordinates')
                ->label('GIS')
                ->state(fn (PopBox $record): string => filled($record->latitude) && filled($record->longitude)
                    ? $record->latitude.', '.$record->longitude
                    : '—')
                ->fontFamily('mono')
                ->toggleable(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\Action::make('map')
                ->label('Map')
                ->icon('heroicon-o-map-pin')
                ->color('gray')
                ->url(function (PopBox $record): string {
                    if (filled($record->latitude) && filled($record->longitude)) {
                        return 'https://www.google.com/maps/search/?api=1&query='.$record->latitude.','.$record->longitude;
                    }

                    return FiberPlantMap::getUrl();
                })
                ->openUrlInNewTab(),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPopBoxes::route('/'),
            'create' => Pages\CreatePopBox::route('/create'),
            'edit' => Pages\EditPopBox::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'mikrotik';
    }
}
