<?php

namespace App\Services\Billing;

use App\Models\CollectorVisit;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentGateway;
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
     *   rows: list<array<string, mixed>>
     * }
     */
    public function report(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $collectorId = null,
        ?string $search = null,
        ?int $tenantId = null,
        ?int $customerId = null,
    ): array {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $from = ($from ?? now())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $payments = Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->when($collectorId, fn ($q) => $q->where('recorded_by', $collectorId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->with([
                'customer:id,name,customer_code,phone,area_id,service_expires_at,status,network_access_state',
                'customer.area:id,name',
                'invoice:id,invoice_number',
                'recorder:id,name,email',
            ])
            ->orderByDesc('paid_at')
            ->get();

        $searchNorm = trim(mb_strtolower($search ?? ''));
        if ($searchNorm !== '') {
            $payments = $payments->filter(function (Payment $payment) use ($searchNorm): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $payment->receipt_number,
                    $payment->reference,
                    $payment->gateway_transaction_id,
                    $payment->customer?->name,
                    $payment->customer?->customer_code,
                    $payment->customer?->phone,
                    $payment->recorder?->name,
                    $payment->recorder?->email,
                    $payment->invoice?->invoice_number,
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

        foreach ($payments as $payment) {
            $method = (string) ($payment->method ?? PaymentGateway::OTHER);
            $amount = (float) $payment->amount;
            $visit = $visitByPayment->get($payment->id);

            $byMethod[$method] ??= ['total' => 0.0, 'count' => 0];
            $byMethod[$method]['total'] += $amount;
            $byMethod[$method]['count']++;

            $collectorIdRow = $payment->recorded_by ? (int) $payment->recorded_by : null;
            $collectorName = $payment->recorder?->name ?? 'Online / gateway';
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
            $rows[] = [
                'id' => $payment->id,
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                'date' => $payment->paid_at?->toDateString(),
                'time' => $payment->paid_at?->format('H:i'),
                'receipt_number' => $payment->receipt_number,
                'collector_id' => $collectorIdRow,
                'collector_name' => $collectorName,
                'collector_email' => $payment->recorder?->email,
                'customer_id' => $payment->customer_id,
                'customer_name' => $payment->customer?->name ?? '—',
                'customer_code' => $payment->customer?->customer_code ?? '—',
                'customer_phone' => $payment->customer?->phone ?? '—',
                'customer_area' => $payment->customer?->area?->name,
                'invoice_number' => $payment->invoice?->invoice_number,
                'amount' => $amount,
                'method' => $method,
                'method_label' => PaymentGateway::label($method),
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

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total' => round((float) $payments->sum('amount'), 2),
            'count' => $payments->count(),
            'cash_total' => round($cashTotal, 2),
            'online_total' => round($onlineTotal, 2),
            'with_gps' => $withGps,
            'by_method' => $byMethod,
            'by_collector' => array_values($byCollector),
            'rows' => $rows,
        ];
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
