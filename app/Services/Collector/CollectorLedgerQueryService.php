<?php

namespace App\Services\Collector;

use App\Models\CollectorCollection;
use App\Models\CollectorDailyClosing;
use App\Models\CollectorExpense;
use App\Models\CollectorExpenseCategory;
use App\Models\CollectorSettlement;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

final class CollectorLedgerQueryService
{
    /**
     * @return Collection<int, CollectorCollection>
     */
    public function openCollectionsForCollector(int $collectorId, int $limit = 50): Collection
    {
        return CollectorCollection::query()
            ->where('collector_id', $collectorId)
            ->whereIn('status', ['open', 'partial'])
            ->with(['customer:id,name,customer_code', 'payment:id,receipt_number'])
            ->orderBy('collected_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CollectorSettlement>
     */
    public function settlementsForCollector(int $collectorId, int $limit = 30): Collection
    {
        return CollectorSettlement::query()
            ->where('collector_id', $collectorId)
            ->with(['approver:id,name'])
            ->orderByDesc('submitted_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CollectorSettlement>
     */
    public function pendingSettlements(?int $tenantId = null, int $limit = 50): Collection
    {
        $tenantId ??= TenantResolver::requiredTenantId();

        return CollectorSettlement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->with(['collector:id,name,branch_id', 'collector.branch:id,name', 'submitter:id,name'])
            ->orderBy('submitted_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CollectorCollection>
     */
    public function recentCollectionsForCollector(int $collectorId, int $limit = 25): Collection
    {
        return CollectorCollection::query()
            ->where('collector_id', $collectorId)
            ->with(['customer:id,name,customer_code', 'payment:id,receipt_number'])
            ->orderByDesc('collected_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CollectorExpense>
     */
    public function expensesForCollector(int $collectorId, int $limit = 30): Collection
    {
        return CollectorExpense::query()
            ->where('collector_id', $collectorId)
            ->with(['category:id,name', 'approver:id,name'])
            ->orderByDesc('expense_date')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CollectorExpense>
     */
    public function pendingExpenses(?int $tenantId = null, int $limit = 50): Collection
    {
        $tenantId ??= TenantResolver::requiredTenantId();

        return CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->with(['collector:id,name', 'category:id,name', 'submitter:id,name'])
            ->orderBy('expense_date')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CollectorExpenseCategory>
     */
    public function expenseCategories(): Collection
    {
        $tenantId = TenantResolver::requiredTenantId();
        app(CollectorWalletService::class)->ensureDefaultCategories($tenantId);

        return CollectorExpenseCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, CollectorDailyClosing>
     */
    public function dailyClosingsForCollector(int $collectorId, int $limit = 14): Collection
    {
        return CollectorDailyClosing::query()
            ->where('collector_id', $collectorId)
            ->orderByDesc('closing_date')
            ->limit($limit)
            ->get();
    }
}
