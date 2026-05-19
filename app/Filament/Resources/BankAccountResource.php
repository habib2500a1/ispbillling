<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankAccountResource\Pages;
use App\Models\BankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Accounting';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('bank_name'),
            Forms\Components\TextInput::make('account_number'),
            Forms\Components\TextInput::make('branch'),
            Forms\Components\Select::make('chart_account_id')
                ->relationship('chartAccount', 'name', fn ($q) => $q->where('code', 'like', '11%'))
                ->searchable()
                ->preload()
                ->nullable(),
            Forms\Components\TextInput::make('opening_balance')->numeric()->default(0),
            Forms\Components\TextInput::make('current_balance')->numeric()->default(0)->disabledOn('create'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('bank_name'),
            Tables\Columns\TextColumn::make('account_number')->fontFamily('mono'),
            Tables\Columns\TextColumn::make('current_balance')->money('BDT')->sortable(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}
