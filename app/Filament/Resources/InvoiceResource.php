<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers;
use App\Models\Invoice;
use App\Services\Billing\BillingInvoiceCounts;
use App\Services\Billing\CouponApplicator;
use App\Services\Billing\InvoiceCalculator;
use App\Services\Billing\LateFeeCalculator;
use App\Services\Billing\StaffCollectionPaymentService;
use App\Support\BillingSidebarRegistry;
use App\Support\PaymentGateway;
use App\Support\Rbac\StaffCapability;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Invoices';

    protected static bool $shouldRegisterNavigation = false;

    public static function registerNavigationItems(): void
    {
        // Curated sidebar: BillingSidebarNavigation → BillingSidebarRegistry.
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getBillingNavigationItems(): array
    {
        try {
            $counts = app(BillingInvoiceCounts::class)->all();
        } catch (\Throwable) {
            $counts = [];
        }

        $items = [];

        foreach (BillingSidebarRegistry::items() as $entry) {
            $count = isset($entry['count_key']) ? ($counts[$entry['count_key']] ?? 0) : 0;
            $routes = $entry['active_routes'];

            $item = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('Billing')
                ->sort($entry['sort'])
                ->isActiveWhen(function () use ($routes, $entry): bool {
                    if (! request()->routeIs($routes)) {
                        return false;
                    }

                    if ($entry['key'] === 'today_collection') {
                        return request()->query('preset', 'today') === 'today';
                    }

                    if ($entry['key'] === 'all_collection') {
                        return request()->query('preset') === 'month';
                    }

                    return true;
                });

            if ($count > 0 && isset($entry['count_key'])) {
                $item->badge((string) $count);
            }

            $items[] = $item;
        }

        return $items;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice details')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('invoice_number')
                            ->maxLength(255)
                            ->helperText('Leave empty for auto number.')
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('issue_date')->required(),
                                Forms\Components\DatePicker::make('due_date')
                                    ->required()
                                    ->helperText('Payment due (grace for late fees is added after this).'),
                                Forms\Components\DatePicker::make('period_start')->required(),
                                Forms\Components\DatePicker::make('period_end')->required(),
                            ]),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'open' => 'Open',
                                'partial' => 'Partially paid',
                                'paid' => 'Paid',
                                'void' => 'Void',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('open')
                            ->native(false),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Forms\Components\Section::make('Amounts & payment')
                    ->icon('heroicon-o-calculator')
                    ->description('Add line items below (Items tab). Record payment from the header button “Record payment” or Billing → Bill collection.')
                    ->schema([
                        Forms\Components\Placeholder::make('calc_hint')
                            ->content('Line items drive subtotal. VAT/SD recalculate on save.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Manual discount (BDT)')
                            ->numeric()
                            ->default(0)
                            ->prefix('৳'),
                        Forms\Components\Select::make('coupon_id')
                            ->relationship('coupon', 'code')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\TextInput::make('coupon_discount_amount')
                            ->label('Coupon discount')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('subtotal')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('tax_amount')
                            ->label('VAT / tax')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('sd_amount')
                            ->label('Supplementary duty (SD)')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('withholding_amount')
                            ->label('Withholding (reporting)')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('total')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('amount_paid')
                            ->label('Amount paid (read-only — use Record payment)')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated()
                            ->prefix('৳'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issue_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Subscriber')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.billing_mode')
                    ->label('Mode')
                    ->badge()
                    ->colors([
                        'success' => 'prepaid',
                        'info' => 'advance',
                        'warning' => 'postpaid',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('issue_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null),
                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period')
                    ->formatStateUsing(fn (Invoice $record): string => $record->period_start?->format('d M')
                        .' – '.$record->period_end?->format('d M Y'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->money('BDT')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Due')
                    ->money('BDT')
                    ->getStateUsing(fn (Invoice $record): float => $record->balanceDue())
                    ->color(fn (Invoice $record): ?string => $record->balanceDue() > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('tax_amount')
                    ->label('VAT')
                    ->money('BDT')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money('BDT')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('coupon_discount_amount')
                    ->label('Coupon')
                    ->money('BDT')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'open',
                        'info' => 'partial',
                        'success' => 'paid',
                        'danger' => fn ($state) => in_array($state, ['void', 'cancelled'], true),
                    ]),
                Tables\Columns\IconColumn::make('past_grace')
                    ->label('Late')
                    ->boolean()
                    ->getStateUsing(fn (Invoice $record): bool => $record->isPastGrace())
                    ->trueColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'partial' => 'Partial',
                        'paid' => 'Paid',
                        'draft' => 'Draft',
                        'void' => 'Void',
                    ]),
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', ['open', 'partial'])
                        ->whereDate('due_date', '<', now()->toDateString())),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Subscriber')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('collect_payment')
                    ->label('Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => static::canCollectPaymentOnInvoice($record))
                    ->form(fn (Invoice $record): array => [
                        Forms\Components\Placeholder::make('summary')
                            ->label('Balance due')
                            ->content(number_format($record->balanceDue(), 2).' BDT'),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount received')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->default($record->balanceDue())
                            ->prefix('৳'),
                        Forms\Components\Select::make('method')
                            ->label('Payment method')
                            ->options([
                                PaymentGateway::CASH => 'Cash',
                                PaymentGateway::BKASH => 'bKash',
                                PaymentGateway::NAGAD => 'Nagad',
                                PaymentGateway::ROCKET => 'Rocket',
                                PaymentGateway::BANK => 'Bank transfer',
                                PaymentGateway::WALLET => 'Wallet / balance',
                                PaymentGateway::OTHER => 'Other',
                            ])
                            ->default(PaymentGateway::CASH)
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference / Txn ID')
                            ->maxLength(120),
                        Forms\Components\Textarea::make('notes')
                            ->label('Note')
                            ->rows(2)
                            ->helperText('Required for partial payment or discount.'),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        $user = auth()->user();
                        if ($user === null) {
                            return;
                        }

                        $record->loadMissing('customer');
                        $customer = $record->customer;
                        if ($customer === null) {
                            Notification::make()->title('Subscriber not found')->danger()->send();

                            return;
                        }

                        try {
                            $result = app(StaffCollectionPaymentService::class)->record(
                                $user,
                                $customer,
                                [
                                    'invoice_id' => $record->id,
                                    'amount' => (float) ($data['amount'] ?? 0),
                                    'method' => (string) ($data['method'] ?? PaymentGateway::CASH),
                                    'reference' => $data['reference'] ?? null,
                                    'notes' => (string) ($data['notes'] ?? ''),
                                    'discount_preset' => 'none',
                                ],
                                'admin-invoice-list',
                            );

                            Notification::make()
                                ->title('Payment recorded')
                                ->body($result['message'])
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Payment failed')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('apply_coupon')
                    ->label('Coupon')
                    ->icon('heroicon-o-ticket')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('coupon_code')
                            ->label('Coupon code')
                            ->required()
                            ->maxLength(64),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        try {
                            CouponApplicator::apply($record, (string) $data['coupon_code']);
                            Notification::make()->title('Coupon applied')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Coupon failed')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Invoice $record): bool => ! in_array($record->status, ['paid', 'void', 'cancelled'], true)),
                Tables\Actions\Action::make('apply_late_fee')
                    ->label('Late fee')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Invoice $record): void {
                        if (LateFeeCalculator::applyToInvoice($record)) {
                            Notification::make()->title('Late fee line added')->success()->send();
                        } else {
                            Notification::make()->title('No late fee due')->info()->send();
                        }
                    })
                    ->visible(fn (Invoice $record): bool => $record->isPastGrace()),
                Tables\Actions\Action::make('recalculate')
                    ->label('Recalc')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Invoice $record): void {
                        InvoiceCalculator::recalculate($record);
                        Notification::make()->title('Totals updated')->success()->send();
                    }),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Invoice $record): string => route('invoices.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('bkashPay')
                    ->label('bKash')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (Invoice $record): string => route('bkash.invoice.initiate', $record))
                    ->visible(fn (): bool => \App\Support\BkashSettings::isEnabledForChannel(\App\Support\BkashSettings::CHANNEL_ADMIN)),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'due' => Pages\ListDueInvoices::route('/due'),
            'paid' => Pages\ListPaidInvoices::route('/paid'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function canCollectPaymentOnInvoice(Invoice $record): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        if (! StaffCapability::for($user)->canCollect()) {
            return false;
        }

        if (in_array($record->status, ['paid', 'void', 'cancelled'], true)) {
            return false;
        }

        return $record->balanceDue() > 0.009;
    }
}
