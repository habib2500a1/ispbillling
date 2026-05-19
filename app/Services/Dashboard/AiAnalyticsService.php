<?php

namespace App\Services\Dashboard;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Services\Optical\OpticalDashboardService;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use App\Support\TenantResolver;
use Carbon\Carbon;

final class AiAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function insights(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $optical = app(OpticalDashboardService::class)->snapshot($tenantId);

        $churnRisk = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::ACTIVE)
            ->where(function ($q) {
                $q->whereDate('service_expires_at', '<', now()->addDays(7))
                    ->orWhereHas('invoices', fn ($inv) => $inv
                        ->whereIn('status', ['open', 'partial'])
                        ->whereRaw('(total - amount_paid) > 0'));
            })
            ->count();

        $paymentRisk = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'partial'])
            ->whereRaw('(total - amount_paid) > 0')
            ->where('due_date', '<', now())
            ->count();

        $lastMonth = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('payment_type', PaymentType::PAYMENT)
            ->whereBetween('paid_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->sum('amount');

        $thisMonth = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('payment_type', PaymentType::PAYMENT)
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $forecast = $thisMonth > 0
            ? round((float) $thisMonth * (now()->daysInMonth / max(1, now()->day)), 0)
            : round((float) $lastMonth, 0);

        $trend = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : 0;

        return [
            'churn_risk_customers' => $churnRisk,
            'payment_risk_invoices' => $paymentRisk,
            'fiber_risk_onus' => ($optical['critical_onus'] ?? 0) + ($optical['warning_onus'] ?? 0),
            'revenue_forecast_mtd' => $forecast,
            'revenue_trend_pct' => $trend,
            'network_anomalies' => ($optical['fiber_faults'] ?? 0) + ($optical['offline_onus'] ?? 0),
            'recommendations' => $this->recommendations($tenantId, $churnRisk, $paymentRisk, $optical),
        ];
    }

    /**
     * @param  array<string, mixed>  $optical
     * @return list<array{priority: string, text: string}>
     */
    private function recommendations(int $tenantId, int $churn, int $paymentRisk, array $optical): array
    {
        $items = [];

        if (($optical['critical_onus'] ?? 0) > 0) {
            $items[] = ['priority' => 'high', 'text' => ($optical['critical_onus']).' ONU with critical signal — schedule field visit.'];
        }
        if ($paymentRisk > 0) {
            $items[] = ['priority' => 'high', 'text' => $paymentRisk.' overdue invoice(s) — run collection campaign.'];
        }
        if ($churn > 0) {
            $items[] = ['priority' => 'medium', 'text' => $churn.' subscriber(s) at churn risk (expiry or balance due).'];
        }

        $breached = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_resolve_due_at')
            ->where('sla_resolve_due_at', '<', now())
            ->count();

        if ($breached > 0) {
            $items[] = ['priority' => 'high', 'text' => $breached.' ticket(s) past SLA — escalate to NOC/support lead.'];
        }

        if ($items === []) {
            $items[] = ['priority' => 'low', 'text' => 'Operations stable. Monitor bandwidth peaks during evening hours.'];
        }

        return $items;
    }
}
