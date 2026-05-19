<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Support\ResellerType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Sub-resellers';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('franchise_type')
                    ->options(ResellerType::labels())
                    ->default(ResellerType::SUB_RESELLER)
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->maxLength(255)
                    ->helperText('Leave blank for auto code.')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\Select::make('commission_type')
                    ->options([
                        'percent' => 'Percentage',
                        'fixed' => 'Fixed',
                    ])
                    ->required()
                    ->default('percent'),
                Forms\Components\TextInput::make('commission_value')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\TextInput::make('wallet_balance')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\Toggle::make('is_active')->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('wallet_balance')->numeric(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['parent_id'] = $this->getOwnerRecord()->getKey();

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
                ]),
            ]);
    }
}
