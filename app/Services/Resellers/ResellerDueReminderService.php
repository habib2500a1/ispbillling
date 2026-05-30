<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerPortalNotification;
use App\Support\CustomerStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ResellerDueReminderService
{
    /**
     * @return array{sent: int, skipped: int, resellers: int}
     */
    public function run(bool $dryRun = false, ?int $tenantId = null): array
    {
        if (! config('automation.reseller_due_reminders.enabled', true)) {
            return ['sent' => 0, 'skipped' => 0, 'resellers' => 0];
        }

        $dueByReseller = $this->dueTotalsByReseller($tenantId);
        $expiringByReseller = config('automation.reseller_due_reminders.include_expiring', true)
            ? $this->expiringTotalsByReseller($tenantId)
            : collect();

        $resellerIds = $dueByReseller->keys()
            ->merge($expiringByReseller->keys())
            ->unique()
            ->values();

        if ($resellerIds->isEmpty()) {
            return ['sent' => 0, 'skipped' => 0, 'resellers' => 0];
        }

        $minCustomers = max(0, (int) config('automation.reseller_due_reminders.min_due_customers', 1));
        $minAmount = max(0.0, (float) config('automation.reseller_due_reminders.min_due_amount', 0));
        $dedupe = (bool) config('automation.reseller_due_reminders.dedupe_same_day', true);

        $sent = 0;
        $skipped = 0;

        Reseller::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $resellerIds)
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(50, function (Collection $resellers) use (
                $dueByReseller,
                $expiringByReseller,
                $minCustomers,
                $minAmount,
                $dedupe,
                $dryRun,
                &$sent,
                &$skipped,
            ): void {
                foreach ($resellers as $reseller) {
                    $due = $dueByReseller->get($reseller->id, ['customers' => 0, 'amount' => 0.0]);
                    $expiring = (int) ($expiringByReseller->get($reseller->id) ?? 0);

                    $dueCustomers = (int) $due['customers'];
                    $dueAmount = (float) $due['amount'];

                    $hasDueAlert = $dueCustomers >= $minCustomers && $dueAmount >= $minAmount;
                    $hasExpiringAlert = $expiring > 0;

                    if (! $hasDueAlert && ! $hasExpiringAlert) {
                        $skipped++;

                        continue;
                    }

                    if ($dedupe && $this->alreadyNotifiedToday($reseller)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $sent++;

                        continue;
                    }

                    app(ResellerPortalNotifier::class)->dueReminder(
                        $reseller,
                        $dueCustomers,
                        $dueAmount,
                        $expiring,
                    );

                    $sent++;
                }
            });

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'resellers' => $resellerIds->count(),
        ];
    }

    /**
     * @return Collection<int, array{customers: int, amount: float}>
     */
    private function dueTotalsByReseller(?int $tenantId): Collection
    {
        $rows = DB::table('customers as c')
            ->join('invoices as i', 'i.customer_id', '=', 'c.id')
            ->whereNotNull('c.reseller_id')
            ->whereIn('i.status', ['open', 'partial'])
            ->when($tenantId !== null, fn ($q) => $q->where('c.tenant_id', $tenantId))
            ->groupBy('c.reseller_id')
            ->selectRaw('c.reseller_id as reseller_id')
            ->selectRaw('COUNT(DISTINCT c.id) as due_customers')
            ->selectRaw('SUM(GREATEST(0, i.total - i.amount_paid)) as due_amount')
            ->get();

        return $rows->mapWithKeys(fn ($row): array => [
            (int) $row->reseller_id => [
                'customers' => (int) $row->due_customers,
                'amount' => round((float) $row->due_amount, 2),
            ],
        ]);
    }

    /**
     * @return Collection<int, int>
     */
    private function expiringTotalsByReseller(?int $tenantId): Collection
    {
        $days = max(1, (int) config('automation.reseller_due_reminders.expiring_within_days', 3));
        $until = now()->addDays($days)->endOfDay();

        return DB::table('customers')
            ->whereNotNull('reseller_id')
            ->where('status', CustomerStatus::ACTIVE)
            ->whereNotNull('service_expires_at')
            ->whereBetween('service_expires_at', [now()->startOfDay(), $until])
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('reseller_id')
            ->selectRaw('reseller_id, COUNT(*) as expiring_count')
            ->pluck('expiring_count', 'reseller_id')
            ->map(fn ($count): int => (int) $count);
    }

    private function alreadyNotifiedToday(Reseller $reseller): bool
    {
        return ResellerPortalNotification::query()
            ->where('reseller_id', $reseller->id)
            ->where('type', 'due_reminder')
            ->whereDate('created_at', today())
            ->exists();
    }
}
