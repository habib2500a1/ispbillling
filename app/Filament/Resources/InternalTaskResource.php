<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\InternalTaskResource\Pages;
use App\Models\InternalTask;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InternalTaskResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = InternalTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Tasks';

    protected static ?int $navigationSort = 15;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'in_progress' => 'In progress',
                    'done' => 'Done',
                    'cancelled' => 'Cancelled',
                ])
                ->required()
                ->default('pending')
                ->native(false),
            Forms\Components\Select::make('priority')
                ->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'])
                ->default('normal')
                ->native(false),
            Forms\Components\Select::make('assigned_to')
                ->label('Assign to')
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->nullable()
                ->native(false),
            Forms\Components\DateTimePicker::make('due_at'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('priority')->badge(),
                Tables\Columns\TextColumn::make('assignee.name')->label('Assigned'),
                Tables\Columns\TextColumn::make('due_at')->dateTime()->sortable(),
            ])
            ->defaultSort('due_at')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'in_progress' => 'In progress',
                    'done' => 'Done',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInternalTasks::route('/'),
            'create' => Pages\CreateInternalTask::route('/create'),
            'edit' => Pages\EditInternalTask::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'support';
    }
}
