<?php

namespace App\Services\Billing;

use App\Filament\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Network\CustomerConnectionStatusService;
use App\Support\BillingDefaults;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerSearchPresenter;
use App\Support\PaymentCollectionSource;
use App\Support\PaymentType;
use App\Services\Search\CustomerScoutSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BillCollectionSearchService
{
    public function __construct(
        private readonly CustomerConnectionStatusService $connectionStatus,
        private readonly CustomerSearchPresenter $searchPresenter,
        private readonly SubscriberBillingStatementService $statements,
        private readonly CustomerScoutSearchService $scoutSearch,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 25, string $filter = 'all'): Collection
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return collect();
        }

        $filter = in_array($filter, ['all', 'due', 'paid'], true) ? $filter : 'all';

        $customers = $this->resolveCustomers($query, $limit);
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
            ->map(fn (Customer $customer): array => $this->present($customer))
            ->filter(function (array $row) use ($filter): bool {
                $due = (float) ($row['balance_due'] ?? 0);

                return match ($filter) {
                    'due' => $due > 0.009,
                    'paid' => $due <= 0.009,
                    default => true,
                };
            })
            ->sortBy(function (array $row) use ($query): array {
                $dueRank = (float) ($row['balance_due'] ?? 0) > 0.009 ? 0 : 1;
                $numericId = ctype_digit($query) ? (int) $query : 0;

                return [
                    $dueRank,
                    $this->relevanceRank($row, $query, $numericId),
                    strtolower((string) ($row['name'] ?? '')),
                ];
            })
            ->take($limit)
            ->values();

        return $this->searchPresenter->annotateDuplicateNames($rows)->values();
    }

    /**
     * Scout (Meilisearch) first, SQL fallback when index unavailable.
     *
     * @return \Illuminate\Support\Collection<int, Customer>
     */
    private function resolveCustomers(string $query, int $limit): Collection
    {
        $scoutIds = $this->scoutSearch->searchIds($query, $limit * 3);

        if ($scoutIds !== null && $scoutIds !== []) {
            $order = array_flip($scoutIds);

            $customers = Customer::query()
                ->with(['area', 'zone', 'subzone', 'package'])
                ->whereIn('id', $scoutIds)
                ->get()
                ->sortBy(fn (Customer $c): int => $order[$c->id] ?? 9999)
                ->values();

            if ($customers->isNotEmpty()) {
                return $customers;
            }
        }

        if ($scoutIds === null || config('customer_search.sql_fallback', true)) {
            return $this->resolveCustomersViaSql($query, $limit);
        }

        return collect();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Customer>
     */
    private function resolveCustomersViaSql(string $query, int $limit): Collection
    {
        $words = $this->extractSearchWords($query);
        $digits = preg_replace('/\D+/', '', $query) ?? '';
        $numericId = ctype_digit($query) ? (int) $query : 0;
        $driver = Customer::query()->getConnection()->getDriverName();

        return Customer::query()
            ->with(['area', 'zone', 'subzone', 'package'])
            ->where(function (Builder $w) use ($query, $words, $digits, $numericId, $driver): void {
                if ($numericId > 0) {
                    $w->where('id', $numericId);
                }

                $w->orWhere(function (Builder $match) use ($query, $words, $digits, $driver): void {
                    if ($words !== []) {
                        foreach ($words as $word) {
                            $match->where(function (Builder $wordQuery) use ($word, $driver): void {
                                $this->applyWordSearch($wordQuery, $word, $driver);
                            });
                        }

                        return;
                    }

                    $this->applyWordSearch($match, $query, $driver);

                    if ($digits !== '') {
                        $digitLike = '%'.$digits.'%';
                        $op = $driver === 'pgsql' ? 'ilike' : 'like';
                        $match->orWhere('phone', $op, $digitLike)
                            ->orWhere('customer_code', $op, $digitLike);
                    }
                });
            })
            ->limit($limit * 3)
            ->get();
    }

    public function find(int $customerId, ?int $tenantId = null): ?array
    {
        $query = Customer::query()
            ->withoutGlobalScopes()
            ->with(['area', 'zone', 'subzone', 'package'])
            ->whereKey($customerId);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $customer = $query->first();

        if ($customer === null) {
            return null;
        }

        $customer->refresh();

        return $this->present($customer, detailed: true);
    }

    /**
     * @return list<string>
     */
    private function extractSearchWords(string $search): array
    {
        return array_values(array_filter(
            str_getcsv(preg_replace('/\s+/', ' ', $search), separator: ' ', escape: '\\'),
            fn (string $word): bool => mb_strlen(trim($word)) >= 2,
        ));
    }

    private function applyWordSearch(Builder $query, string $word, string $driver): void
    {
        $like = '%'.$word.'%';
        $op = $driver === 'pgsql' ? 'ilike' : 'like';

        $query->where(function (Builder $searchQuery) use ($like, $op, $driver): void {
            if ($driver === 'pgsql') {
                $searchQuery
                    ->where('customer_code', $op, $like)
                    ->orWhere('name', $op, $like)
                    ->orWhere('email', $op, $like)
                    ->orWhere('address', $op, $like)
                    ->orWhere('mikrotik_secret_name', $op, $like)
                    ->orWhere('radius_username', $op, $like)
                    ->orWhere('nid_number', $op, $like)
                    ->orWhere('phone', $op, $like)
                    ->orWhereHas('invoices', fn (Builder $iq): Builder => $iq->where('invoice_number', $op, $like))
                    ->orWhereHas('area', fn (Builder $aq): Builder => $aq->where('name', $op, $like))
                    ->orWhereHas('zone', fn (Builder $zq): Builder => $zq->where('name', $op, $like))
                    ->orWhereHas('subzone', fn (Builder $sq): Builder => $sq->where('name', $op, $like))
                    ->orWhereHas('package', fn (Builder $pq): Builder => $pq->where('name', $op, $like));

                return;
            }

            $searchQuery
                ->whereRaw('LOWER(customer_code) LIKE LOWER(?)', [$like])
                ->orWhereRaw('LOWER(name) LIKE LOWER(?)', [$like])
                ->orWhereRaw('LOWER(email) LIKE LOWER(?)', [$like])
                ->orWhereRaw('LOWER(address) LIKE LOWER(?)', [$like])
                ->orWhereRaw('LOWER(mikrotik_secret_name) LIKE LOWER(?)', [$like])
                ->orWhereRaw('LOWER(radius_username) LIKE LOWER(?)', [$like])
                ->orWhereRaw('LOWER(nid_number) LIKE LOWER(?)', [$like])
                ->orWhereRaw('LOWER(phone) LIKE LOWER(?)', [$like])
                ->orWhereHas('invoices', fn (Builder $iq): Builder => $iq->whereRaw('LOWER(invoice_number) LIKE LOWER(?)', [$like]))
                ->orWhereHas('area', fn (Builder $aq): Builder => $aq->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]))
                ->orWhereHas('zone', fn (Builder $zq): Builder => $zq->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]))
                ->orWhereHas('subzone', fn (Builder $sq): Builder => $sq->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]))
                ->orWhereHas('package', fn (Builder $pq): Builder => $pq->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]));
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function relevanceRank(array $row, string $query, int $numericId): int
    {
        if ($numericId > 0 && (int) ($row['id'] ?? 0) === $numericId) {
            return 0;
        }

        $code = (string) ($row['customer_code'] ?? '');
        $name = strtolower((string) ($row['name'] ?? ''));
        $needle = strtolower($query);

        if (strcasecmp($code, $query) === 0) {
            return 1;
        }

        if (str_starts_with(strtolower($code), $needle)) {
            return 2;
        }

        if (str_starts_with($name, $needle)) {
            return 3;
        }

        if (str_contains($name, $needle)) {
            return 4;
        }

        return 5;
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
            'monthly_bill' => app(CustomerPrepayService::class)->monthlyRate($customer),
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
