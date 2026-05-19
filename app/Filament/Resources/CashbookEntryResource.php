<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashbookEntryResource\Pages;
use App\Models\CashbookEntry;
use App\Models\ChartOfAccount;
use App\Support\AccountType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CashbookEntryResource extends Resource
{
    protected static ?string $model = CashbookEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationLabel = 'Cashbook';

    protected static ?string $navigationGroup = 'Accounting';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('entry_date')->required()->default(now()),
            Forms\Components\Select::make('direction')
                ->options(['in' => 'Receipt (cash in)', 'out' => 'Payment (cash out)'])
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('amount')->numeric()->required(),
            Forms\Components\TextInput::make('party_name')->required()->maxLength(255),
            Forms\Components\Select::make('chart_account_id')
                ->label('Category account')
                ->options(fn () => ChartOfAccount::query()
                    ->whereIn('type', [AccountType::INCOME, AccountType::EXPENSE])
                    ->orderBy('code')
                    ->pluck('name', 'id'))
                ->searchable()
                ->nullable(),
            Forms\Components\Select::make('payment_method')
                ->options(['cash' => 'Cash', 'mobile' => 'bKash/Nagad', 'cheque' => 'Cheque'])
                ->default('cash')
                ->native(false),
            Forms\Components\TextInput::make('reference'),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entry_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('party_name')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('BDT'),
                Tables\Columns\TextColumn::make('chartAccount.name')->label('Category'),
                Tables\Columns\TextColumn::make('journalEntry.entry_number')->label('JE'),
            ])
            ->defaultSort('entry_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('direction')->options(['in' => 'In', 'out' => 'Out']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashbookEntries::route('/'),
            'create' => Pages\CreateCashbookEntry::route('/create'),
        ];
    }
}
