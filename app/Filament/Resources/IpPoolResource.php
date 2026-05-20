<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IpPoolResource\Pages;
use App\Filament\Resources\IpPoolResource\RelationManagers\AllocationsRelationManager;
use App\Models\IpPool;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IpPoolResource extends Resource
{
    protected static ?string $model = IpPool::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Network';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'IP pools';

    protected static ?int $navigationSort = 13;

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return $u !== null && \App\Support\Rbac\StaffCapability::for($u)->canMikrotik();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(128),
            Forms\Components\TextInput::make('subnet')->required()->placeholder('192.168.10.0/24'),
            Forms\Components\TextInput::make('gateway')->maxLength(45),
            Forms\Components\TextInput::make('dns_primary')->label('DNS 1')->maxLength(45),
            Forms\Components\TextInput::make('dns_secondary')->label('DNS 2')->maxLength(45),
            Forms\Components\Select::make('mikrotik_server_id')->relationship('mikrotikServer', 'name')->searchable(),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subnet')->copyable(),
                Tables\Columns\TextColumn::make('allocations_count')->counts('allocations')->label('IPs'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AllocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIpPools::route('/'),
            'create' => Pages\CreateIpPool::route('/create'),
            'edit' => Pages\EditIpPool::route('/{record}/edit'),
        ];
    }
}
