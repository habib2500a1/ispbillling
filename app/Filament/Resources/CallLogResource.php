<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CallLogResource\Pages;
use App\Models\CallLog;
use App\Models\Customer;
use App\Support\SupportPanelAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CallLogResource extends Resource
{
    protected static ?string $model = CallLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';

    protected static ?string $navigationLabel = 'Call logs';

    protected static ?string $slug = 'call-logs';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('customer_id')
                ->label('Customer')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->limit(20)
                    ->pluck('name', 'id')
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->find($value)?->name),
            Forms\Components\Select::make('direction')
                ->options([
                    CallLog::DIRECTION_INBOUND => 'Inbound',
                    CallLog::DIRECTION_OUTBOUND => 'Outbound',
                ])
                ->required(),
            Forms\Components\TextInput::make('phone')->tel(),
            Forms\Components\TextInput::make('staff_extension'),
            Forms\Components\Select::make('status')
                ->options([
                    'completed' => 'Completed',
                    'answered' => 'Answered',
                    'missed' => 'Missed',
                    'no_answer' => 'No answer',
                    'busy' => 'Busy',
                ])
                ->required(),
            Forms\Components\TextInput::make('duration_seconds')->numeric()->default(0),
            Forms\Components\DateTimePicker::make('started_at')->required(),
            Forms\Components\Textarea::make('remarks')->rows(2)->columnSpanFull(),
            Forms\Components\TextInput::make('recording_url')->url()->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('direction')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('duration_seconds')->label('Sec')->sortable(),
                Tables\Columns\TextColumn::make('staff_extension')->label('Ext'),
            ])
            ->defaultSort('started_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Log call'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCallLogs::route('/'),
            'create' => Pages\CreateCallLog::route('/create'),
            'edit' => Pages\EditCallLog::route('/{record}/edit'),
        ];
    }
}
