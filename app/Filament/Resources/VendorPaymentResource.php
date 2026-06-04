<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPaymentResource\Pages;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorPaymentResource extends Resource
{
    protected static ?string $model = VendorPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?string $modelLabel = 'Expense';

    protected static ?string $pluralModelLabel = 'Expenses';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\ToggleButtons::make('expense_type')
                ->label('খরচের ধরন / Expense type')
                ->options(config('vendor_expenses.types', []))
                ->icons([
                    VendorPayment::TYPE_VENDOR => 'heroicon-o-building-storefront',
                    VendorPayment::TYPE_GENERAL => 'heroicon-o-receipt-percent',
                ])
                ->colors([
                    VendorPayment::TYPE_VENDOR => 'info',
                    VendorPayment::TYPE_GENERAL => 'warning',
                ])
                ->default(VendorPayment::TYPE_VENDOR)
                ->required()
                ->live()
                ->inline(false)
                ->columnSpanFull()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    if ($state === VendorPayment::TYPE_GENERAL) {
                        $set('vendor_id', null);
                    } else {
                        $set('expense_category', null);
                        $set('payee_name', null);
                    }
                }),
            Forms\Components\Placeholder::make('general_expense_hint')
                ->label('')
                ->content('Vendor list-এ নেই? Office rent, bill, transport, marketing — vendor ছাড়াই সব general expense এখানে add করুন।')
                ->visible(fn (Get $get): bool => $get('expense_type') === VendorPayment::TYPE_GENERAL)
                ->columnSpanFull(),
            Forms\Components\Section::make('Payment details')
                ->schema([
                    Forms\Components\Select::make('vendor_id')
                        ->label('Vendor / supplier')
                        ->relationship('vendor', 'name')
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $get('expense_type') === VendorPayment::TYPE_VENDOR)
                        ->visible(fn (Get $get): bool => $get('expense_type') === VendorPayment::TYPE_VENDOR)
                        ->helperText('No vendor in list? Use + to add a new supplier.')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->label('Vendor name')->required()->maxLength(255),
                            Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
                            Forms\Components\TextInput::make('email')->email()->maxLength(255),
                            Forms\Components\Textarea::make('address')->rows(2),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            $vendor = Vendor::query()->create([
                                'name' => $data['name'],
                                'phone' => $data['phone'] ?? null,
                                'email' => $data['email'] ?? null,
                                'address' => $data['address'] ?? null,
                                'is_active' => true,
                            ]);

                            return (int) $vendor->id;
                        }),
                    Forms\Components\Select::make('expense_category')
                        ->label('Expense category / খরচের ক্যাটাগরি')
                        ->options(config('vendor_expenses.general_categories', []))
                        ->required(fn (Get $get): bool => $get('expense_type') === VendorPayment::TYPE_GENERAL)
                        ->visible(fn (Get $get): bool => $get('expense_type') === VendorPayment::TYPE_GENERAL)
                        ->searchable()
                        ->native(false),
                    Forms\Components\TextInput::make('payee_name')
                        ->label('Paid to / কাকে দেওয়া')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('expense_type') === VendorPayment::TYPE_GENERAL)
                        ->placeholder('e.g. DESCO, office landlord, mechanic')
                        ->helperText('Optional — who received this payment'),
                    Forms\Components\DatePicker::make('payment_date')->required()->default(now()),
                    Forms\Components\TextInput::make('amount')->numeric()->required()->prefix('BDT'),
                    Forms\Components\TextInput::make('vat_amount')->numeric()->default(0)->helperText('Input VAT claim (if any)'),
                    Forms\Components\Select::make('payment_method')
                        ->options(['bank' => 'Bank transfer', 'cash' => 'Cash', 'cheque' => 'Cheque', 'bkash' => 'bKash / MFS'])
                        ->default('cash')
                        ->live()
                        ->native(false),
                    Forms\Components\Select::make('bank_account_id')
                        ->relationship('bankAccount', 'name')
                        ->visible(fn (Get $get): bool => $get('payment_method') === 'bank')
                        ->nullable(),
                    Forms\Components\TextInput::make('reference')->maxLength(64),
                    Forms\Components\Textarea::make('notes')->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('expense_type')
                    ->label('Type')
                    ->formatStateUsing(fn (VendorPayment $record): string => $record->typeLabel())
                    ->badge()
                    ->color(fn (VendorPayment $record): string => $record->isVendorExpense() ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Payee / vendor')
                    ->getStateUsing(fn (VendorPayment $record): string => $record->displayName())
                    ->searchable(['payee_name', 'vendor.name']),
                Tables\Columns\TextColumn::make('expense_category')
                    ->label('Category')
                    ->formatStateUsing(fn (VendorPayment $record): string => $record->categoryLabel() ?? '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')->money('BDT'),
                Tables\Columns\TextColumn::make('vat_amount')->money('BDT')->toggleable(isToggledHiddenByDefault: true),
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
