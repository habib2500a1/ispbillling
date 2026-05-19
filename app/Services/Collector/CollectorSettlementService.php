<?php

namespace App\Services\Collector;

use App\Models\CollectorCollection;
use App\Models\CollectorSettlement;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\CashbookService;
use App\Services\Accounting\LedgerService;
use App\Support\PaymentType;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CollectorSettlementService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly CashbookService $cashbook,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('collector.enabled', true);
    }

    public function qualifiesForCollectorTracking(Payment $payment): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if ($payment->status !== 'completed') {
            return false;
        }

        if (($payment->payment_type ?? PaymentType::PAYMENT) !== PaymentType::PAYMENT) {
            return false;
        }

        if ($payment->recorded_by === null) {
            return false;
        }

        return in_array(
            (string) $payment->method,
            config('collector.cash_methods', ['cash', 'counter']),
            true
        );
    }

    public function recordCollectionFromPayment(Payment $payment): ?CollectorCollection
    {
        if (! $this->qualifiesForCollectorTracking($payment)) {
            return null;
        }

        $existing = CollectorCollection::query()->where('payment_id', $payment->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $collector = User::query()->find($payment->recorded_by);
        if ($collector === null) {
            return null;
        }

        $collection = CollectorCollection::query()->create([
            'tenant_id' => $payment->tenant_id ?? TenantResolver::requiredTenantId(),
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'collector_id' => $collector->id,
            'branch_id' => $collector->branch_id,
            'amount' => round((float) $payment->amount, 2),
            'amount_settled' => 0,
            'payment_method' => (string) $payment->method,
            'status' => 'open',
            'collected_at' => $payment->paid_at ?? $payment->created_at,
            'notes' => $payment->notes,
        ]);

        return $collection;
    }

    /**
     * @return array{total_collected: float, total_settled: float, outstanding: float, today_collected: float, month_collected: float, pending_settlement: float}
     */
    public function balanceForCollector(int $collectorId, ?int $tenantId = null): array
    {
        $tenantId ??= TenantResolver::requiredTenantId();

        $collections = CollectorCollection::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId);

        $totalCollected = (float) (clone $collections)->sum('amount');
        $totalSettledOnCollections = (float) (clone $collections)->sum('amount_settled');

        $todayCollected = (float) (clone $collections)
            ->whereDate('collected_at', today())
            ->sum('amount');

        $monthCollected = (float) (clone $collections)
            ->where('collected_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $pendingSettlement = (float) CollectorSettlement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->where('status', 'pending')
            ->sum('amount');

        $outstanding = max(0.0, round($totalCollected - $totalSettledOnCollections, 2));

        return [
            'total_collected' => round($totalCollected, 2),
            'total_settled' => round($totalSettledOnCollections, 2),
            'outstanding' => $outstanding,
            'today_collected' => round($todayCollected, 2),
            'month_collected' => round($monthCollected, 2),
            'pending_settlement' => round($pendingSettlement, 2),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leaderboard(?int $tenantId = null, int $limit = 20): array
    {
        $tenantId ??= TenantResolver::requiredTenantId();

        return CollectorCollection::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->selectRaw('collector_id, SUM(amount) as total_collected, SUM(amount_settled) as total_settled')
            ->groupBy('collector_id')
            ->orderByDesc('total_collected')
            ->limit($limit)
            ->get()
            ->map(function ($row): array {
                $user = User::query()->with('branch')->find($row->collector_id);
                $wallet = app(CollectorWalletService::class)->wallet((int) $row->collector_id);

                return [
                    'collector_id' => (int) $row->collector_id,
                    'name' => $user?->name ?? 'User #'.$row->collector_id,
                    'branch' => $user?->branch?->name,
                    'total_collected' => round((float) $row->total_collected, 2),
                    'total_settled' => round((float) $row->total_settled, 2),
                    'outstanding' => $wallet['outstanding'],
                    'approved_expenses' => $wallet['approved_expenses'] ?? 0,
                ];
            })
            ->all();
    }

    public function submitSettlement(
        int $collectorId,
        float $amount,
        string $paymentMethod = 'cash',
        ?string $reference = null,
        ?string $notes = null,
        ?int $submittedBy = null,
    ): CollectorSettlement {
        $amount = round($amount, 2);
        if ($amount < 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Settlement amount must be at least 0.01 BDT.',
            ]);
        }

        $submittedBy ??= auth()->id();
        $wallet = app(CollectorWalletService::class)->wallet($collectorId);
        if ($amount > $wallet['outstanding'] + 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Amount exceeds your cash in hand ('.number_format($wallet['outstanding'], 2).' BDT).',
            ]);
        }

        $collector = User::query()->findOrFail($collectorId);
        $tenantId = TenantResolver::requiredTenantId();

        $settlement = CollectorSettlement::query()->create([
            'tenant_id' => $tenantId,
            'settlement_number' => CollectorSettlement::generateNumber($tenantId),
            'collector_id' => $collectorId,
            'branch_id' => $collector->branch_id,
            'submitted_by' => $submittedBy,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'notes' => $notes,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        if (! config('collector.settlement_requires_approval', true)) {
            return $this->approveSettlement($settlement, $submittedBy);
        }

        return $settlement->fresh();
    }

    public function approveSettlement(CollectorSettlement $settlement, ?int $approvedBy = null): CollectorSettlement
    {
        if ($settlement->status !== 'pending') {
            throw ValidationException::withMessages([
                'settlement' => 'Only pending settlements can be approved.',
            ]);
        }

        $approvedBy ??= auth()->id();

        return DB::transaction(function () use ($settlement, $approvedBy): CollectorSettlement {
            $this->allocateSettlementToCollections($settlement);

            $collector = $settlement->collector;
            $journal = null;
            $cashbook = null;

            if (config('accounting.auto_post_customer_payments', true)) {
                $holdingCode = (string) config('collector.holding_account_code', '1050');
                $cashCode = (string) config('accounting.cash_account_code', '1000');
                $amount = (float) $settlement->amount;

                $journal = $this->ledger->post(
                    'Collector settlement '.$settlement->settlement_number,
                    [
                        ['account_code' => $cashCode, 'debit' => $amount, 'description' => $collector?->name],
                        ['account_code' => $holdingCode, 'credit' => $amount, 'description' => $collector?->name],
                    ],
                    $settlement->submitted_at,
                    'collector_settlement',
                    $settlement->id,
                    (int) $settlement->tenant_id,
                );

                $cashbook = $this->cashbook->record(
                    direction: 'in',
                    amount: $amount,
                    partyName: 'Settlement: '.($collector?->name ?? 'Collector'),
                    accountCode: $holdingCode,
                    paymentMethod: $settlement->payment_method,
                    reference: $settlement->settlement_number,
                    notes: $settlement->notes,
                    date: now(),
                );
            }

            $settlement->forceFill([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'journal_entry_id' => $journal?->id,
                'cashbook_entry_id' => $cashbook?->id,
            ])->save();

            return $settlement->fresh(['collector', 'approver']);
        });
    }

    public function rejectSettlement(CollectorSettlement $settlement, string $reason, ?int $rejectedBy = null): CollectorSettlement
    {
        if ($settlement->status !== 'pending') {
            throw ValidationException::withMessages([
                'settlement' => 'Only pending settlements can be rejected.',
            ]);
        }

        $settlement->forceFill([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'rejected_at' => now(),
            'approved_by' => $rejectedBy ?? auth()->id(),
        ])->save();

        return $settlement->fresh();
    }

    private function allocateSettlementToCollections(CollectorSettlement $settlement): void
    {
        $remaining = (float) $settlement->amount;

        $collections = CollectorCollection::withoutGlobalScopes()
            ->where('tenant_id', $settlement->tenant_id)
            ->where('collector_id', $settlement->collector_id)
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('collected_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($collections as $collection) {
            if ($remaining <= 0.009) {
                break;
            }

            $due = $collection->outstandingAmount();
            $apply = round(min($remaining, $due), 2);
            if ($apply <= 0) {
                continue;
            }

            $collection->amount_settled = round((float) $collection->amount_settled + $apply, 2);
            $collection->syncStatus();
            $collection->save();

            $remaining = round($remaining - $apply, 2);
        }

        if ($remaining > 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Could not allocate full settlement to open collections. Remaining: '.$remaining,
            ]);
        }
    }
}
