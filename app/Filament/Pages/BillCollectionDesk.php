<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\AssignsCollectorOnPayment;
use App\Filament\Pages\Concerns\HandlesCollectionDiscountAndNotes;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillCollectionSearchService;
use App\Services\Billing\BillingDueRealtimeSync;
use App\Services\Billing\OpenInvoiceResolver;
use App\Services\Billing\CollectionPaymentClassifier;
use App\Services\Collector\CollectorStaffResolver;
use App\Services\Collector\CollectorVisitService;
use App\Services\Billing\InvoiceCalculator;
use App\Services\Billing\PaymentAllocationCorrectionService;
use App\Services\Billing\PaymentVoidService;
use App\Support\PaymentGateway;
use App\Support\PaymentRenewalPolicy;
use App\Support\PaymentType;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BillCollectionDesk extends Page
{
    use AssignsCollectorOnPayment;
    use HandlesCollectionDiscountAndNotes;

    protected static ?string $navigationIcon = 'heroicon-o-currency-bangladeshi';

    protected static string $view = 'filament.pages.bill-collection-desk';

    protected static ?string $navigationLabel = 'Bill collection';

    protected static ?string $title = 'Bill collection desk';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'bill-collection';

    public string $search = '';

    /** @var Collection<int, array<string, mixed>> */
    public Collection $results;

    public ?int $selectedCustomerId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedCustomer = null;

    public ?int $invoiceId = null;

    public string $amount = '';

    public string $method = PaymentGateway::CASH;

    public string $reference = '';

    public string $notes = '';

    public bool $useCustomerWallet = false;

    public string $activeTab = 'collect';

    public ?int $editingPaymentId = null;

    public string $editPaymentAmount = '';

    public ?int $editPaymentInvoiceId = null;

    public string $editPaymentReference = '';

    public string $editPaymentNotes = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?int $accuracyMeters = null;

    public string $renewalPolicy = PaymentRenewalPolicy::DEFAULT;

    public function mount(): void
    {
        $this->results = collect();
        $this->mountCollectorAssignment();

        $customerId = request()->integer('customer');
        if ($customerId > 0) {
            $customer = \App\Models\Customer::query()->find($customerId);
            if ($customer !== null) {
                $this->search = $customer->customer_code ?: (string) $customer->id;
                $this->runSearch();
                $this->selectCustomer($customerId);

                $editPaymentId = request()->integer('edit_payment');
                if ($editPaymentId > 0) {
                    $this->startEditPayment($editPaymentId);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        $url = parent::getUrl($parameters, $isAbsolute, $panel, $tenant);

        if (isset($parameters['customer'])) {
            $url .= (str_contains($url, '?') ? '&' : '?').'customer='.(int) $parameters['customer'];
        }

        return $url;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        $capability = \App\Support\Rbac\StaffCapability::for($user);

        return $capability->canAny(['payments.add', 'collections.view', 'billing.view']);
    }

    public function updatedSearch(): void
    {
        $this->runSearch();
    }

    public function runSearch(): void
    {
        $this->results = app(BillCollectionSearchService::class)->search($this->search);
        if ($this->selectedCustomerId !== null && $this->results->where('id', $this->selectedCustomerId)->isEmpty()) {
            $this->clearSelection();
        }
    }

    public function selectCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->reloadCustomer();
        $this->activeTab = 'collect';
        $this->cancelEditPayment();
        $this->resetCollectionDiscountFields();

        if ($this->selectedCustomer === null) {
            $this->clearSelection();

            return;
        }

        $invoices = $this->selectedCustomer['invoices'] ?? [];
        if (count($invoices) === 1) {
            $this->invoiceId = (int) $invoices[0]['id'];
            $this->fillAmountFromSelectedInvoiceDue();
        } elseif (($this->selectedCustomer['balance_due'] ?? 0) > 0) {
            $this->amount = (string) round((float) $this->selectedCustomer['balance_due'], 2);
        }
    }

    /**
     * @return array<string, string>
     */
    public function getRenewalPolicyOptions(): array
    {
        return PaymentRenewalPolicy::options();
    }

    public function renewalPolicyHint(): string
    {
        if ($this->selectedCustomerId === null) {
            return '';
        }

        $customer = \App\Models\Customer::query()->find($this->selectedCustomerId);
        if ($customer === null) {
            return '';
        }

        return PaymentRenewalPolicy::describeForCustomer($customer, $this->renewalPolicy);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['collect', 'bills', 'history'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function clearSelection(): void
    {
        $this->selectedCustomerId = null;
        $this->selectedCustomer = null;
        $this->invoiceId = null;
        $this->amount = '';
        $this->reference = '';
        $this->notes = '';
        $this->renewalPolicy = PaymentRenewalPolicy::DEFAULT;
        $this->resetCollectionDiscountFields();
        $this->activeTab = 'collect';
        $this->cancelEditPayment();
    }

    public function recalculateInvoice(int $invoiceId): void
    {
        abort_unless($this->selectedCustomerId !== null, 403);

        $invoice = Invoice::query()
            ->where('customer_id', $this->selectedCustomerId)
            ->findOrFail($invoiceId);

        InvoiceCalculator::recalculate($invoice->fresh());
        $this->reloadCustomer();

        Notification::make()
            ->title('Invoice totals updated')
            ->body($invoice->invoice_number.' recalculated from line items and payments.')
            ->success()
            ->send();
    }

    public function startEditPayment(int $paymentId): void
    {
        abort_unless($this->selectedCustomerId !== null, 403);

        $payment = Payment::query()
            ->where('customer_id', $this->selectedCustomerId)
            ->findOrFail($paymentId);

        $this->editingPaymentId = $payment->id;
        $this->editPaymentAmount = (string) $payment->amount;
        $this->editPaymentInvoiceId = $payment->invoice_id;
        $this->editPaymentReference = (string) ($payment->reference ?? '');
        $this->editPaymentNotes = (string) ($payment->notes ?? '');
        $this->activeTab = 'history';
    }

    public function cancelEditPayment(): void
    {
        $this->editingPaymentId = null;
        $this->editPaymentAmount = '';
        $this->editPaymentInvoiceId = null;
        $this->editPaymentReference = '';
        $this->editPaymentNotes = '';
    }

    public function voidPayment(int $paymentId, ?string $reason = null): void
    {
        abort_unless($this->selectedCustomerId !== null, 403);

        $payment = Payment::query()
            ->where('customer_id', $this->selectedCustomerId)
            ->findOrFail($paymentId);

        app(PaymentVoidService::class)->void($payment, $reason);

        if ($this->editingPaymentId === $paymentId) {
            $this->cancelEditPayment();
        }

        $this->reloadCustomer();

        Notification::make()
            ->title('Payment voided')
            ->body('Collection removed and invoice/wallet balances adjusted.')
            ->success()
            ->send();
    }

    public function savePaymentCorrection(): void
    {
        abort_unless($this->selectedCustomerId !== null && $this->editingPaymentId !== null, 403);

        $this->validate([
            'editPaymentAmount' => 'required|numeric|min:0.01',
            'editPaymentInvoiceId' => 'nullable|integer|exists:invoices,id',
            'editPaymentReference' => 'nullable|string|max:255',
            'editPaymentNotes' => 'nullable|string|max:1000',
        ]);

        $payment = Payment::query()
            ->where('customer_id', $this->selectedCustomerId)
            ->findOrFail($this->editingPaymentId);

        app(PaymentAllocationCorrectionService::class)->reassign(
            $payment,
            $this->editPaymentInvoiceId ?: null,
            (float) $this->editPaymentAmount,
            $this->editPaymentReference ?: null,
            $this->editPaymentNotes ?: null,
        );

        $this->cancelEditPayment();
        $this->reloadCustomer();

        Notification::make()
            ->title('Collection corrected')
            ->body('Payment re-applied to the selected invoice. Invoice balances refreshed.')
            ->success()
            ->send();
    }

    private function reloadCustomer(): void
    {
        if ($this->selectedCustomerId === null) {
            $this->selectedCustomer = null;

            return;
        }

        // Reset so Livewire re-renders due amount/colour immediately after payment.
        $this->selectedCustomer = null;
        $this->selectedCustomer = app(BillCollectionSearchService::class)->find($this->selectedCustomerId);
    }

    private function refreshDueAfterPayment(\App\Models\Customer $customer): void
    {
        $due = BillingDueRealtimeSync::afterPayment($customer, queueNetwork: false);
        $this->search = $customer->customer_code;
        $this->runSearch();
        $this->reloadCustomer();
        $this->syncSearchResultDue((int) $customer->id, $due);

        if (($this->selectedCustomer['balance_due'] ?? 0) <= 0.009) {
            $this->amount = '';
            $this->invoiceId = null;
        }
    }

    private function syncSearchResultDue(int $customerId, float $due): void
    {
        $this->results = $this->results->map(function (array $row) use ($customerId, $due): array {
            if ((int) ($row['id'] ?? 0) !== $customerId) {
                return $row;
            }

            $row['balance_due'] = $due;
            $row['billing_payment_state'] = $due <= 0.009 ? 'paid' : ($row['billing_payment_state'] ?? 'partial');
            $row['open_invoices'] = $due <= 0.009 ? 0 : max(1, (int) ($row['open_invoices'] ?? 0));

            return $row;
        });
    }

    public function setGps(?float $lat, ?float $lng, ?int $accuracy = null): void
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
        $this->accuracyMeters = $accuracy;
    }

    public function collectPayment(): void
    {
        if ($this->invoiceId === '' || $this->invoiceId === 0) {
            $this->invoiceId = null;
        }

        $this->validate([
            'selectedCustomerId' => 'required|integer|exists:customers,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string',
            'invoiceId' => 'nullable|integer|exists:invoices,id',
            'notes' => 'nullable|string|max:1000',
            'collectorUserId' => 'nullable|integer|exists:users,id',
        ]);

        $customer = \App\Models\Customer::query()->findOrFail($this->selectedCustomerId);

        $payAmount = round((float) $this->amount, 2);
        $invoice = OpenInvoiceResolver::forCustomer($customer, $this->invoiceId);
        if ($invoice !== null) {
            $this->invoiceId = $invoice->id;
        } elseif ($payAmount > 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'No open bill with balance due for this customer.',
            ]);
        }

        $collectorId = $this->resolveCollectorIdForPayment();
        $collector = app(CollectorStaffResolver::class)->resolveCollectorUser($collectorId);

        $walletApplied = 0.0;
        if ($this->useCustomerWallet && $invoice !== null) {
            $walletBalance = (float) $customer->account_balance;
            $dueBefore = $invoice->fresh()->balanceDue();
            if ($walletBalance > 0 && $dueBefore > 0) {
                $walletApplied = round(min($walletBalance, $dueBefore), 2);
                if ($walletApplied > 0) {
                    Payment::createTrusted([
                        'tenant_id' => $customer->tenant_id,
                        'customer_id' => $customer->id,
                        'invoice_id' => $invoice->id,
                        'payment_type' => PaymentType::WALLET_APPLY,
                        'amount' => $walletApplied,
                        'method' => PaymentGateway::OTHER,
                        'reference' => 'wallet-apply',
                        'notes' => 'Applied from customer wallet at collection desk',
                        'status' => 'completed',
                        'paid_at' => now(),
                        'recorded_by' => $collectorId,
                        'meta' => array_merge(
                            $this->collectorPaymentMeta($collectorId),
                            $this->renewalPolicyMeta(),
                        ),
                    ]);
                    $invoice = $invoice->fresh();
                }
            }
        }

        $discountBdt = $this->validateCollectionPayment($invoice, $payAmount, $this->notes);

        if ($payAmount <= 0 && $walletApplied <= 0 && $discountBdt <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter cash amount, apply wallet, or give a discount.',
            ]);
        }

        $payment = null;
        if ($payAmount > 0) {
            $payment = Payment::createTrusted([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'invoice_id' => $this->invoiceId,
                'payment_type' => PaymentType::PAYMENT,
                'amount' => $payAmount,
                'method' => $this->method,
                'reference' => $this->reference ?: null,
                'notes' => $this->notes ?: null,
                'status' => 'completed',
                'paid_at' => now(),
                'recorded_by' => $collectorId,
                'meta' => CollectionPaymentClassifier::paymentMeta(
                    $customer,
                    $invoice,
                    $payAmount,
                    $discountBdt,
                    array_merge(
                        $this->collectorPaymentMeta($collectorId),
                        $this->collectionDiscountMeta($discountBdt),
                        $this->renewalPolicyMeta(),
                    ),
                ),
            ]);

            $this->applyCollectionDiscountIfNeeded($invoice, $discountBdt, $payment);
        } elseif ($discountBdt > 0 && $invoice !== null) {
            $payment = Payment::createTrusted([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'invoice_id' => $this->invoiceId,
                'payment_type' => PaymentType::PAYMENT,
                'amount' => 0.01,
                'method' => $this->method,
                'reference' => $this->reference ?: null,
                'notes' => $this->notes ?: null,
                'status' => 'completed',
                'paid_at' => now(),
                'recorded_by' => $collectorId,
                'meta' => array_merge(
                    $this->collectorPaymentMeta($collectorId),
                    $this->collectionDiscountMeta($discountBdt),
                    $this->renewalPolicyMeta(),
                ),
            ]);
            $this->applyCollectionDiscountIfNeeded($invoice, $discountBdt, $payment);
        }

        if ($payment !== null && auth()->user() !== null) {
            app(CollectorVisitService::class)->logFromPayment($payment->fresh(), $collector, [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'accuracy_meters' => $this->accuracyMeters,
                'device_meta' => ['source' => 'bill-collection-desk'],
            ]);
        }

        $body = $payment !== null
            ? 'Receipt '.$payment->receipt_number.' — '.number_format((float) $payment->amount, 2).' BDT'
            : 'Collection recorded';
        $body .= ' · Credited to '.$collector->name;
        if ((int) auth()->id() !== $collectorId) {
            $body .= ' (entered by '.auth()->user()?->name.')';
        }
        if ($walletApplied > 0) {
            $body .= ' · Wallet applied '.number_format($walletApplied, 2).' BDT';
        }
        if ($discountBdt > 0) {
            $body .= ' · Discount '.number_format($discountBdt, 2).' BDT';
        }
        if ($invoice !== null) {
            $invoice = $invoice->fresh();
            if ($invoice->balanceDue() > 0) {
                $body .= ' · Remaining due '.number_format($invoice->balanceDue(), 2).' BDT';
            }
        }

        $notification = Notification::make()
            ->title('Payment collected')
            ->body($body)
            ->success();

        if ($payment !== null) {
            $notification->actions([
                \Filament\Notifications\Actions\Action::make('receipt')
                    ->label('Open receipt')
                    ->url(route('payments.receipt', $payment), shouldOpenInNewTab: true),
            ]);
        }

        $this->resetCollectionDiscountFields();
        $this->refreshDueAfterPayment($customer);

        $notification->send();
    }

    /**
     * @return array<string, string>
     */
    public function getMethodOptions(): array
    {
        return [
            PaymentGateway::CASH => 'Cash',
            PaymentGateway::BANK => 'Bank transfer',
            PaymentGateway::BKASH => 'bKash',
            PaymentGateway::NAGAD => 'Nagad',
            PaymentGateway::ROCKET => 'Rocket',
            PaymentGateway::OTHER => 'Other',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function renewalPolicyMeta(): array
    {
        return ['renewal_policy' => $this->renewalPolicy];
    }
}
