<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CustomersRelationManager extends RelationManager
{
    protected static string $relationship = 'customers';

    protected static ?string $title = 'Assigned subscribers';

    protected static ?string $icon = 'heroicon-o-users';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
