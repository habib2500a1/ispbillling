<?php

namespace App\Filament\Resources\SupportTicketResource\RelationManagers;

use App\Models\FieldVisit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FieldVisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'fieldVisits';

    protected static ?string $title = 'Field visits';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('assigned_to')
                    ->label('Engineer')
                    ->relationship('assignee', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\Select::make('status')
                    ->options(FieldVisit::STATUSES)
                    ->required()
                    ->default('scheduled'),
                Forms\Components\DateTimePicker::make('scheduled_at')->required(),
                Forms\Components\DateTimePicker::make('started_at'),
                Forms\Components\DateTimePicker::make('completed_at'),
                Forms\Components\TextInput::make('latitude')->numeric()->nullable(),
                Forms\Components\TextInput::make('longitude')->numeric()->nullable(),
                Forms\Components\TextInput::make('location_text')->maxLength(255),
                Forms\Components\Textarea::make('report')->rows(4)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->formatStateUsing(fn (?string $state): string => FieldVisit::STATUSES[$state] ?? (string) $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('assignee.name')->placeholder('—'),
                Tables\Columns\TextColumn::make('location_text')->limit(40)->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
