<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\CustomerDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static bool $isLazy = true;

    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents & KYC files';

    protected static ?string $icon = 'heroicon-o-document-arrow-up';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('document_type')
                    ->options(CustomerDocument::TYPES)
                    ->required()
                    ->native(false),
                Forms\Components\FileUpload::make('path')
                    ->label('File')
                    ->disk('local')
                    ->directory(fn (): string => 'subscribers/'.$this->getOwnerRecord()->getKey())
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/pdf',
                    ])
                    ->maxSize(8192)
                    ->required()
                    ->storeFileNamesIn('original_filename')
                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                        if (is_string($state) && $state !== '') {
                            $set('disk', 'local');
                        }
                    }),
                Forms\Components\Hidden::make('disk')->default('local'),
                Forms\Components\Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CustomerDocument::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->placeholder('—')
                    ->limit(32),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1).' KB' : '—'),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['uploaded_by'] = auth('web')->id();
                        if (isset($data['path']) && is_string($data['path'])) {
                            $data['size_bytes'] = Storage::disk($data['disk'] ?? 'local')->size($data['path']) ?: null;
                            $data['mime_type'] = Storage::disk($data['disk'] ?? 'local')->mimeType($data['path']) ?: null;
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (CustomerDocument $record) => Storage::disk($record->disk)->download(
                        $record->path,
                        $record->original_filename ?? basename($record->path),
                    )),
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
