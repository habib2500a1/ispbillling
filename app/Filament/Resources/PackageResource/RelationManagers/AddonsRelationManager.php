<?php

namespace App\Filament\Resources\PackageResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AddonsRelationManager extends RelationManager
{
    protected static string $relationship = 'addons';

    protected static ?string $title = 'Add-ons';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('addon_type')
                    ->options([
                        'extra_speed' => 'Extra speed',
                        'static_ip' => 'Static IP',
                        'extra_gb' => 'Extra GB',
                        'ott' => 'OTT subscription',
                        'cloud_storage' => 'Cloud storage',
                        'other' => 'Other',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('price_monthly')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('addon_type'),
                Tables\Columns\TextColumn::make('label'),
                Tables\Columns\TextColumn::make('price_monthly')->numeric(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();
                        $data['tenant_id'] = $owner->tenant_id;
                        $data['package_id'] = $owner->getKey();

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
