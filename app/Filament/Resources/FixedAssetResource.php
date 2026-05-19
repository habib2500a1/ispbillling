<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\FixedAssetResource\Pages;
use App\Models\FixedAsset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FixedAssetResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = FixedAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Finance';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Fixed assets';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('asset_code'),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('category'),
            Forms\Components\TextInput::make('serial_number'),
            Forms\Components\DatePicker::make('purchased_at'),
            Forms\Components\TextInput::make('purchase_value')->numeric()->default(0),
            Forms\Components\Select::make('status')->options([
                'active' => 'Active',
                'disposed' => 'Disposed',
                'damaged' => 'Damaged',
            ])->default('active')->native(false),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('asset_code')->fontFamily('mono'),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('category'),
            Tables\Columns\TextColumn::make('purchase_value')->money('BDT'),
            Tables\Columns\TextColumn::make('status')->badge(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFixedAssets::route('/'),
            'create' => Pages\CreateFixedAsset::route('/create'),
            'edit' => Pages\EditFixedAsset::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'accounting';
    }
}
