<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\AttendanceOfficeLocationResource\Pages;
use App\Models\AttendanceOfficeLocation;
use App\Support\TenantResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttendanceOfficeLocationResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = AttendanceOfficeLocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Office locations';

    protected static ?string $modelLabel = 'office location';

    protected static ?string $pluralModelLabel = 'office locations';

    protected static ?string $navigationGroup = 'HRM';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        $defaultRadius = (int) config('attendance.default_radius_meters', 10);

        return $form->schema([
            Forms\Components\Section::make('Office GPS zone')
                ->description('Staff «Present» attendance must be within radius of this point (default '.$defaultRadius.' m).')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(120)
                        ->placeholder('Head office'),
                    Forms\Components\Textarea::make('address')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('latitude')
                        ->label('Latitude')
                        ->numeric()
                        ->required()
                        ->step(0.0000001)
                        ->helperText('Google Maps → right-click → copy coordinates'),
                    Forms\Components\TextInput::make('longitude')
                        ->label('Longitude')
                        ->numeric()
                        ->required()
                        ->step(0.0000001),
                    Forms\Components\TextInput::make('radius_meters')
                        ->label('Allowed radius (meters)')
                        ->numeric()
                        ->required()
                        ->default($defaultRadius)
                        ->minValue(5)
                        ->maxValue(500)
                        ->suffix('m'),
                    Forms\Components\TagsInput::make('allowed_ips')
                        ->label('Office IPs (optional)')
                        ->placeholder('103.29.127.94 or 192.168.1.0/24')
                        ->helperText('When set, check-in IP must match one of these. Leave empty to allow any IP.'),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Default office')
                        ->helperText('Pre-selected on new attendance rows.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('latitude')->label('Lat')->toggleable(),
                Tables\Columns\TextColumn::make('longitude')->label('Lng')->toggleable(),
                Tables\Columns\TextColumn::make('radius_meters')
                    ->label('Radius')
                    ->suffix(' m')
                    ->sortable(),
                Tables\Columns\TextColumn::make('allowed_ips')
                    ->label('IPs')
                    ->formatStateUsing(fn ($state): string => is_array($state) && $state !== []
                        ? implode(', ', $state)
                        : 'Any')
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceOfficeLocations::route('/'),
            'create' => Pages\CreateAttendanceOfficeLocation::route('/create'),
            'edit' => Pages\EditAttendanceOfficeLocation::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'payroll';
    }
}
