<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\TenantResolver;
use Carbon\Carbon;

final class BillingOpsMetricsService
{
    public function snapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $today = now()->toDateString();

        $openBase = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['paid', 'void', 'cancelled', 'draft']);

        $outstanding = (float) (clone $openBase)
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as due')
            ->value('due');

        $aging = [
            'current' => $this->agingBucket($tenantId, 0, 0),
            '1_30' => $this->agingBucket($tenantId, 1, 30),
            '31_60' => $this->agingBucket($tenantId, 31, 60),
            '60_plus' => $this->agingBucket($tenantId, 61, 9999),
        ];

        $dueTomorrow = (clone $openBase)
            ->whereDate('due_date', Carbon::parse($today)->addDay()->toDateString())
            ->whereRaw('(total - amount_paid) > 0')
            ->count();

        $overCredit = Customer::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('credit_limit')
            ->where('credit_limit', '>', 0)
            ->whereRaw('(
                SELECT COALESCE(SUM(invoices.total - invoices.amount_paid), 0)
                FROM invoices
                WHERE invoices.customer_id = customers.id
                  AND invoices.tenant_id = customers.tenant_id
                  AND invoices.status NOT IN (\'paid\', \'void\', \'cancelled\', \'draft\')
            ) > customers.credit_limit')
            ->count();

        $prepaidExpiring = Customer::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('billing_mode', ['prepaid', 'advance'])
            ->whereNotNull('service_expires_at')
            ->whereDate('service_expires_at', '>=', $today)
            ->whereDate('service_expires_at', '<=', Carbon::parse($today)->addDays(7)->toDateString())
            ->count();

        return [
            'outstanding' => round(max(0, $outstanding), 2),
            'aging' => $aging,
            'due_tomorrow' => $dueTomorrow,
            'over_credit_limit' => $overCredit,
            'prepaid_expiring_7d' => $prepaidExpiring,
        ];
    }

    private function agingBucket(int $tenantId, int $minDaysOverdue, int $maxDaysOverdue): array
    {
        $today = now()->startOfDay();

        $query = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['paid', 'void', 'cancelled', 'draft'])
            ->whereRaw('(total - amount_paid) > 0');

        if ($minDaysOverdue === 0 && $maxDaysOverdue === 0) {
            $query->whereDate('due_date', '>=', $today->toDateString());
        } else {
            $oldestDue = $today->copy()->subDays($maxDaysOverdue)->toDateString();
            $newestDue = $today->copy()->subDays($minDaysOverdue)->toDateString();
            $query->whereDate('due_date', '>=', $oldestDue)
                ->whereDate('due_date', '<=', $newestDue);
        }

        return [
            'count' => (clone $query)->count(),
            'amount' => round((float) (clone $query)->selectRaw('COALESCE(SUM(total - amount_paid), 0) as due')->value('due'), 2),
        ];
    }
}
