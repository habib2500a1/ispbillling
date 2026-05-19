<?php

namespace App\Filament\Resources\SupportTicketResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversation & internal notes';

    protected static ?string $icon = 'heroicon-o-chat-bubble-left-right';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('body')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_internal')
                    ->label('Internal note (hidden from customer)')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('author')
                    ->label('From')
                    ->state(function ($record): string {
                        if ($record->is_internal) {
                            return '🔒 '.($record->user?->name ?? 'Staff');
                        }
                        if ($record->customer_id) {
                            return '👤 '.($record->customer?->name ?? 'Customer');
                        }

                        return '👤 '.($record->user?->name ?? 'Support');
                    }),
                Tables\Columns\IconColumn::make('is_internal')
                    ->boolean()
                    ->label('Internal'),
                Tables\Columns\TextColumn::make('body')
                    ->wrap()
                    ->html()
                    ->formatStateUsing(fn (string $state): string => nl2br(e($state))),
            ])
            ->defaultSort('created_at', 'asc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Public reply')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['customer_id'] = null;
                        $data['is_internal'] = false;

                        return $data;
                    }),
                Tables\Actions\CreateAction::make('internalNote')
                    ->label('Internal note')
                    ->icon('heroicon-o-lock-closed')
                    ->color('gray')
                    ->form([
                        Forms\Components\Textarea::make('body')
                            ->required()
                            ->rows(4),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['customer_id'] = null;
                        $data['is_internal'] = true;

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
            ])
            ->emptyStateDescription('Add a public reply for the customer or an internal note for your team.');
    }
}
