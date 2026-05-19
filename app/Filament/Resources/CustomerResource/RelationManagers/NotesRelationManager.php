<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\CustomerNote;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static bool $isLazy = true;

    protected static string $relationship = 'customerNotes';

    protected static ?string $title = 'Notes & history';

    protected static ?string $icon = 'heroicon-o-chat-bubble-left-right';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category')
                    ->options(CustomerNote::CATEGORIES)
                    ->required()
                    ->default('general')
                    ->native(false),
                Forms\Components\Toggle::make('is_pinned')
                    ->label('Pin to top'),
                Forms\Components\Textarea::make('body')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_pinned')
                    ->boolean()
                    ->label('Pin'),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CustomerNote::CATEGORIES[$state] ?? $state),
                Tables\Columns\TextColumn::make('body')
                    ->wrap()
                    ->limit(120),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Staff')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort(fn ($query) => $query->orderByDesc('is_pinned')->orderByDesc('created_at'))
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => array_merge($data, [
                        'user_id' => auth('web')->id(),
                    ])),
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
