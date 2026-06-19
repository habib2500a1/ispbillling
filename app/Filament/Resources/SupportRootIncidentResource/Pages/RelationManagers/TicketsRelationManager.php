<?php

namespace App\Filament\Resources\SupportRootIncidentResource\Pages\RelationManagers;

use App\Filament\Resources\SupportTicketResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected static ?string $title = 'Linked tickets';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ticket_number')
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->searchable()
                    ->url(fn ($record) => SupportTicketResource::getUrl('edit', ['record' => $record])),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('priority')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->url(fn ($record) => SupportTicketResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
