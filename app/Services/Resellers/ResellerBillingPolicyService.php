<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Reseller;
use App\Support\ResellerCustomerBillingPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ResellerBillingPolicyService
{
    public function isCustomerExemptFromAutoSuspend(Customer $customer): bool
    {
        if ($customer->reseller_id === null) {
            return false;
        }

        return app(ResellerCustomerBillingEngine::class)->isExemptFromAutoSuspend($customer);
    }

    /**
     * @return array{status: string, admin_due: float, credit_limit: float, grace_days: int, overdue_days: int, risk_score: float, should_warn: bool, should_suspend_reseller: bool}
     */
    public function evaluate(Reseller $reseller): array
    {
        $summary = app(ResellerDueLedgerService::class)->summary($reseller);
        $limit = $summary['credit_limit'];
        $due = $summary['admin_due'];
        $grace = max(0, (int) ($reseller->due_grace_period_days ?? config('reseller_billing.default_due_grace_days', 15)));

        $oldestUnpaid = $this->oldestUnpaidInvoiceAgeDays($reseller);
        $overdueDays = max(0, $oldestUnpaid - $grace);

        $risk = 0.0;
        if ($limit > 0) {
            $risk += min(50, ($due / $limit) * 50);
        }
        $risk += min(30, $overdueDays);
        $risk += min(20, (float) $reseller->customers()->whereHas('invoices', fn ($q) => $q->whereIn('status', ['open', 'partial']))->count());

        $shouldWarn = $summary['status'] === 'warning' || $summary['status'] === 'breach';
        $shouldSuspend = $limit > 0 && $due > $limit && $overdueDays > 0
            && ($reseller->reseller_suspend_policy ?? 'credit_breach') !== 'none';

        $reseller->forceFill([
            'risk_score' => round($risk, 2),
            'billing_policy_evaluated_at' => now(),
        ])->saveQuietly();

        return [
            'status' => $summary['status'],
            'admin_due' => $due,
            'credit_limit' => $limit,
            'grace_days' => $grace,
            'overdue_days' => $overdueDays,
            'risk_score' => round($risk, 2),
            'should_warn' => $shouldWarn,
            'should_suspend_reseller' => $shouldSuspend,
        ];
    }

    public function applyResellerBreachIfNeeded(Reseller $reseller): bool
    {
        $eval = $this->evaluate($reseller);
        if (! $eval['should_suspend_reseller'] || ! $reseller->is_active) {
            return false;
        }

        DB::transaction(function () use ($reseller): void {
            $reseller->forceFill(['is_active' => false])->save();

            if ($reseller->suspend_reseller_customers_on_breach) {
                app(ResellerSubscriberSyncService::class)->handleResellerActiveChange($reseller, true, false);
            }
        });

        app(ResellerPortalNotifier::class)->notify(
            $reseller,
            'billing_credit_breach',
            'Credit limit exceeded',
            sprintf(
                'Admin receivable due %s BDT exceeds credit limit %s BDT. Contact HQ to settle.',
                number_format($eval['admin_due'], 0),
                number_format($eval['credit_limit'], 0),
            ),
        );

        return true;
    }

    private function oldestUnpaidInvoiceAgeDays(Reseller $reseller): int
    {
        $customerIds = $reseller->customers()->pluck('id');
        if ($customerIds->isEmpty()) {
            return 0;
        }

        $oldest = Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', ['open', 'partial'])
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->value('due_date');

        if ($oldest === null) {
            return 0;
        }

        $due = Carbon::parse($oldest)->startOfDay();

        return $due->isFuture() ? 0 : (int) $due->diffInDays(now()->startOfDay());
    }

    /**
     * @return array{bucket_30: float, bucket_60: float, bucket_90: float, bucket_90_plus: float}
     */
    public function agingReport(Reseller $reseller): array
    {
        $buckets = ['bucket_30' => 0.0, 'bucket_60' => 0.0, 'bucket_90' => 0.0, 'bucket_90_plus' => 0.0];
        $customerIds = $reseller->customers()->pluck('id');
        if ($customerIds->isEmpty()) {
            return $buckets;
        }

        $invoices = Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', ['open', 'partial'])
            ->get();

        foreach ($invoices as $invoice) {
            $balance = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
            if ($balance <= 0) {
                continue;
            }
            $days = $invoice->due_date
                ? max(0, (int) Carbon::parse($invoice->due_date)->diffInDays(now(), false))
                : 0;

            if ($days <= 30) {
                $buckets['bucket_30'] += $balance;
            } elseif ($days <= 60) {
                $buckets['bucket_60'] += $balance;
            } elseif ($days <= 90) {
                $buckets['bucket_90'] += $balance;
            } else {
                $buckets['bucket_90_plus'] += $balance;
            }
        }

        foreach ($buckets as $k => $v) {
            $buckets[$k] = round($v, 2);
        }

        return $buckets;
    }
}
