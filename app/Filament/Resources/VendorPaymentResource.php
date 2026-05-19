<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPaymentResource\Pages;
use App\Models\VendorPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorPaymentResource extends Resource
{
    protected static ?string $model = VendorPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Accounting';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('vendor_id')->relationship('vendor', 'name')->searchable()->required(),
            Forms\Components\DatePicker::make('payment_date')->required()->default(now()),
            Forms\Components\TextInput::make('amount')->numeric()->required(),
            Forms\Components\TextInput::make('vat_amount')->numeric()->default(0)->helperText('Input VAT claim'),
            Forms\Components\Select::make('payment_method')
                ->options(['bank' => 'Bank transfer', 'cash' => 'Cash', 'cheque' => 'Cheque'])
                ->default('bank')
                ->live()
                ->native(false),
            Forms\Components\Select::make('bank_account_id')
                ->relationship('bankAccount', 'name')
                ->visible(fn (Get $get): bool => $get('payment_method') === 'bank')
                ->nullable(),
            Forms\Components\TextInput::make('reference'),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('vendor.name')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('BDT'),
                Tables\Columns\TextColumn::make('vat_amount')->money('BDT'),
                Tables\Columns\TextColumn::make('payment_method')->badge(),
                Tables\Columns\TextColumn::make('journalEntry.entry_number')->label('Journal'),
            ])
            ->defaultSort('payment_date', 'desc')
            ->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorPayments::route('/'),
            'create' => Pages\CreateVendorPayment::route('/create'),
            'edit' => Pages\EditVendorPayment::route('/{record}/edit'),
        ];
    }
}
