<?php

namespace App\Services\Billing;

use App\Models\CollectorVisit;
use App\Models\Payment;
use App\Models\User;
use App\Services\Mobile\StaffBillingKpiResolver;
use App\Support\PaymentCollectionSource;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class CollectionDeskReportService
{
    /**
     * @return array{
     *   from: string,
     *   to: string,
     *   total: float,
     *   count: int,
     *   cash_total: float,
     *   online_total: float,
     *   with_gps: int,
     *   by_method: array<string, array{total: float, count: int}>,
     *   by_collector: list<array{collector_id: int|null, collector: string, total: float, count: int}>,
     *   rows: list<array<string, mixed>>,
     *   source_filter: string,
     *   desk_total: float,
     *   desk_count: int,
     *   legacy_portal_total: float,
     *   legacy_portal_count: int,
     *   isp_grid_collected_mtd: ?float,
     * }
     */
    public function report(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $collectorId = null,
        ?string $search = null,
        ?int $tenantId = null,
        ?int $customerId = null,
        ?string $sourceFilter = null,
        ?string $methodFilter = null,
    ): array {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $from = ($from ?? now())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();
        $sourceFilter = $sourceFilter ?? (string) config('legacy_portal.collection_report_default_source', 'legacy_portal');
        if (! in_array($sourceFilter, ['all', 'desk', 'legacy_portal'], true)) {
            $sourceFilter = 'legacy_portal';
        }

        $payments = Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->when($collectorId, fn ($q) => $q->where('recorded_by', $collectorId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->with([
                'customer:id,name,customer_code,phone,area_id,service_expires_at,status,network_access_state,radius_username,mikrotik_secret_name',
                'customer.area:id,name',
                'invoice:id,invoice_number,total,amount_paid',
                'recorder:id,name,email',
            ])
            ->orderByDesc('paid_at')
            ->get();

        $deskPayments = $payments->filter(
            fn (Payment $p): bool => ! PaymentCollectionSource::isLegacyPortalImport($p)
                && in_array($p->payment_type ?? PaymentType::PAYMENT, [PaymentType::PAYMENT, PaymentType::WALLET_APPLY], true),
        );
        $ispPayments = $payments->filter(
            fn (Payment $p): bool => PaymentCollectionSource::isLegacyPortalImport($p),
        );

        $payments = match ($sourceFilter) {
            'desk' => $deskPayments->values(),
            'legacy_portal' => $ispPayments->values(),
            default => $payments,
        };

        if ($methodFilter !== null && $methodFilter !== '' && $methodFilter !== 'all') {
            $payments = $payments->filter(function (Payment $payment) use ($methodFilter): bool {
                $method = (string) ($payment->method ?? '');

                if ($methodFilter === 'bkash') {
                    return in_array($method, [
                        PaymentGateway::BKASH,
                        PaymentGateway::BKASH_PERSONAL,
                        PaymentGateway::BKASH_MERCHANT,
                    ], true);
                }

                return $method === $methodFilter;
            })->values();
        }

        $searchNorm = trim(mb_strtolower($search ?? ''));
        if ($searchNorm !== '') {
            $payments = $payments->filter(function (Payment $payment) use ($searchNorm): bool {
                $meta = is_array($payment->meta) ? $payment->meta : [];

                $haystack = mb_strtolower(implode(' ', array_filter([
                    $payment->receipt_number,
                    $payment->reference,
                    $payment->gateway_transaction_id,
                    $payment->customer?->name,
                    $payment->customer?->customer_code,
                    $payment->customer?->phone,
                    $payment->customer?->radius_username,
                    $payment->customer?->mikrotik_secret_name,
                    $payment->recorder?->name,
                    $payment->recorder?->email,
                    $payment->invoice?->invoice_number,
                    $meta['received_by'] ?? null,
                    $meta['approved_by'] ?? null,
                    $meta['created_by'] ?? null,
                    PaymentGateway::label((string) ($payment->method ?? '')),
                    PaymentCollectionSource::label($payment),
                ])));

                return str_contains($haystack, $searchNorm);
            })->values();
        }

        $visitByPayment = CollectorVisit::query()
            ->whereIn('payment_id', $payments->pluck('id')->filter())
            ->get()
            ->keyBy('payment_id');

        $byMethod = [];
        $byCollector = [];
        $rows = [];
        $cashTotal = 0.0;
        $onlineTotal = 0.0;
        $withGps = 0;
        $sumBillTotal = 0.0;
        $sumReceived = 0.0;
        $sumDiscount = 0.0;
        $sumBalance = 0.0;

        foreach ($payments as $payment) {
            $method = (string) ($payment->method ?? PaymentGateway::OTHER);
            $amount = (float) $payment->amount;
            $visit = $visitByPayment->get($payment->id);
            $meta = is_array($payment->meta) ? $payment->meta : [];
            $discount = round((float) ($meta['discount'] ?? 0), 2);
            $invoice = $payment->invoice;
            $billTotal = round((float) ($invoice?->total ?? $amount), 2);
            $balanceDue = $invoice !== null
                ? round(max(0, (float) $invoice->total - (float) $invoice->amount_paid), 2)
                : round(max(0, $billTotal - $discount - $amount), 2);

            $byMethod[$method] ??= ['total' => 0.0, 'count' => 0];
            $byMethod[$method]['total'] += $amount;
            $byMethod[$method]['count']++;

            $collectorIdRow = $payment->recorded_by ? (int) $payment->recorded_by : null;
            $receivedBy = $this->metaStaffName($meta, 'received_by')
                ?? $payment->recorder?->name
                ?? 'Online / gateway';
            $collectorName = $payment->recorder?->name ?? $receivedBy;
            $approvedBy = $this->metaStaffName($meta, 'approved_by')
                ?? $this->metaStaffName($meta, 'approved_by_name')
                ?? ($payment->recorder?->name ?? $receivedBy);
            $createdBy = $this->metaStaffName($meta, 'created_by')
                ?? $this->metaStaffName($meta, 'created_by_name')
                ?? ($payment->recorder?->name ?? $receivedBy);

            $sumBillTotal += $billTotal;
            $sumReceived += $amount;
            $sumDiscount += $discount;
            $sumBalance += $balanceDue;
            $collectorKey = (string) ($collectorIdRow ?? 'online');
            $byCollector[$collectorKey] ??= [
                'collector_id' => $collectorIdRow,
                'collector' => $collectorName,
                'total' => 0.0,
                'count' => 0,
            ];
            $byCollector[$collectorKey]['total'] += $amount;
            $byCollector[$collectorKey]['count']++;

            if (in_array($method, [PaymentGateway::CASH, PaymentGateway::BANK, PaymentGateway::OTHER], true)) {
                $cashTotal += $amount;
            } else {
                $onlineTotal += $amount;
            }

            if ($visit && $visit->latitude !== null) {
                $withGps++;
            }

            $customer = $payment->customer;
            $username = $customer?->radius_username
                ?? $customer?->mikrotik_secret_name
                ?? $customer?->customer_code
                ?? '—';
            $billNumber = filled($meta['legacy_portal_bill_header_id'] ?? null)
                ? (string) $meta['legacy_portal_bill_header_id']
                : ($payment->invoice?->invoice_number ?? $payment->receipt_number ?? '—');

            $rows[] = [
                'id' => $payment->id,
                'source_label' => PaymentCollectionSource::label($payment),
                'collection_label' => PaymentCollectionSource::label($payment),
                'is_legacy_portal_import' => PaymentCollectionSource::isLegacyPortalImport($payment),
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                'date' => $payment->paid_at?->format('d M Y'),
                'time' => $payment->paid_at?->format('h:i A'),
                'receipt_number' => $payment->receipt_number,
                'bill_number' => $billNumber,
                'collector_id' => $collectorIdRow,
                'collector_name' => $collectorName,
                'received_by' => $receivedBy,
                'approved_by' => $approvedBy,
                'created_by' => $createdBy,
                'collector_email' => $payment->recorder?->email,
                'customer_id' => $payment->customer_id,
                'customer_name' => $payment->customer?->name ?? '—',
                'customer_code' => $payment->customer?->customer_code ?? '—',
                'username' => $username,
                'customer_phone' => $payment->customer?->phone ?? '—',
                'customer_area' => $payment->customer?->area?->name,
                'invoice_number' => $payment->invoice?->invoice_number,
                'bill_total' => $billTotal,
                'discount' => $discount,
                'balance_due' => $balanceDue,
                'amount' => $amount,
                'method' => $method,
                'method_label' => PaymentGateway::label($method),
                'is_bkash' => in_array($method, [
                    PaymentGateway::BKASH,
                    PaymentGateway::BKASH_PERSONAL,
                    PaymentGateway::BKASH_MERCHANT,
                ], true),
                'reference' => $payment->reference,
                'gateway_transaction_id' => $payment->gateway_transaction_id,
                'notes' => $payment->notes,
                'has_gps' => $visit && $visit->latitude !== null,
                'latitude' => $visit?->latitude,
                'longitude' => $visit?->longitude,
                'receipt_url' => route('payments.receipt', $payment),
                'service_valid_until' => $customer?->service_expires_at?->toDateString(),
                'service_off_date' => $customer?->serviceOffDate()?->toDateString(),
                'days_until_off' => $customer?->daysUntilServiceExpiry(),
                'customer_status' => $customer?->status,
                'network_state' => $customer?->network_access_state,
                'edit_url' => \App\Filament\Pages\BillCollectionDesk::getUrl([
                    'customer' => $payment->customer_id,
                ]).'&edit_payment='.$payment->id,
                'subscriber_edit_url' => $customer
                    ? \App\Filament\Resources\CustomerResource::getUrl('edit', ['record' => $customer->id])
                    : null,
            ];
        }

        uasort($byCollector, fn ($a, $b) => $b['total'] <=> $a['total']);

        $ispGridMtd = null;
        if ($from->lte(now()->startOfMonth()) && $to->gte(now()->startOfDay())) {
            try {
                $kpi = app(StaffBillingKpiResolver::class)->resolve($tenantId);
                if (($kpi['source'] ?? '') === 'legacy_portal') {
                    $ispGridMtd = (float) $kpi['collected_bill'];
                }
            } catch (\Throwable) {
                $ispGridMtd = null;
            }
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'source_filter' => $sourceFilter,
            'desk_total' => round((float) $deskPayments->sum('amount'), 2),
            'desk_count' => $deskPayments->count(),
            'legacy_portal_total' => round((float) $ispPayments->sum('amount'), 2),
            'legacy_portal_count' => $ispPayments->count(),
            'isp_grid_collected_mtd' => $ispGridMtd,
            'total' => round((float) $payments->sum('amount'), 2),
            'count' => $payments->count(),
            'cash_total' => round($cashTotal, 2),
            'online_total' => round($onlineTotal, 2),
            'with_gps' => $withGps,
            'by_method' => $byMethod,
            'by_collector' => array_values($byCollector),
            'rows' => $rows,
            'row_totals' => [
                'bill_total' => round($sumBillTotal, 2),
                'received' => round($sumReceived, 2),
                'discount' => round($sumDiscount, 2),
                'balance_due' => round($sumBalance, 2),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function metaStaffName(array $meta, string $key): ?string
    {
        $value = $meta[$key] ?? null;

        return filled($value) ? trim((string) $value) : null;
    }

    /**
     * @return array{
     *   date: string,
     *   total: float,
     *   count: int,
     *   by_method: array<string, array{total: float, count: int}>,
     *   by_collector: list<array{collector: string, total: float, count: int}>
     * }
     */
    public function todaySnapshot(?Carbon $date = null, ?int $tenantId = null): array
    {
        $date = $date ?? now();
        $full = $this->report($date->copy()->startOfDay(), $date->copy()->endOfDay(), null, null, $tenantId);

        return [
            'date' => $full['from'],
            'total' => $full['total'],
            'count' => $full['count'],
            'by_method' => $full['by_method'],
            'by_collector' => array_map(
                fn (array $row): array => [
                    'collector' => $row['collector'],
                    'total' => $row['total'],
                    'count' => $row['count'],
                ],
                $full['by_collector'],
            ),
            'rows' => $full['rows'],
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function collectorsForFilter(?int $tenantId = null): Collection
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $ids = Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('recorded_by')
            ->distinct()
            ->pluck('recorded_by');

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
