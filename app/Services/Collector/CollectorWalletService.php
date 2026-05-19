<?php

namespace App\Services\Collector;

use App\Models\CollectorAdjustment;
use App\Models\CollectorCollection;
use App\Models\CollectorDailyClosing;
use App\Models\CollectorExpense;
use App\Models\CollectorExpenseCategory;
use App\Models\CollectorSettlement;
use App\Models\User;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CollectorWalletService
{
    public function __construct(
        private readonly CollectorSettlementService $settlements,
    ) {}

    /**
     * Cash in hand = collected − deposited − approved expenses − credit adjustments + debit adjustments.
     *
     * @return array<string, float|int>
     */
    public function wallet(int $collectorId, ?int $tenantId = null): array
    {
        $tenantId ??= TenantResolver::requiredTenantId();
        $base = $this->settlements->balanceForCollector($collectorId, $tenantId);

        $approvedExpenses = (float) CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->where('status', 'approved')
            ->sum('amount');

        $pendingExpenses = (float) CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->where('status', 'pending')
            ->sum('amount');

        $todayExpenses = (float) CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->where('status', 'approved')
            ->whereDate('expense_date', today())
            ->sum('amount');

        $todayDeposited = (float) CollectorSettlement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('submitted_at', today())
            ->sum('amount');

        $adjustments = $this->adjustmentNet($collectorId, $tenantId);

        $cashInHand = max(0.0, round(
            $base['total_collected']
            - $base['total_settled']
            - $approvedExpenses
            - $adjustments['credit']
            + $adjustments['debit'],
            2,
        ));

        $outstanding = $cashInHand;

        return array_merge($base, [
            'approved_expenses' => round($approvedExpenses, 2),
            'pending_expenses' => round($pendingExpenses, 2),
            'today_expenses' => round($todayExpenses, 2),
            'today_deposited' => round($todayDeposited, 2),
            'adjustment_credit' => $adjustments['credit'],
            'adjustment_debit' => $adjustments['debit'],
            'cash_in_hand' => $cashInHand,
            'outstanding' => $outstanding,
            'net_payable' => $outstanding,
        ]);
    }

    /**
     * @return array{credit: float, debit: float}
     */
    private function adjustmentNet(int $collectorId, int $tenantId): array
    {
        $rows = CollectorAdjustment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->get(['direction', 'amount']);

        $credit = 0.0;
        $debit = 0.0;
        foreach ($rows as $row) {
            if ($row->direction === 'credit') {
                $credit += (float) $row->amount;
            } else {
                $debit += (float) $row->amount;
            }
        }

        return [
            'credit' => round($credit, 2),
            'debit' => round($debit, 2),
        ];
    }

    public function ensureDefaultCategories(int $tenantId): void
    {
        $defaults = config('collector.expense_categories', []);
        foreach ($defaults as $code => $name) {
            CollectorExpenseCategory::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'is_active' => true, 'sort_order' => 0],
            );
        }
    }

    public function submitExpense(
        int $collectorId,
        float $amount,
        int $categoryId,
        ?string $description = null,
        ?string $expenseDate = null,
        ?string $proofPath = null,
        ?int $submittedBy = null,
    ): CollectorExpense {
        $amount = round($amount, 2);
        if ($amount < 0.01) {
            throw ValidationException::withMessages(['amount' => 'Expense must be at least 0.01 BDT.']);
        }

        $wallet = $this->wallet($collectorId);
        if ($amount > $wallet['outstanding'] + 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Expense exceeds cash in hand ('.number_format($wallet['outstanding'], 2).' BDT).',
            ]);
        }

        $collector = User::query()->findOrFail($collectorId);
        $tenantId = TenantResolver::requiredTenantId();
        $this->ensureDefaultCategories($tenantId);

        return CollectorExpense::query()->create([
            'tenant_id' => $tenantId,
            'collector_id' => $collectorId,
            'branch_id' => $collector->branch_id,
            'category_id' => $categoryId,
            'expense_number' => CollectorExpense::generateNumber($tenantId),
            'amount' => $amount,
            'status' => config('collector.expense_requires_approval', true) ? 'pending' : 'approved',
            'expense_date' => $expenseDate ?? now()->toDateString(),
            'description' => $description,
            'proof_path' => $proofPath,
            'submitted_by' => $submittedBy ?? auth()->id() ?? $collectorId,
            'approved_by' => config('collector.expense_requires_approval', true) ? null : ($submittedBy ?? auth()->id() ?? $collectorId),
            'approved_at' => config('collector.expense_requires_approval', true) ? null : now(),
        ]);
    }

    public function approveExpense(CollectorExpense $expense, ?int $approvedBy = null): CollectorExpense
    {
        if ($expense->status !== 'pending') {
            throw ValidationException::withMessages(['expense' => 'Only pending expenses can be approved.']);
        }

        $wallet = $this->wallet((int) $expense->collector_id);
        if ((float) $expense->amount > $wallet['outstanding'] + 0.009) {
            throw ValidationException::withMessages([
                'expense' => 'Collector no longer has enough cash in hand for this expense.',
            ]);
        }

        $expense->forceFill([
            'status' => 'approved',
            'approved_by' => $approvedBy ?? auth()->id(),
            'approved_at' => now(),
        ])->save();

        return $expense->fresh(['category', 'collector']);
    }

    public function rejectExpense(CollectorExpense $expense, string $reason, ?int $rejectedBy = null): CollectorExpense
    {
        if ($expense->status !== 'pending') {
            throw ValidationException::withMessages(['expense' => 'Only pending expenses can be rejected.']);
        }

        $expense->forceFill([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'rejected_at' => now(),
            'approved_by' => $rejectedBy ?? auth()->id(),
        ])->save();

        return $expense->fresh();
    }

    public function submitDailyClosing(
        int $collectorId,
        string $closingDate,
        float $declaredCashInHand,
        ?string $notes = null,
        ?int $submittedBy = null,
    ): CollectorDailyClosing {
        $tenantId = TenantResolver::requiredTenantId();
        $date = Carbon::parse($closingDate)->toDateString();

        if (CollectorDailyClosing::query()
            ->where('collector_id', $collectorId)
            ->whereDate('closing_date', $date)
            ->exists()) {
            throw ValidationException::withMessages([
                'closing_date' => 'Daily closing already submitted for this date.',
            ]);
        }

        $dayCollected = (float) CollectorCollection::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->whereDate('collected_at', $date)
            ->sum('amount');

        $dayDeposited = (float) CollectorSettlement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('submitted_at', $date)
            ->sum('amount');

        $dayExpenses = (float) CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->where('status', 'approved')
            ->whereDate('expense_date', $date)
            ->sum('amount');

        $computedDue = max(0.0, round($dayCollected - $dayDeposited - $dayExpenses, 2));
        $variance = round($declaredCashInHand - $computedDue, 2);

        $collector = User::query()->findOrFail($collectorId);

        return CollectorDailyClosing::query()->create([
            'tenant_id' => $tenantId,
            'collector_id' => $collectorId,
            'branch_id' => $collector->branch_id,
            'closing_date' => $date,
            'collected_total' => round($dayCollected, 2),
            'deposited_total' => round($dayDeposited, 2),
            'expense_total' => round($dayExpenses, 2),
            'declared_cash_in_hand' => round($declaredCashInHand, 2),
            'computed_due' => $computedDue,
            'cash_variance' => $variance,
            'status' => abs($variance) > (float) config('collector.cash_mismatch_threshold', 50) ? 'flagged' : 'submitted',
            'notes' => $notes,
            'submitted_by' => $submittedBy ?? auth()->id(),
            'submitted_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fraudAlerts(int $collectorId, ?int $tenantId = null): array
    {
        $tenantId ??= TenantResolver::requiredTenantId();
        $alerts = [];
        $wallet = $this->wallet($collectorId, $tenantId);

        $dueLimit = (float) config('collector.due_alert_threshold', 10000);
        if ($wallet['outstanding'] > $dueLimit) {
            $alerts[] = [
                'type' => 'due_limit',
                'severity' => 'danger',
                'message' => 'Outstanding due exceeds '.number_format($dueLimit, 0).' BDT',
            ];
        }

        if ($wallet['pending_expenses'] > 0 && $wallet['pending_settlement'] > 0) {
            $alerts[] = [
                'type' => 'pending_both',
                'severity' => 'warning',
                'message' => 'Pending expenses and settlements awaiting approval',
            ];
        }

        $duplicateExpenses = CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->whereDate('expense_date', today())
            ->select('amount', DB::raw('COUNT(*) as expense_count'))
            ->groupBy('amount')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        if ($duplicateExpenses->isNotEmpty()) {
            $alerts[] = [
                'type' => 'duplicate_expense',
                'severity' => 'warning',
                'message' => 'Duplicate expense amounts detected today',
            ];
        }

        $flaggedClosing = CollectorDailyClosing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->where('status', 'flagged')
            ->where('closing_date', '>=', now()->subDays(7)->toDateString())
            ->exists();
        if ($flaggedClosing) {
            $alerts[] = [
                'type' => 'cash_mismatch',
                'severity' => 'danger',
                'message' => 'Daily closing cash variance flagged in last 7 days',
            ];
        }

        $highExpense = (float) config('collector.high_expense_threshold', 5000);
        $suspicious = CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->where('status', 'pending')
            ->where('amount', '>=', $highExpense)
            ->whereNull('proof_path')
            ->exists();
        if ($suspicious) {
            $alerts[] = [
                'type' => 'missing_receipt',
                'severity' => 'warning',
                'message' => 'Large pending expense without receipt upload',
            ];
        }

        return $alerts;
    }

    /**
     * Unified ledger timeline for UI.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ledgerTimeline(int $collectorId, int $limit = 40): Collection
    {
        $tenantId = TenantResolver::requiredTenantId();
        $events = collect();

        foreach (CollectorCollection::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->with('customer:id,name,customer_code')
            ->orderByDesc('collected_at')
            ->limit($limit)
            ->get() as $c) {
            $events->push([
                'at' => $c->collected_at,
                'type' => 'collection',
                'label' => 'Collection · '.($c->customer?->customer_code ?? ''),
                'amount' => (float) $c->amount,
                'meta' => $c->payment_method,
            ]);
        }

        foreach (CollectorSettlement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->orderByDesc('submitted_at')
            ->limit($limit)
            ->get() as $s) {
            $events->push([
                'at' => $s->submitted_at,
                'type' => 'settlement',
                'label' => 'Settlement '.$s->settlement_number,
                'amount' => -1 * (float) $s->amount,
                'meta' => $s->status,
            ]);
        }

        foreach (CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->where('status', 'approved')
            ->with('category:id,name')
            ->orderByDesc('expense_date')
            ->limit($limit)
            ->get() as $e) {
            $events->push([
                'at' => $e->expense_date->startOfDay(),
                'type' => 'expense',
                'label' => 'Expense · '.($e->category?->name ?? 'Misc'),
                'amount' => -1 * (float) $e->amount,
                'meta' => $e->expense_number,
            ]);
        }

        return $events->sortByDesc('at')->take($limit)->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function adminDashboard(?int $tenantId = null): array
    {
        $tenantId ??= TenantResolver::requiredTenantId();
        $leaderboard = $this->settlements->leaderboard($tenantId);
        $totalDue = round(array_sum(array_column($leaderboard, 'outstanding')), 2);

        $pendingSettlements = CollectorSettlement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        $pendingExpenses = CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        $expenseBreakdown = CollectorExpense::withoutGlobalScopes()
            ->where('collector_expenses.tenant_id', $tenantId)
            ->where('collector_expenses.status', 'approved')
            ->where('collector_expenses.expense_date', '>=', now()->startOfMonth())
            ->join('collector_expense_categories', 'collector_expenses.category_id', '=', 'collector_expense_categories.id')
            ->selectRaw('collector_expense_categories.name as category, SUM(collector_expenses.amount) as total')
            ->groupBy('collector_expense_categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'total' => round((float) $row->total, 2)])
            ->all();

        return [
            'total_due' => $totalDue,
            'pending_settlements' => $pendingSettlements,
            'pending_expenses' => $pendingExpenses,
            'expense_breakdown' => $expenseBreakdown,
            'leaderboard' => $leaderboard,
        ];
    }
}
