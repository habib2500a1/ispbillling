<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CallFollowUpResource\Pages;
use App\Models\CallFollowUp;
use App\Models\Customer;
use App\Models\User;
use App\Support\SupportPanelAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CallFollowUpResource extends Resource
{
    protected static ?string $model = CallFollowUp::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Call follow-ups';

    protected static ?string $slug = 'call-follow-ups';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('customer_id')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                    ->where('name', 'like', '%'.$search.'%')
                    ->limit(20)
                    ->pluck('name', 'id')
                    ->all()),
            Forms\Components\TextInput::make('phone')->tel(),
            Forms\Components\TextInput::make('subject'),
            Forms\Components\DateTimePicker::make('scheduled_at')->required(),
            Forms\Components\Select::make('assigned_user_id')
                ->label('Operator')
                ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable(),
            Forms\Components\Select::make('status')
                ->options([
                    CallFollowUp::STATUS_PENDING => 'Pending',
                    CallFollowUp::STATUS_COMPLETED => 'Completed',
                    CallFollowUp::STATUS_CANCELLED => 'Cancelled',
                ])
                ->required(),
            Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer'),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('subject')->limit(30),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('assignedUser.name')->label('Operator'),
            ])
            ->defaultSort('scheduled_at')
            ->actions([Tables\Actions\EditAction::make()])
            ->headerActions([Tables\Actions\CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCallFollowUps::route('/'),
            'create' => Pages\CreateCallFollowUp::route('/create'),
            'edit' => Pages\EditCallFollowUp::route('/{record}/edit'),
        ];
    }
}
