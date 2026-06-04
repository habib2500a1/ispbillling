<?php

namespace App\Services\Billing;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PaymentResource;
use App\Models\Customer;
use App\Support\BillingDefaults;
use App\Support\CustomerBalanceDue;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Network\CustomerConnectionStatusService;
use App\Support\CustomerSearchPresenter;
use App\Support\PaymentCollectionSource;
use App\Support\PaymentType;
use Illuminate\Support\Collection;

final class BillCollectionSearchService
{
    public function __construct(
        private readonly CustomerConnectionStatusService $connectionStatus,
        private readonly CustomerSearchPresenter $searchPresenter,
        private readonly SubscriberBillingStatementService $statements,
    ) {}
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 25): Collection
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return collect();
        }

        $digits = preg_replace('/\D+/', '', $query) ?? '';
        $like = '%'.$query.'%';
        $numericId = ctype_digit($query) ? (int) $query : 0;

        $customers = Customer::query()
            ->with(['area', 'zone', 'subzone', 'package'])
            ->where(function ($w) use ($query, $like, $digits, $numericId): void {
                if ($numericId > 0) {
                    $w->where('id', $numericId);
                }

                $w->orWhere('customer_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhere('mikrotik_secret_name', 'like', $like)
                    ->orWhere('radius_username', 'like', $like)
                    ->orWhere('nid_number', 'like', $like);

                if ($digits !== '') {
                    $w->orWhere('phone', 'like', '%'.$digits.'%')
                        ->orWhere('customer_code', 'like', '%'.$digits.'%');
                } else {
                    $w->orWhere('phone', 'like', $like);
                }

                $w->orWhereHas('invoices', function ($iq) use ($like): void {
                    $iq->where('invoice_number', 'like', $like);
                })
                    ->orWhereHas('area', fn ($aq) => $aq->where('name', 'like', $like))
                    ->orWhereHas('zone', fn ($zq) => $zq->where('name', 'like', $like))
                    ->orWhereHas('subzone', fn ($sq) => $sq->where('name', 'like', $like));
            })
            ->limit($limit * 2)
            ->get()
            ->sortBy(function (Customer $customer) use ($query, $numericId): int {
                if ($numericId > 0 && (int) $customer->id === $numericId) {
                    return 0;
                }
                if (strcasecmp((string) $customer->customer_code, $query) === 0) {
                    return 1;
                }
                if (str_starts_with(strtolower((string) $customer->customer_code), strtolower($query))) {
                    return 2;
                }
                if (str_contains(strtolower((string) $customer->name), strtolower($query))) {
                    return 3;
                }

                return 4;
            })
            ->take($limit);

        if ($customers->isEmpty()) {
            return collect();
        }

        $customersWithDue = CustomerBalanceDue::augmentTableQuery(
            Customer::query()
                ->with(['area', 'zone', 'subzone', 'package'])
                ->whereIn('id', $customers->pluck('id')),
        )->get()->keyBy('id');

        $rows = $customers
            ->map(fn (Customer $customer): Customer => $customersWithDue->get($customer->id) ?? $customer)
            ->map(fn (Customer $customer): array => $this->present($customer));

        return $this->searchPresenter->annotateDuplicateNames($rows)->values();
    }

    public function find(int $customerId): ?array
    {
        $customer = Customer::query()
            ->withoutGlobalScopes()
            ->with(['area', 'zone', 'subzone', 'package'])
            ->find($customerId);

        if ($customer === null) {
            return null;
        }

        $customer->refresh();

        return $this->present($customer, detailed: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Customer $customer, bool $detailed = false): array
    {
        $openInvoices = Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
            ->orderBy('due_date')
            ->get()
            ->filter(fn (Invoice $inv): bool => $inv->balanceDue() > 0.009)
            ->values();

        $balanceDue = CustomerBalanceDue::displayAmount($customer);
        $due = CustomerBalanceDue::resolve($customer);
        $due['balance_due'] = $balanceDue;

        $row = [
            'id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'username' => $customer->pppLoginName(),
            'address' => $customer->address ?? $customer->formattedAddress(),
            'area_id' => $customer->area_id,
            'zone_id' => $customer->zone_id,
            'area' => $customer->area?->name,
            'zone' => $customer->zone?->name,
            'billing_mode' => $customer->billing_mode,
            'expire_day' => BillingDefaults::expireDayFromDate($customer->service_expires_at?->toDateString()),
            'service_expires_at' => $customer->service_expires_at?->toDateString(),
            'notes' => $customer->notes,
            'mikrotik_secret_name' => $customer->mikrotik_secret_name,
            'status' => $customer->status,
            'package' => $customer->package?->name,
            'package_id' => $customer->package_id,
            'monthly_bill' => $customer->package?->price_monthly !== null
                ? round((float) $customer->package->price_monthly, 2)
                : null,
            'package_speed' => $customer->package?->download_mbps,
            'balance_due' => $balanceDue,
            'billing_payment_state' => $due['payment_state'],
            'open_invoices' => $openInvoices->count(),
            'account_balance' => (float) $customer->account_balance,
            'is_online' => $customer->isPppOnline(),
            'connection' => $this->connectionStatus->summary($customer),
        ];

        if ($detailed) {
            $row['invoices'] = $openInvoices->map(fn (Invoice $inv): array => $this->invoiceRow($inv))->values()->all();

            $row['bill_history'] = $this->statements->invoiceHistoryRows($customer);

            $row['collection_history'] = $this->statements->paymentHistoryRows($customer, 'all');

            $row['collection_history_legacy_portal'] = $this->statements->paymentHistoryRows($customer, 'legacy_portal');

            $payments = Payment::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('paid_at')
                ->limit($this->statements->paymentHistoryLimit())
                ->get();
            $legacyPortalOnly = PaymentCollectionSource::filterLegacyPortalParity($payments);
            $localOnly = PaymentCollectionSource::filterLocalOnly($payments);

            $row['collection_sync'] = [
                'legacy_portal_count' => count($legacyPortalOnly),
                'local_only_count' => count($localOnly),
                'show_legacy_portal_hint' => \App\Support\LegacyPortalSource::isImportedSource($customer->import_source)
                    && count($localOnly) > 0,
            ];
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRow(Invoice $inv): array
    {
        return $this->statements->invoiceRow($inv);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentRow(Payment $payment): array
    {
        $row = $this->statements->paymentRow($payment);

        return array_merge($row, [
            'recorded_by_id' => $payment->recorded_by,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'edit_url' => PaymentResource::getUrl('edit', ['record' => $payment]),
            'can_correct' => $payment->status === 'completed'
                && in_array($payment->payment_type ?? PaymentType::PAYMENT, [PaymentType::PAYMENT, PaymentType::WALLET_APPLY], true),
            'can_void' => app(PaymentVoidService::class)->canVoid($payment),
            'is_void' => $payment->status === 'void',
        ]);
    }
}
