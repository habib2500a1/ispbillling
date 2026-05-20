<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\AssignsCollectorOnPayment;
use App\Filament\Pages\Concerns\HandlesCollectionDiscountAndNotes;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillCollectionSearchService;
use App\Services\Collector\CollectorCollectionReportService;
use App\Services\Collector\CollectorStaffResolver;
use App\Services\Collector\CollectorVisitService;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class CollectorMobile extends Page
{
    use AssignsCollectorOnPayment;
    use HandlesCollectionDiscountAndNotes;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string $view = 'filament.pages.collector-mobile';

    protected static ?string $navigationLabel = 'Collector mobile';

    protected static ?string $title = 'Field collection';

    protected static ?string $navigationGroup = 'Billing';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'collector-mobile';

    protected static ?int $navigationSort = 3;

    public string $search = '';

    public string $panelTab = 'collect';

    /** @var Collection<int, array<string, mixed>> */
    public Collection $results;

    public ?int $selectedCustomerId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedCustomer = null;

    public ?int $invoiceId = null;

    public string $amount = '';

    public string $method = PaymentGateway::CASH;

    public string $notes = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?int $accuracyMeters = null;

    public function mount(): void
    {
        $this->results = collect();
        $this->mountCollectorAssignment();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return \App\Support\Rbac\StaffCapability::for($user)->canCollect()
            || $user->can('collections.view')
            || $user->can('collections.settle');
    }

    /**
     * @return array<string, mixed>
     */
    public function getTodaySummary(): array
    {
        $report = app(CollectorCollectionReportService::class);

        if ($this->canPickCollector()) {
            return $report->todaySummary();
        }

        $collectorId = app(CollectorStaffResolver::class)->defaultCollectorId();
        $mine = $report->collectorTodayTotal($collectorId);

        return [
            'total' => $mine,
            'count' => 0,
            'by_collector' => [
                [
                    'collector_id' => $collectorId,
                    'name' => auth()->user()?->name ?? 'Me',
                    'total' => $mine,
                    'count' => 0,
                ],
            ],
            'mine_only' => true,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getRecentCollections(): Collection
    {
        $collectorId = $this->canPickCollector() ? null : app(CollectorStaffResolver::class)->defaultCollectorId();

        return app(CollectorCollectionReportService::class)->recentCollections(20, $collectorId);
    }

    public function updatedSearch(): void
    {
        $this->runSearch();
    }

    public function runSearch(): void
    {
        if (strlen(trim($this->search)) < 2) {
            $this->results = collect();

            return;
        }

        $this->results = app(BillCollectionSearchService::class)->search($this->search);
    }

    public function selectCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->selectedCustomer = app(BillCollectionSearchService::class)->find($customerId);
        $this->invoiceId = null;
        $this->notes = '';
        $this->resetCollectionDiscountFields();

        if ($this->selectedCustomer !== null) {
            $invoices = $this->selectedCustomer['invoices'] ?? [];
            if (count($invoices) === 1) {
                $this->invoiceId = (int) $invoices[0]['id'];
                $this->fillAmountFromSelectedInvoiceDue();
            } elseif (($this->selectedCustomer['balance_due'] ?? 0) > 0) {
                $this->amount = (string) round((float) $this->selectedCustomer['balance_due'], 2);
            }
        }
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
            'collectorUserId' => 'nullable|integer|exists:users,id',
            'invoiceId' => 'nullable|integer|exists:invoices,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $customer = Customer::query()->findOrFail($this->selectedCustomerId);
        $invoice = null;
        if ($this->invoiceId) {
            $invoice = Invoice::query()
                ->where('customer_id', $customer->id)
                ->findOrFail($this->invoiceId);
        }

        $payAmount = round((float) $this->amount, 2);
        $discountBdt = $this->validateCollectionPayment($invoice, $payAmount, $this->notes);

        $collectorId = $this->resolveCollectorIdForPayment();
        $collector = app(CollectorStaffResolver::class)->resolveCollectorUser($collectorId);

        $payment = Payment::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => $this->invoiceId,
            'payment_type' => PaymentType::PAYMENT,
            'amount' => $payAmount,
            'method' => $this->method,
            'notes' => $this->notes ?: null,
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $collectorId,
            'meta' => array_merge(
                $this->collectorPaymentMeta($collectorId),
                $this->collectionDiscountMeta($discountBdt),
            ),
        ]);

        $this->applyCollectionDiscountIfNeeded($invoice, $discountBdt, $payment);

        app(CollectorVisitService::class)->logFromPayment($payment->fresh(), $collector, [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy_meters' => $this->accuracyMeters,
            'device_meta' => [
                'source' => 'collector-mobile',
                'entered_by' => auth()->id(),
            ],
        ]);

        $body = number_format((float) $payment->amount, 2).' BDT · Receipt '.$payment->receipt_number;
        if ($discountBdt > 0) {
            $body .= ' · Discount '.number_format($discountBdt, 2);
        }
        if ($collectorId !== (int) auth()->id()) {
            $body .= ' · Credited to '.$collector->name;
        }

        Notification::make()
            ->title('Collected')
            ->body($body)
            ->success()
            ->send();

        $this->reset(['selectedCustomerId', 'selectedCustomer', 'amount', 'search', 'invoiceId', 'notes']);
        $this->resetCollectionDiscountFields();
        $this->results = collect();
        $this->panelTab = 'activity';
    }

    /**
     * @return array<string, string>
     */
    public function getMethodOptions(): array
    {
        return [
            PaymentGateway::CASH => 'Cash',
            PaymentGateway::BKASH => 'bKash',
            PaymentGateway::NAGAD => 'Nagad',
            PaymentGateway::ROCKET => 'Rocket',
        ];
    }
}
