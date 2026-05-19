<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\AssignsCollectorOnPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillCollectionSearchService;
use App\Services\Collector\CollectorStaffResolver;
use App\Services\Collector\CollectorVisitService;
use App\Services\Billing\InvoiceCalculator;
use App\Services\Billing\PaymentAllocationCorrectionService;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BillCollectionDesk extends Page
{
    use AssignsCollectorOnPayment;

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

    public string $activeTab = 'collect';

    public ?int $editingPaymentId = null;

    public string $editPaymentAmount = '';

    public ?int $editPaymentInvoiceId = null;

    public string $editPaymentReference = '';

    public string $editPaymentNotes = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?int $accuracyMeters = null;

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

        if ($user->hasRole(['super-admin', 'isp-admin', 'admin', 'cashier', 'branch-manager'])) {
            return true;
        }

        return $user->can('payments.add') || $user->can('billing.view');
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

        if ($this->selectedCustomer === null) {
            $this->clearSelection();

            return;
        }

        $invoices = $this->selectedCustomer['invoices'] ?? [];
        if (count($invoices) === 1) {
            $this->invoiceId = $invoices[0]['id'];
            $this->amount = (string) $invoices[0]['balance_due'];
        } elseif ($this->selectedCustomer['balance_due'] > 0) {
            $this->amount = (string) $this->selectedCustomer['balance_due'];
        }
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

        $this->selectedCustomer = app(BillCollectionSearchService::class)->find($this->selectedCustomerId);
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
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string',
            'invoiceId' => 'nullable|integer|exists:invoices,id',
        ]);

        $customer = \App\Models\Customer::query()->findOrFail($this->selectedCustomerId);

        if ($this->invoiceId) {
            $invoice = \App\Models\Invoice::query()
                ->where('customer_id', $customer->id)
                ->findOrFail($this->invoiceId);
            $balanceDue = $invoice->balanceDue();
            if ($balanceDue <= 0) {
                throw ValidationException::withMessages([
                    'invoiceId' => 'This invoice has no balance due.',
                ]);
            }
            $payAmount = round((float) $this->amount, 2);
            if ($payAmount > $balanceDue + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount cannot exceed invoice due ('.number_format($balanceDue, 2).' BDT). Use partial pay for less than full due.',
                ]);
            }
        }

        $collectorId = $this->resolveCollectorIdForPayment();
        $collector = app(CollectorStaffResolver::class)->resolveCollectorUser($collectorId);

        $payment = Payment::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => $this->invoiceId,
            'payment_type' => PaymentType::PAYMENT,
            'amount' => round((float) $this->amount, 2),
            'method' => $this->method,
            'reference' => $this->reference ?: null,
            'notes' => $this->notes ?: null,
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $collectorId,
            'meta' => $this->collectorPaymentMeta($collectorId),
        ]);

        if (auth()->user() !== null) {
            app(CollectorVisitService::class)->logFromPayment($payment, $collector, [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'accuracy_meters' => $this->accuracyMeters,
                'device_meta' => ['source' => 'bill-collection-desk'],
            ]);
        }

        $body = 'Receipt '.$payment->receipt_number.' — '.number_format((float) $payment->amount, 2).' BDT';
        if ($this->invoiceId) {
            $invoice = Invoice::query()->find($this->invoiceId);
            if ($invoice !== null && $invoice->balanceDue() > 0) {
                $body .= ' · Remaining due '.number_format($invoice->balanceDue(), 2).' BDT (partial)';
            }
        }

        Notification::make()
            ->title('Payment collected')
            ->body($body)
            ->success()
            ->actions([
                \Filament\Notifications\Actions\Action::make('receipt')
                    ->label('Open receipt')
                    ->url(route('payments.receipt', $payment), shouldOpenInNewTab: true),
            ])
            ->send();

        $this->search = $customer->customer_code;
        $this->runSearch();
        $this->reloadCustomer();
    }

    public function selectedInvoiceBalanceDue(): ?float
    {
        if ($this->invoiceId === null || $this->invoiceId === '' || $this->selectedCustomer === null) {
            return null;
        }

        foreach ($this->selectedCustomer['invoices'] ?? [] as $inv) {
            if ((int) $inv['id'] === (int) $this->invoiceId) {
                return (float) $inv['balance_due'];
            }
        }

        return null;
    }

    public function partialPaymentRemaining(): ?float
    {
        $due = $this->selectedInvoiceBalanceDue();
        if ($due === null) {
            return null;
        }

        $amount = (float) $this->amount;
        if ($amount <= 0) {
            return $due;
        }

        return max(0.0, round($due - $amount, 2));
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
}
