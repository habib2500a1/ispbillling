<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerLedgerEntry;
use App\Models\ResellerMonthlyStatement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ResellerMonthlyStatementService
{
    public function syncMonth(Reseller $reseller, int $year, int $month): ResellerMonthlyStatement
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $opening = $this->openingDueBefore($reseller, $start);

        $entries = ResellerLedgerEntry::query()
            ->where('reseller_id', $reseller->id)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $accruals = round((float) $entries
            ->where('entry_type', ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_ACCRUAL)
            ->sum('amount'), 2);

        $collections = round((float) $entries
            ->where('entry_type', ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_COLLECTION)
            ->sum('amount'), 2);

        $settlements = round((float) $entries
            ->where('entry_type', ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_SETTLEMENT)
            ->sum('amount'), 2);

        $creditNotes = round((float) $entries
            ->where('entry_type', ResellerLedgerEntry::TYPE_CREDIT_NOTE)
            ->sum('amount'), 2);

        $debitNotes = round((float) $entries
            ->where('entry_type', ResellerLedgerEntry::TYPE_DEBIT_NOTE)
            ->sum('amount'), 2);

        $margin = round((float) $entries->sum('margin_amount'), 2);

        $closing = round($opening + $accruals + $debitNotes - $collections - $settlements - $creditNotes, 2);

        return ResellerMonthlyStatement::query()->updateOrCreate(
            [
                'reseller_id' => $reseller->id,
                'period_year' => $year,
                'period_month' => $month,
            ],
            [
                'tenant_id' => $reseller->tenant_id,
                'opening_admin_due' => $opening,
                'accruals' => $accruals,
                'collections_applied' => $collections,
                'settlements' => $settlements + $creditNotes,
                'closing_admin_due' => $closing,
                'margin_total' => $margin,
                'status' => ResellerMonthlyStatement::STATUS_OPEN,
            ],
        );
    }

    public function closeMonth(Reseller $reseller, int $year, int $month): ResellerMonthlyStatement
    {
        return DB::transaction(function () use ($reseller, $year, $month): ResellerMonthlyStatement {
            $statement = $this->syncMonth($reseller, $year, $month);
            $statement->forceFill([
                'status' => ResellerMonthlyStatement::STATUS_CLOSED,
                'closed_at' => now(),
                'closing_admin_due' => (float) $reseller->fresh()->admin_receivable_due,
            ])->save();

            return $statement->fresh();
        });
    }

    /**
     * Close prior month statements for all active resellers.
     *
     * @return array{resellers: int, closed: int}
     */
    public function closePreviousMonthForAll(?int $tenantId = null): array
    {
        $ref = now()->subMonth();
        $year = (int) $ref->year;
        $month = (int) $ref->month;

        $query = Reseller::query()->withoutGlobalScopes()->where('is_active', true);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $closed = 0;
        $query->orderBy('id')->chunkById(50, function ($resellers) use ($year, $month, &$closed): void {
            foreach ($resellers as $reseller) {
                $this->closeMonth($reseller, $year, $month);
                $closed++;
            }
        });

        return ['resellers' => $closed, 'closed' => $closed];
    }

    private function openingDueBefore(Reseller $reseller, Carbon $before): float
    {
        $prior = ResellerMonthlyStatement::query()
            ->where('reseller_id', $reseller->id)
            ->where(function ($q) use ($before): void {
                $q->where('period_year', '<', $before->year)
                    ->orWhere(function ($q2) use ($before): void {
                        $q2->where('period_year', $before->year)
                            ->where('period_month', '<', $before->month);
                    });
            })
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        if ($prior !== null) {
            return (float) $prior->closing_admin_due;
        }

        $sumDebits = (float) ResellerLedgerEntry::query()
            ->where('reseller_id', $reseller->id)
            ->where('created_at', '<', $before)
            ->where('direction', ResellerLedgerEntry::DIRECTION_DEBIT)
            ->sum('amount');

        $sumCredits = (float) ResellerLedgerEntry::query()
            ->where('reseller_id', $reseller->id)
            ->where('created_at', '<', $before)
            ->where('direction', ResellerLedgerEntry::DIRECTION_CREDIT)
            ->sum('amount');

        return round(max(0, $sumDebits - $sumCredits), 2);
    }
}
