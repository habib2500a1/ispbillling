<?php

namespace App\Services\Reports;

use App\Models\Payment;
use App\Support\PaymentType;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PaymentsReportService
{
    public const WALLET_ALL = 'all';

    public const WALLET_ONLY = 'wallet';

    public const WALLET_INVOICE = 'invoice';

    /**
     * @return array{total_amount: float, total_discount: float, total_rows: int, grouped_items: int}
     */
    public function summary(Carbon $from, Carbon $to, string $walletFilter = self::WALLET_ALL, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $query = $this->baseQuery($from, $to, $walletFilter, $tenantId);

        $totalAmount = (float) (clone $query)->sum('amount');
        $totalRows = (int) (clone $query)->count();

        $discountSum = (float) ((clone $query)
            ->toBase()
            ->selectRaw("COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.discount')) AS DECIMAL(12,2))), 0) as discount_total")
            ->value('discount_total') ?? 0);

        $groupedItems = (int) ((clone $query)
            ->toBase()
            ->selectRaw('COUNT(DISTINCT CONCAT(customer_id, "-", DATE(paid_at))) as grp')
            ->value('grp') ?? 0);

        return [
            'total_amount' => round($totalAmount, 2),
            'total_discount' => round($discountSum, 2),
            'total_rows' => $totalRows,
            'grouped_items' => $groupedItems,
        ];
    }

    public function tableQuery(Carbon $from, Carbon $to, string $walletFilter = self::WALLET_ALL, ?int $tenantId = null): Builder
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return $this->baseQuery($from, $to, $walletFilter, $tenantId)
            ->with(['customer', 'invoice', 'recorder']);
    }

    public static function walletFilterLabel(string $filter): string
    {
        return match ($filter) {
            self::WALLET_ONLY => 'Wallet only',
            self::WALLET_INVOICE => 'Invoice payments',
            default => 'All Wallets',
        };
    }

    public static function creditedToLabel(Payment $payment): string
    {
        $type = (string) ($payment->payment_type ?? PaymentType::PAYMENT);

        return match ($type) {
            PaymentType::WALLET_DEPOSIT => 'Customer wallet',
            PaymentType::WALLET_APPLY => 'Applied from wallet',
            default => $payment->invoice_id
                ? 'Invoice '.($payment->invoice?->invoice_number ?? '#'.$payment->invoice_id)
                : 'General ledger',
        };
    }

    public static function discountFor(Payment $payment): float
    {
        return round((float) (($payment->meta['discount'] ?? 0)), 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rowsForExport(Carbon $from, Carbon $to, string $walletFilter = self::WALLET_ALL, ?int $tenantId = null): array
    {
        return $this->tableQuery($from, $to, $walletFilter, $tenantId)
            ->orderByDesc('paid_at')
            ->limit(10000)
            ->get()
            ->map(fn (Payment $p): array => [
                'paid_at' => $p->paid_at?->format('Y-m-d H:i') ?? '',
                'client' => $p->customer?->name ?? '—',
                'customer_code' => $p->customer?->customer_code ?? '',
                'invoice' => $p->invoice?->invoice_number ?? '',
                'method' => $p->methodLabel(),
                'amount' => $p->displayAmount(),
                'discount' => self::discountFor($p),
                'credited_to' => self::creditedToLabel($p),
                'received_by' => $p->recorder?->name ?? '—',
                'remarks' => (string) ($p->notes ?? $p->reference ?? ''),
            ])
            ->all();
    }

    private function baseQuery(Carbon $from, Carbon $to, string $walletFilter, int $tenantId): Builder
    {
        $query = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to]);

        return match ($walletFilter) {
            self::WALLET_ONLY => $query->whereIn('payment_type', [
                PaymentType::WALLET_DEPOSIT,
                PaymentType::WALLET_APPLY,
            ]),
            self::WALLET_INVOICE => $query->where(function (Builder $q): void {
                $q->whereNull('payment_type')
                    ->orWhere('payment_type', PaymentType::PAYMENT);
            })->whereNotNull('invoice_id'),
            default => $query,
        };
    }
}
