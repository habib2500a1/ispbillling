<?php

namespace App\Filament\Resources\IpPoolResource\RelationManagers;

use App\Models\Customer;
use App\Models\IpAllocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('ip_address')->required()->maxLength(45),
            Forms\Components\Select::make('status')->options([
                IpAllocation::STATUS_FREE => 'Free',
                IpAllocation::STATUS_ASSIGNED => 'Assigned',
                IpAllocation::STATUS_RESERVED => 'Reserved',
            ])->required(),
            Forms\Components\Select::make('customer_id')
                ->options(fn () => Customer::query()->limit(200)->pluck('name', 'id'))
                ->searchable(),
            Forms\Components\Textarea::make('notes'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip_address')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
