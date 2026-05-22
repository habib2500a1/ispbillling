<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Support\InventoryWarehouseSelect;
use App\Models\Product;
use App\Services\Billing\StaffCollectionPaymentService;
use App\Services\Inventory\InvoiceHardwareLineService;
use App\Support\PaymentGateway;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recordPayment')
                ->label('Record payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => InvoiceResource::canCollectPaymentOnInvoice($this->getRecord()))
                ->form(fn (): array => [
                    Forms\Components\Placeholder::make('summary')
                        ->label('Balance due')
                        ->content(number_format($this->getRecord()->balanceDue(), 2).' BDT'),
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount received')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->default($this->getRecord()->balanceDue())
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
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();
                    if ($user === null) {
                        return;
                    }

                    $record = $this->getRecord();
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
                            'admin-invoice-edit',
                        );

                        Notification::make()
                            ->title('Payment recorded')
                            ->body($result['message'])
                            ->success()
                            ->send();

                        $this->refreshFormData(['amount_paid', 'status', 'total']);
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Payment failed')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('addHardware')
                ->label('Add hardware / product')
                ->icon('heroicon-o-cpu-chip')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->getRecord()->status, ['open', 'partial', 'draft'], true))
                ->form([
                    Forms\Components\Select::make('product_id')
                        ->label('Product')
                        ->options(fn () => Product::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('quantity')->numeric()->integer()->default(1)->minValue(1)->required(),
                    Forms\Components\TextInput::make('unit_price')->numeric()->label('Unit price (optional)'),
                    InventoryWarehouseSelect::make(),
                    Forms\Components\Toggle::make('issue_stock')->label('Deduct stock from warehouse')->default(true),
                ])
                ->action(function (array $data): void {
                    $product = Product::findOrFail($data['product_id']);
                    app(InvoiceHardwareLineService::class)->addProductLine(
                        $this->getRecord(),
                        $product,
                        (int) $data['quantity'],
                        isset($data['unit_price']) ? (float) $data['unit_price'] : null,
                        isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
                        (bool) ($data['issue_stock'] ?? false),
                        auth()->user(),
                    );
                    Notification::make()->title('Hardware line added to invoice')->success()->send();
                    $this->refreshFormData(['items']);
                }),
            Actions\Action::make('downloadPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('invoices.pdf', ['invoice' => $this->getRecord()]))
                ->openUrlInNewTab(),
            Actions\Action::make('bkashPay')
                ->label('Pay with bKash')
                ->icon('heroicon-o-banknotes')
                ->url(fn (): string => route('bkash.invoice.initiate', ['invoice' => $this->getRecord()]))
                ->visible(fn (): bool => \App\Support\BkashSettings::isEnabledForChannel(\App\Support\BkashSettings::CHANNEL_ADMIN)),
            Actions\DeleteAction::make(),
        ];
    }
}
