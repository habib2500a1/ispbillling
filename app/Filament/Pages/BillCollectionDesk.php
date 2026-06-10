<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\AssignsCollectorOnPayment;
use App\Filament\Pages\Concerns\HandlesCollectionDiscountAndNotes;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\AdvanceInvoiceSyncService;
use App\Services\Billing\BillCollectionSearchService;
use App\Services\Billing\BillingDueRealtimeSync;
use App\Services\Billing\CustomerPrepayService;
use App\Services\Billing\OpenInvoiceResolver;
use App\Services\Billing\CollectionPaymentClassifier;
use App\Services\Collector\CollectorStaffResolver;
use App\Services\Collector\CollectorVisitService;
use App\Services\Billing\InvoiceCalculator;
use App\Services\Billing\PaymentAllocationCorrectionService;
use App\Services\Billing\PaymentVoidService;
use App\Services\Mobile\StaffBillingKpiResolver;
use App\Services\Resellers\ResellerPaymentAllocationService;
use App\Support\CustomerBalanceDue;
use App\Support\PaymentGateway;
use App\Support\PaymentRenewalPolicy;
use App\Support\PaymentType;
use App\Support\TenantResolver;
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

    /** all | due | paid */
    public string $searchFilter = 'all';

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

    /** all | legacy_portal — match pay.anetbd.com history for imported subscribers */
    public string $collectionHistoryFilter = 'all';

    public ?int $editingPaymentId = null;

    public string $editPaymentAmount = '';

    public ?int $editPaymentInvoiceId = null;

    public string $editPaymentReference = '';

    public string $editPaymentNotes = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?int $accuracyMeters = null;

    public string $renewalPolicy = PaymentRenewalPolicy::DEFAULT;

    public string $receiveFrom = '';

    public bool $sendSms = true;

    public bool $setNextBillingDate = true;

    /** bill | advance (recharge) */
    public string $collectionMode = 'bill';

    /** auto | wallet | specific invoice id as string */
    public string $paymentApplyTarget = 'auto';

    public ?int $advancePrepayMonths = null;

    /** @var array{due_clients: int, paid_clients: int, total_due: float} */
    public array $deskStats = [
        'due_clients' => 0,
        'paid_clients' => 0,
        'total_due' => 0.0,
    ];

    public function mount(): void
    {
        $this->results = collect();
        $this->mountCollectorAssignment();
        $this->refreshDeskStats();

        $legacySearch = trim((string) request()->query('q', ''));
        if ($legacySearch !== '') {
            $this->search = $legacySearch;
        }

        $filter = (string) request()->query('filter', 'all');
        if (in_array($filter, ['all', 'due', 'paid'], true)) {
            $this->searchFilter = $filter;
        }

        $openMode = (string) request()->query('mode', '');
        if ($openMode === 'advance') {
            $this->collectionMode = 'advance';
        }

        $customerId = request()->integer('customer');
        if ($customerId > 0) {
            $customer = \App\Models\Customer::withoutGlobalScopes()->find($customerId);
            if ($customer !== null) {
                $this->search = $customer->customer_code ?: (string) $customer->id;
                $this->runSearch();
                $this->selectCustomer($customerId);

                if ($openMode === 'advance' && ($this->selectedCustomer['balance_due'] ?? 0) <= 0.009) {
                    $this->enterRechargeMode();
                }

                $editPaymentId = request()->integer('edit_payment');
                if ($editPaymentId > 0) {
                    $this->startEditPayment($editPaymentId);
                }
            }
        } elseif ($this->search !== '') {
            $this->runSearch();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function queryString(): array
    {
        return [
            'search' => ['except' => '', 'as' => 'q'],
            'searchFilter' => ['except' => 'all', 'as' => 'filter'],
        ];
    }

    protected function refreshDeskStats(): void
    {
        $tenantId = TenantResolver::requiredTenantId();
        $billingKpis = app(StaffBillingKpiResolver::class);

        $this->deskStats = [
            'due_clients' => $billingKpis->dueClientsCount($tenantId),
            'paid_clients' => $billingKpis->paidClientsCount($tenantId),
            'total_due' => CustomerBalanceDue::tenantOpenInvoiceDueSum($tenantId),
        ];
    }

    public function setSearchFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'due', 'paid'], true)) {
            return;
        }

        $this->searchFilter = $filter;

        if (filled($this->search)) {
            $this->runSearch();
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        $customerId = isset($parameters['customer']) ? (int) $parameters['customer'] : null;
        unset($parameters['customer']);

        $url = parent::getUrl($parameters, $isAbsolute, $panel, $tenant);

        if ($customerId !== null && $customerId > 0) {
            $url .= (str_contains($url, '?') ? '&' : '?').'customer='.$customerId;
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

    public function updatedSearchFilter(): void
    {
        if (filled($this->search)) {
            $this->runSearch();
        }
    }

    public function runSearch(): void
    {
        $this->results = app(BillCollectionSearchService::class)->search(
            $this->search,
            filter: $this->searchFilter,
        );

        if ($this->selectedCustomerId !== null && $this->results->where('id', $this->selectedCustomerId)->isEmpty()) {
            $this->clearSelection();
        }

        if ($this->selectedCustomerId === null && $this->results->count() === 1) {
            $this->selectCustomer((int) $this->results->first()['id']);
        }
    }

    public function selectCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->reloadCustomer();
        $this->activeTab = 'collect';
        $this->cancelEditPayment();
        $this->resetCollectionDiscountFields();
        $this->ensureCollectorSelected();

        if ($this->selectedCustomer === null) {
            $this->clearSelection();

            return;
        }

        $invoices = $this->selectedCustomer['invoices'] ?? [];
        $this->receiveFrom = (string) ($this->selectedCustomer['name'] ?? '');

        if (($this->selectedCustomer['balance_due'] ?? 0) <= 0.009) {
            $this->enterRechargeMode();
        } elseif (count($invoices) === 1) {
            $this->collectionMode = 'bill';
            $this->advancePrepayMonths = null;
            $this->invoiceId = (int) $invoices[0]['id'];
            $this->paymentApplyTarget = (string) $this->invoiceId;
            $this->fillAmountFromSelectedInvoiceDue();
        } else {
            $this->collectionMode = 'bill';
            $this->advancePrepayMonths = null;
            $this->invoiceId = null;
            $this->paymentApplyTarget = 'auto';
            $this->amount = (string) round((float) $this->selectedCustomer['balance_due'], 2);
        }
    }

    public function setCollectionMode(string $mode): void
    {
        if (! in_array($mode, ['bill', 'advance'], true)) {
            return;
        }

        if ($mode === 'advance') {
            $this->enterRechargeMode();

            return;
        }

        $this->collectionMode = 'bill';
        $this->advancePrepayMonths = null;
        $this->paymentApplyTarget = 'auto';

        $invoices = $this->selectedCustomer['invoices'] ?? [];
        if (count($invoices) === 1) {
            $this->invoiceId = (int) $invoices[0]['id'];
            $this->paymentApplyTarget = (string) $this->invoiceId;
            $this->fillAmountFromSelectedInvoiceDue();
        } elseif (($this->selectedCustomer['balance_due'] ?? 0) > 0.009) {
            $this->invoiceId = null;
            $this->amount = (string) round((float) $this->selectedCustomer['balance_due'], 2);
        } else {
            $this->invoiceId = null;
            $this->amount = '';
        }
    }

    public function updatedPaymentApplyTarget(string $value): void
    {
        if ($value === 'wallet') {
            $this->invoiceId = null;

            return;
        }

        if ($value === 'auto') {
            $this->invoiceId = null;

            return;
        }

        $invoiceId = (int) $value;
        if ($invoiceId > 0) {
            $this->invoiceId = $invoiceId;
            $this->fillAmountFromSelectedInvoiceDue();
        }
    }

    public function applyRechargeMonths(int $months): void
    {
        if ($this->selectedCustomerId === null) {
            return;
        }

        $customer = \App\Models\Customer::query()->find($this->selectedCustomerId);
        if ($customer === null) {
            return;
        }

        $hasDue = ($this->selectedCustomer['balance_due'] ?? 0) > 0.009;
        $quote = app(CustomerPrepayService::class)->quote($customer, $months, includeCurrentDue: $hasDue);
        if ($quote === null) {
            return;
        }

        $this->collectionMode = 'advance';
        $this->advancePrepayMonths = $months;
        $this->invoiceId = null;
        $this->resetCollectionDiscountFields();
        $this->amount = (string) round(
            $hasDue ? (float) $quote['total_amount'] : (float) $quote['prepay_amount'],
            2,
        );
    }

    public function isRechargeMode(): bool
    {
        return $this->collectionMode === 'advance';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPrepayQuickOptions(): array
    {
        if ($this->selectedCustomerId === null) {
            return [];
        }

        $customer = \App\Models\Customer::query()->find($this->selectedCustomerId);
        if ($customer === null) {
            return [];
        }

        $prepay = app(CustomerPrepayService::class);
        if (! $prepay->isEnabled()) {
            return [];
        }

        $hasDue = ($this->selectedCustomer['balance_due'] ?? 0) > 0.009;
        $options = [];

        foreach ($prepay->quickMonthOptions() as $months) {
            $quote = $prepay->quote($customer, $months, includeCurrentDue: $hasDue);
            if ($quote !== null) {
                $options[] = $quote;
            }
        }

        return $options;
    }

    protected function enterRechargeMode(): void
    {
        $this->collectionMode = 'advance';
        $this->invoiceId = null;
        $this->paymentApplyTarget = 'wallet';
        $this->resetCollectionDiscountFields();
        $this->setNextBillingDate = true;

        $monthly = (float) ($this->selectedCustomer['monthly_bill'] ?? 0);
        if ($monthly <= 0 && $this->selectedCustomerId !== null) {
            $customer = \App\Models\Customer::query()->find($this->selectedCustomerId);
            $rate = $customer !== null ? app(CustomerPrepayService::class)->monthlyRate($customer) : null;
            $monthly = $rate !== null ? (float) $rate : 0.0;
        }

        if ($monthly > 0) {
            $this->advancePrepayMonths = 1;
            $this->amount = (string) round($monthly, 2);
        } else {
            $this->advancePrepayMonths = null;
            $this->amount = '';
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
        $this->receiveFrom = '';
        $this->sendSms = true;
        $this->setNextBillingDate = true;
        $this->collectionMode = 'bill';
        $this->paymentApplyTarget = 'auto';
        $this->advancePrepayMonths = null;
        $this->resetCollectionDiscountFields();
        $this->activeTab = 'collect';
        $this->cancelEditPayment();
    }

    public function payableAmount(): float
    {
        if ($this->isRechargeMode()) {
            return $this->receivedAmountNumeric();
        }

        $invoiceDue = $this->selectedInvoiceBalanceDue();

        if ($invoiceDue !== null) {
            return round($invoiceDue, 2);
        }

        return round((float) ($this->selectedCustomer['balance_due'] ?? 0), 2);
    }

    public function receivedAmountNumeric(): float
    {
        return is_numeric($this->amount) ? round((float) $this->amount, 2) : 0.0;
    }

    public function balanceDueAfterCollection(): float
    {
        if ($this->isRechargeMode()) {
            return 0.0;
        }

        $remaining = $this->partialPaymentRemaining();

        if ($remaining !== null) {
            return $remaining;
        }

        $payable = $this->payableAmount();
        $received = $this->receivedAmountNumeric();
        $discount = $this->previewCollectionDiscountBdt();

        return max(0.0, round($payable - $received - $discount, 2));
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

        $this->assertCanManagePayment($payment);

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

        $this->assertCanManagePayment($payment);

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

        $this->assertCanManagePayment($payment);

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

    /**
     * @return list<array<string, mixed>>
     */
    public function filteredCollectionHistory(): array
    {
        if ($this->selectedCustomer === null) {
            return [];
        }

        if ($this->collectionHistoryFilter === 'legacy_portal') {
            return $this->selectedCustomer['collection_history_legacy_portal']
                ?? array_values(array_filter(
                    $this->selectedCustomer['collection_history'] ?? [],
                    fn (array $row): bool => (bool) ($row['is_legacy_portal_import'] ?? false),
                ));
        }

        return $this->selectedCustomer['collection_history'] ?? [];
    }

    public function collectionHistoryTabLabel(): string
    {
        if ($this->selectedCustomer === null) {
            return 'Collection history';
        }

        $sync = $this->selectedCustomer['collection_sync'] ?? null;
        $all = count($this->selectedCustomer['collection_history'] ?? []);
        $isd = is_array($sync) ? (int) ($sync['legacy_portal_count'] ?? 0) : $all;

        if ($this->collectionHistoryFilter === 'legacy_portal' && is_array($sync) && ($sync['show_legacy_portal_hint'] ?? false)) {
            return \App\Support\BillingPortalLabel::collectionFilter()." ({$isd})";
        }

        return "Collection history ({$all})";
    }

    private function refreshDueAfterPayment(\App\Models\Customer $customer): void
    {
        $due = BillingDueRealtimeSync::afterPayment($customer, queueNetwork: true);
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

        $this->ensureCollectorSelected();

        $customer = \App\Models\Customer::query()->findOrFail($this->selectedCustomerId);

        $payAmount = round((float) $this->amount, 2);
        $advanceFifoMeta = [];

        if ($this->isRechargeMode()) {
            $this->invoiceId = null;
            $invoice = null;
            $advanceFifoMeta = $this->buildAdvanceFifoMeta($customer, $payAmount);
            $this->invoiceId = $advanceFifoMeta['primary_invoice_id'];
        } else {
            $invoice = null;
            $advanceFifoMeta = [];

            if ($this->paymentApplyTarget === 'wallet') {
                $this->invoiceId = null;
                if ($payAmount > 0.009) {
                    $advanceFifoMeta = [
                        'primary_invoice_id' => null,
                        'fifo_allocations' => [],
                        'wallet_surplus' => $payAmount,
                    ];
                }
            } elseif ($this->paymentApplyTarget === 'auto') {
                $this->invoiceId = null;
                if ($payAmount > 0.009) {
                    $advanceFifoMeta = $this->buildAdvanceFifoMeta($customer, $payAmount);
                    $this->invoiceId = $advanceFifoMeta['primary_invoice_id'];
                }
            } elseif (is_numeric($this->paymentApplyTarget) && (int) $this->paymentApplyTarget > 0) {
                $this->invoiceId = (int) $this->paymentApplyTarget;
                $invoice = OpenInvoiceResolver::forCustomer($customer, $this->invoiceId);
            } elseif ($this->invoiceId !== null && $this->invoiceId > 0) {
                $invoice = OpenInvoiceResolver::forCustomer($customer, $this->invoiceId);
            } else {
                $this->invoiceId = null;
            }

            if (
                $invoice === null
                && $advanceFifoMeta === []
                && $payAmount > 0.009
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'No open bill with balance due. Choose wallet / recharge for advance payment.',
                ]);
            }
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

        $discountBdt = $this->validateCollectionPayment(
            ($this->isRechargeMode() || in_array($this->paymentApplyTarget, ['wallet', 'auto'], true))
                ? null
                : $invoice,
            $payAmount,
            $this->notes,
        );

        if ($payAmount <= 0 && $walletApplied <= 0 && $discountBdt <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter cash amount, apply wallet, or give a discount.',
            ]);
        }

        $payment = null;
        if ($payAmount > 0) {
            $paymentMeta = array_merge(
                $this->collectorPaymentMeta($collectorId),
                $this->collectionDiscountMeta($discountBdt),
                $this->renewalPolicyMeta(),
            );

            if ($this->isRechargeMode()) {
                $paymentMeta = array_merge($paymentMeta, [
                    'collection_type' => 'advance',
                    'allocation_mode' => ResellerPaymentAllocationService::MODE_ADVANCE,
                ]);

                if ($advanceFifoMeta['fifo_allocations'] !== []) {
                    $paymentMeta['fifo_allocations'] = $advanceFifoMeta['fifo_allocations'];
                    $paymentMeta['wallet_surplus'] = $advanceFifoMeta['wallet_surplus'];
                    $paymentMeta['fifo_multi_invoice'] = true;
                    $paymentMeta['allocation_mode'] = ResellerPaymentAllocationService::MODE_FIFO;
                }
            } elseif ($this->paymentApplyTarget === 'wallet') {
                $paymentMeta = array_merge($paymentMeta, [
                    'collection_type' => 'advance',
                    'allocation_mode' => ResellerPaymentAllocationService::MODE_ADVANCE,
                ]);
            } elseif ($advanceFifoMeta !== []) {
                $paymentMeta['fifo_allocations'] = $advanceFifoMeta['fifo_allocations'];
                $paymentMeta['wallet_surplus'] = $advanceFifoMeta['wallet_surplus'];
                $paymentMeta['fifo_multi_invoice'] = true;
                $paymentMeta['allocation_mode'] = ResellerPaymentAllocationService::MODE_FIFO;
            } else {
                $paymentMeta = CollectionPaymentClassifier::paymentMeta(
                    $customer,
                    $invoice,
                    $payAmount,
                    $discountBdt,
                    $paymentMeta,
                );
            }

            $payment = Payment::createTrusted([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'invoice_id' => $this->invoiceId,
                'payment_type' => PaymentType::PAYMENT,
                'amount' => $payAmount,
                'method' => $this->method,
                'reference' => $this->reference ?: null,
                'notes' => $this->collectionNotesForStorage() ?: null,
                'status' => 'completed',
                'paid_at' => now(),
                'recorded_by' => $collectorId,
                'meta' => $paymentMeta,
            ]);

            $this->applyCollectionDiscountIfNeeded($invoice, $discountBdt, $payment);

            if (
                $payment !== null
                && $this->advancePrepayMonths !== null
                && $this->advancePrepayMonths > 0
                && CollectionPaymentClassifier::isAdvancePayment($payment->fresh())
            ) {
                app(AdvanceInvoiceSyncService::class)->syncForwardInvoices(
                    $customer->fresh(),
                    $this->advancePrepayMonths,
                    $payment->fresh(),
                );
            }
        } elseif ($discountBdt > 0 && $invoice !== null) {
            $payment = Payment::createTrusted([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'invoice_id' => $this->invoiceId,
                'payment_type' => PaymentType::PAYMENT,
                'amount' => 0.01,
                'method' => $this->method,
                'reference' => $this->reference ?: null,
                'notes' => $this->collectionNotesForStorage() ?: null,
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

        $isAdvance = false;

        if ($payment !== null) {
            $payment = $payment->fresh();
            $isAdvance = CollectionPaymentClassifier::isAdvancePayment($payment);
            $body = 'Receipt '.$payment->receipt_number.' — '.number_format((float) $payment->amount, 2).' BDT';
        } else {
            $body = 'Collection recorded';
        }

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
            ->title($isAdvance ? 'Recharge recorded' : 'Payment collected')
            ->body($isAdvance ? 'Advance payment · '.$body : $body)
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
        $this->refreshDeskStats();

        if ($isAdvance) {
            $this->collectionHistoryFilter = 'all';
            $this->activeTab = 'history';
            $walletBalance = (float) ($this->selectedCustomer['account_balance'] ?? 0);
            if ($walletBalance > 0.009) {
                $body .= ' · Wallet balance '.number_format($walletBalance, 2).' BDT';
            }
            if (($this->selectedCustomer['balance_due'] ?? 0) <= 0.009) {
                $this->enterRechargeMode();
            }
        } elseif ($advanceFifoMeta !== [] && ($advanceFifoMeta['wallet_surplus'] ?? 0) > 0.009) {
            $walletBalance = (float) ($this->selectedCustomer['account_balance'] ?? 0);
            $body .= ' · Wallet credited '.number_format((float) $advanceFifoMeta['wallet_surplus'], 2).' BDT';
            if ($walletBalance > 0.009) {
                $body .= ' (balance '.number_format($walletBalance, 2).' BDT)';
            }
        }

        $notification->send();
    }

    /**
     * Recharge/advance: clear open bills first (FIFO), then credit the remainder to wallet.
     *
     * @return array{primary_invoice_id: ?int, fifo_allocations: list<array<string, mixed>>, wallet_surplus: float}
     */
    private function buildAdvanceFifoMeta(\App\Models\Customer $customer, float $payAmount): array
    {
        $remaining = round($payAmount, 2);
        $allocations = [];

        foreach (OpenInvoiceResolver::openInvoicesWithBalance($customer) as $openInvoice) {
            if ($remaining <= 0.009) {
                break;
            }

            $due = $openInvoice->balanceDue();
            $apply = round(min($remaining, $due), 2);
            if ($apply <= 0) {
                continue;
            }

            $allocations[] = [
                'invoice_id' => $openInvoice->id,
                'invoice_number' => (string) $openInvoice->invoice_number,
                'amount' => $apply,
            ];
            $remaining = round($remaining - $apply, 2);
        }

        return [
            'primary_invoice_id' => $allocations[0]['invoice_id'] ?? null,
            'fifo_allocations' => $allocations,
            'wallet_surplus' => max(0.0, $remaining),
        ];
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
        $meta = ['renewal_policy' => $this->renewalPolicy];

        if (! $this->setNextBillingDate) {
            $meta['skip_billing_date_update'] = true;
        }

        if (! $this->sendSms) {
            $meta['skip_customer_sms'] = true;
        }

        if (filled($this->receiveFrom)) {
            $meta['receive_from'] = trim($this->receiveFrom);
        }

        if ($this->advancePrepayMonths !== null && $this->advancePrepayMonths > 0) {
            $meta['prepay_months'] = $this->advancePrepayMonths;
        }

        return $meta;
    }

    private function collectionNotesForStorage(): ?string
    {
        $notes = trim($this->notes);
        $receiveFrom = trim($this->receiveFrom);

        if ($receiveFrom !== '' && $receiveFrom !== trim((string) ($this->selectedCustomer['name'] ?? ''))) {
            $prefix = 'Receive from: '.$receiveFrom;

            return $notes !== '' ? $prefix.' — '.$notes : $prefix;
        }

        return $notes !== '' ? $notes : null;
    }

    protected function assertCanManagePayment(Payment $payment): void
    {
        if (app(CollectorStaffResolver::class)->canPickCollector()) {
            return;
        }

        abort_unless(
            app(CollectorStaffResolver::class)->paymentBelongsToCollector($payment, (int) auth()->id()),
            403,
            'You can only change collections credited to your own name.',
        );
    }
}
