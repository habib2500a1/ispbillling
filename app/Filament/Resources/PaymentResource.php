<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\PaymentVoidService;
use App\Services\Payments\PaymentProcessor;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Payments';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('payment_tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Entry')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Forms\Components\Select::make('payment_type')
                                    ->options(PaymentType::options())
                                    ->required()
                                    ->default(PaymentType::PAYMENT)
                                    ->live()
                                    ->native(false),
                                Forms\Components\Select::make('customer_id')
                                    ->relationship('customer', 'name')
                                    ->searchable(['name', 'customer_code', 'phone', 'mikrotik_secret_name', 'radius_username', 'email', 'address', 'nid_number'])
                                    ->preload()
                                    ->required()
                                    ->live(),
                                Forms\Components\Select::make('invoice_id')
                                    ->label('Invoice (optional)')
                                    ->options(function (Get $get): array {
                                        $customerId = $get('customer_id');
                                        if (! $customerId) {
                                            return [];
                                        }

                                        return Invoice::query()
                                            ->where('customer_id', $customerId)
                                            ->whereIn('status', ['open', 'partial', 'draft'])
                                            ->orderByDesc('issue_date')
                                            ->get()
                                            ->mapWithKeys(fn (Invoice $inv): array => [
                                                $inv->id => $inv->invoice_number.' — due '.number_format($inv->balanceDue(), 2).' BDT',
                                            ])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->nullable()
                                    ->visible(fn (Get $get): bool => ! in_array($get('payment_type'), [
                                        PaymentType::WALLET_DEPOSIT,
                                    ], true)),
                                Forms\Components\TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->prefix('৳'),
                                Forms\Components\Select::make('method')
                                    ->options(PaymentGateway::options())
                                    ->required()
                                    ->default(PaymentGateway::CASH)
                                    ->native(false),
                                Forms\Components\TextInput::make('reference')
                                    ->maxLength(255)
                                    ->placeholder('TRX ID, cheque #, etc.'),
                                Forms\Components\TextInput::make('gateway_transaction_id')
                                    ->label('Gateway transaction ID')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => in_array($get('method'), PaymentGateway::webhookGateways(), true)),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'pending' => 'Pending (awaiting confirmation)',
                                        'completed' => 'Completed',
                                        'failed' => 'Failed',
                                        'void' => 'Void (removed)',
                                    ])
                                    ->required()
                                    ->default('completed')
                                    ->native(false),
                                Forms\Components\DateTimePicker::make('paid_at')
                                    ->default(now()),
                                Forms\Components\Select::make('meta.adjustment_direction')
                                    ->label('Adjustment direction')
                                    ->options([
                                        'credit_invoice' => 'Credit invoice (increase paid)',
                                        'debit_invoice' => 'Debit invoice (reduce paid)',
                                        'credit_wallet' => 'Credit wallet only',
                                    ])
                                    ->default('credit_invoice')
                                    ->visible(fn (Get $get): bool => $get('payment_type') === PaymentType::ADJUSTMENT),
                                Forms\Components\Textarea::make('notes')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Proof')
                            ->icon('heroicon-o-paper-clip')
                            ->schema([
                                Forms\Components\FileUpload::make('proof_path')
                                    ->label('Payment proof (screenshot / slip)')
                                    ->disk('public')
                                    ->directory('payment-proofs')
                                    ->visibility('public')
                                    ->image()
                                    ->downloadable()
                                    ->openable()
                                    ->nullable()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Receipt')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Subscriber')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('—')
                    ->url(fn (Payment $record): ?string => $record->invoice_id
                        ? InvoiceResource::getUrl('edit', ['record' => $record->invoice_id])
                        : null),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (Payment $record): string => $record->typeLabel())
                    ->color(fn (Payment $record): string => match ($record->payment_type) {
                        PaymentType::REFUND => 'danger',
                        PaymentType::WALLET_DEPOSIT, PaymentType::WALLET_APPLY => 'info',
                        PaymentType::ADJUSTMENT => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (Payment $record): string => $record->methodLabel()),
                Tables\Columns\TextColumn::make('amount')
                    ->money('BDT')
                    ->color(fn (Payment $record): ?string => $record->isRefund() ? 'danger' : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference')
                    ->limit(20)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('gateway_transaction_id')
                    ->label('Gateway TX')
                    ->limit(16)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'gray' => 'void',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'void' => 'Void',
                    ]),
                Tables\Filters\SelectFilter::make('payment_type')
                    ->options(PaymentType::options()),
                Tables\Filters\SelectFilter::make('method')
                    ->options(PaymentGateway::options()),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Subscriber')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-document-check')
                    ->url(fn (Payment $record): string => route('payments.receipt', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Payment $record): bool => $record->status === 'completed'),
                Tables\Actions\Action::make('mark_completed')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record): bool => $record->status === 'pending')
                    ->action(function (Payment $record): void {
                        $record->update(['status' => 'completed', 'paid_at' => $record->paid_at ?? now()]);
                        Notification::make()->title('Payment completed')->success()->send();
                    }),
                \App\Filament\Support\TransferMfsPaymentAction::forPayment()
                    ->label('Transfer ID'),
                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Payment $record): bool => $record->status === 'completed'
                        && $record->payment_type === PaymentType::PAYMENT
                        && $record->refunds()->count() === 0)
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->default(fn (Payment $record): float => (float) $record->amount),
                        Forms\Components\Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        PaymentProcessor::recordRefund(
                            $record,
                            (float) $data['amount'],
                            $data['notes'] ?? null,
                        );
                        Notification::make()->title('Refund recorded')->success()->send();
                    }),
                Tables\Actions\Action::make('void')
                    ->label('Void / delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Void this payment?')
                    ->modalDescription('Invoice paid amount and customer wallet will be adjusted back. This cannot be undone.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(2)
                            ->placeholder('Wrong amount / wrong customer / duplicate entry'),
                    ])
                    ->visible(fn (Payment $record): bool => app(PaymentVoidService::class)->canVoid($record))
                    ->action(function (Payment $record, array $data): void {
                        app(PaymentVoidService::class)->void($record, $data['reason'] ?? null);
                        Notification::make()->title('Payment voided')->success()->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Payment $record): bool => $record->status !== 'void'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
