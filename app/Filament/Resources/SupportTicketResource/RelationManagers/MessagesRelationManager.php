<?php

namespace App\Filament\Resources\SupportTicketResource\RelationManagers;

use App\Services\Support\SupportSlaService;
use App\Services\Support\SupportTicketAttachmentService;
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

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    protected function messageFormFields(bool $internal = false): array
    {
        return [
            Forms\Components\Textarea::make('body')
                ->required()
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\FileUpload::make('attachments')
                ->label('Attachments (photo, video, PDF)')
                ->multiple()
                ->disk('public')
                ->directory(fn (): string => 'ticket-messages/'.$this->getOwnerRecord()->tenant_id.'/'.$this->getOwnerRecord()->getKey())
                ->acceptedFileTypes([
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'application/pdf',
                    'video/mp4', 'video/quicktime', 'video/webm',
                ])
                ->maxSize(SupportTicketAttachmentService::MAX_SIZE_KB)
                ->downloadable()
                ->openable()
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_internal')
                ->label('Internal note (hidden from customer)')
                ->default($internal)
                ->visible(! $internal),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema($this->messageFormFields());
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
                Tables\Columns\TextColumn::make('attachments_list')
                    ->label('Files')
                    ->state(function ($record): string {
                        $record->loadMissing('attachments');
                        if ($record->attachments->isEmpty()) {
                            return '—';
                        }

                        return $record->attachments->map(
                            fn ($a): string => '<a href="'.e($a->url()).'" target="_blank" rel="noopener">'.e($a->original_name).'</a>',
                        )->implode('<br>');
                    })
                    ->html(),
            ])
            ->defaultSort('created_at', 'asc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Public reply')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->form($this->messageFormFields(false))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['customer_id'] = null;
                        $data['is_internal'] = false;

                        return $data;
                    })
                    ->after(function ($record, array $data): void {
                        app(SupportTicketAttachmentService::class)->attachPathsToMessage($record, $data['attachments'] ?? null);
                        app(SupportSlaService::class)->markFirstResponse($this->getOwnerRecord());
                    }),
                Tables\Actions\CreateAction::make('internalNote')
                    ->label('Internal note')
                    ->icon('heroicon-o-lock-closed')
                    ->color('gray')
                    ->form($this->messageFormFields(true))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['customer_id'] = null;
                        $data['is_internal'] = true;

                        return $data;
                    })
                    ->after(function ($record, array $data): void {
                        app(SupportTicketAttachmentService::class)->attachPathsToMessage($record, $data['attachments'] ?? null);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
