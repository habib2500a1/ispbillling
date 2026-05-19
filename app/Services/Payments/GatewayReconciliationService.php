<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PendingGatewayPayment;
use App\Support\PaymentGateway;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class GatewayReconciliationService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?Carbon $from = null, ?Carbon $to = null, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $from = ($from ?? now()->subDays(7))->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $payments = Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->get();

        $byGateway = [];
        foreach ($payments as $payment) {
            $gw = (string) ($payment->method ?? PaymentGateway::OTHER);
            $byGateway[$gw] ??= ['count' => 0, 'total' => 0.0, 'with_trx' => 0];
            $byGateway[$gw]['count']++;
            $byGateway[$gw]['total'] += (float) $payment->amount;
            if (filled($payment->gateway_transaction_id)) {
                $byGateway[$gw]['with_trx']++;
            }
        }

        $pending = PendingGatewayPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->limit(50)
            ->with('customer:id,name,customer_code')
            ->get();

        $duplicateTrx = Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('gateway_transaction_id')
            ->where('gateway_transaction_id', '!=', '')
            ->select('gateway_transaction_id', 'method', DB::raw('COUNT(*) as c'))
            ->groupBy('gateway_transaction_id', 'method')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $stalePending = PendingGatewayPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'payment_count' => $payments->count(),
            'payment_total' => round((float) $payments->sum('amount'), 2),
            'by_gateway' => $byGateway,
            'pending' => $pending,
            'pending_count' => $pending->count(),
            'stale_pending_count' => $stalePending,
            'duplicate_transactions' => $duplicateTrx,
        ];
    }
}
