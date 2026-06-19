<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportAssignmentRuleResource\Pages;
use App\Models\PopBox;
use App\Models\SupportAssignmentRule;
use App\Support\SupportPanelAccess;
use App\Models\SupportTicket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupportAssignmentRuleResource extends Resource
{
    protected static ?string $model = SupportAssignmentRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Auto-assign rules';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('area_id')
                        ->label('Area (blank = any)')
                        ->relationship('area', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Forms\Components\Select::make('pop_box_id')
                        ->label('POP box (blank = any)')
                        ->options(fn (): array => PopBox::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->nullable(),
                    Forms\Components\Select::make('department')
                        ->label('Department (blank = any)')
                        ->options(SupportTicket::DEPARTMENTS)
                        ->nullable(),
                    Forms\Components\TextInput::make('skill_tag')
                        ->label('Skill tag')
                        ->helperText('Matches issue type or department substring — e.g. fiber, field_engineer')
                        ->maxLength(64)
                        ->nullable(),
                    Forms\Components\Toggle::make('vip_priority')
                        ->label('VIP / corporate only')
                        ->default(false),
                    Forms\Components\TextInput::make('max_open_tickets')
                        ->label('Max open tickets')
                        ->numeric()
                        ->minValue(1)
                        ->nullable(),
                ]),
                Forms\Components\Select::make('user_id')
                    ->label('Assign to user')
                    ->relationship('user', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('area.name')->label('Area')->placeholder('Any'),
                Tables\Columns\TextColumn::make('popBox.name')->label('POP')->placeholder('Any'),
                Tables\Columns\TextColumn::make('department')
                    ->formatStateUsing(fn (?string $state): string => $state ? (SupportTicket::DEPARTMENTS[$state] ?? $state) : 'Any'),
                Tables\Columns\TextColumn::make('skill_tag')->placeholder('—'),
                Tables\Columns\IconColumn::make('vip_priority')->boolean()->label('VIP'),
                Tables\Columns\TextColumn::make('max_open_tickets')->label('Cap')->placeholder('—'),
                Tables\Columns\TextColumn::make('user.name')->label('User'),
            ])
            ->defaultSort('sort_order')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSupportAssignmentRules::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return SupportPanelAccess::assignTickets(auth()->user());
    }

    public static function canCreate(): bool
    {
        return SupportPanelAccess::assignTickets(auth()->user());
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return SupportPanelAccess::assignTickets(auth()->user());
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return SupportPanelAccess::assignTickets(auth()->user());
    }
}
