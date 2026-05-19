<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SupportTicketsRelationManager extends RelationManager
{
    protected static bool $isLazy = true;

    protected static string $relationship = 'supportTickets';

    protected static ?string $title = 'Complaint & ticket history';

    protected static ?string $icon = 'heroicon-o-lifebuoy';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ticket_number')
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label('Ticket')
                    ->searchable()
                    ->url(fn (SupportTicket $record): string => SupportTicketResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('subject')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SupportTicket::PRIORITIES[$state] ?? (string) $state),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SupportTicket::STATUSES[$state] ?? (string) $state),
                Tables\Columns\TextColumn::make('channel')
                    ->formatStateUsing(fn (SupportTicket $record): string => $record->channelLabel()),
                Tables\Columns\TextColumn::make('assignee.name')->label('Technician')->placeholder('—'),
                Tables\Columns\TextColumn::make('sla_resolve_due_at')
                    ->label('SLA')
                    ->formatStateUsing(fn (SupportTicket $record): string => $record->slaRemainingLabel()),
                Tables\Columns\TextColumn::make('created_at')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(SupportTicket::STATUSES),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (SupportTicket $record): string => SupportTicketResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }
}
