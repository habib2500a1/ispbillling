<?php

namespace App\Services\Saas;

use App\Models\CollectionSummary;
use App\Models\SaasOperator;
use App\Models\StaffCashEntry;
use App\Models\User;

final class StaffCashService
{
    /**
     * @return list<array{
     *   user: User,
     *   collected: float,
     *   deposited: float,
     *   adjusted: float,
     *   due: float,
     *   receipts: int
     * }>
     */
    public function ledger(?SaasOperator $operator = null, ?string $from = null, ?string $to = null): array
    {
        $from ??= now()->startOfMonth()->toDateString();
        $to ??= now()->toDateString();

        $staff = User::query()
            ->when($operator, fn ($q) => $q->where(function ($inner) use ($operator) {
                $inner->where('saas_operator_id', $operator->id)
                    ->orWhere('id', $operator->user_id);
            }))
            ->when(! $operator && ! SaasContext::isPlatformOwner(), function ($q) {
                $mine = SaasContext::operator();
                if ($mine) {
                    $q->where(function ($inner) use ($mine) {
                        $inner->where('saas_operator_id', $mine->id)
                            ->orWhere('id', $mine->user_id);
                    });
                }
            })
            ->orderBy('name')
            ->get();

        $emails = $staff->pluck('email')->filter()->all();
        [$fromAt, $toAt] = $this->collectionWindow($from, $to);

        $collections = CollectionSummary::query()
            ->whereBetween('collection_date', [$fromAt, $toAt])
            ->when($emails !== [], fn ($q) => $q->whereIn('collected_by', $emails))
            ->selectRaw('collected_by, SUM(collection_amount) as total, COUNT(*) as cnt')
            ->groupBy('collected_by')
            ->get()
            ->keyBy('collected_by');

        $cash = StaffCashEntry::query()
            ->whereBetween('entry_date', [$from, $to])
            ->when($operator, fn ($q) => $q->where('saas_operator_id', $operator->id))
            ->whereIn('user_id', $staff->pluck('id'))
            ->selectRaw('user_id, type, SUM(amount) as total')
            ->groupBy('user_id', 'type')
            ->get()
            ->groupBy('user_id');

        $rows = [];
        foreach ($staff as $user) {
            $col = $collections->get($user->email);
            $collected = (float) ($col->total ?? 0);
            $receipts = (int) ($col->cnt ?? 0);
            $types = $cash->get($user->id) ?? collect();
            $deposited = (float) ($types->firstWhere('type', 'deposit')->total ?? 0);
            $adjusted = (float) ($types->firstWhere('type', 'adjustment')->total ?? 0);
            $due = $collected - $deposited + $adjusted;

            $rows[] = [
                'user' => $user,
                'collected' => $collected,
                'deposited' => $deposited,
                'adjusted' => $adjusted,
                'due' => $due,
                'receipts' => $receipts,
            ];
        }

        usort($rows, fn ($a, $b) => $b['collected'] <=> $a['collected']);

        return $rows;
    }

    public function deposit(User $staff, int $amount, string $date, ?string $note = null, string $type = 'deposit'): StaffCashEntry
    {
        $operator = SaasContext::operator($staff) ?? SaasContext::operator();

        return StaffCashEntry::create([
            'user_id' => $staff->id,
            'saas_operator_id' => $operator?->id,
            'recorded_by' => auth()->id(),
            'type' => $type === 'adjustment' ? 'adjustment' : 'deposit',
            'amount' => $amount,
            'entry_date' => $date,
            'note' => $note,
        ]);
    }

    /**
     * @return list<array{id: int, customer: string, amount: float, collected_by: string, collected_at: string}>
     */
    public function receipts(?SaasOperator $operator = null, ?string $from = null, ?string $to = null, int $limit = 50): array
    {
        $from ??= now()->startOfMonth()->toDateString();
        $to ??= now()->toDateString();
        [$fromAt, $toAt] = $this->collectionWindow($from, $to);

        $emails = User::query()
            ->when($operator, fn ($q) => $q->where(function ($inner) use ($operator) {
                $inner->where('saas_operator_id', $operator->id)
                    ->orWhere('id', $operator->user_id);
            }))
            ->pluck('email')
            ->filter()
            ->all();

        return CollectionSummary::query()
            ->with('customer:id,customer_unique_id,customer_name')
            ->whereBetween('collection_date', [$fromAt, $toAt])
            ->when($emails !== [], fn ($q) => $q->whereIn('collected_by', $emails))
            ->orderByDesc('collection_date')
            ->limit($limit)
            ->get()
            ->map(fn (CollectionSummary $row) => [
                'id' => $row->id,
                'customer' => $row->customer?->customer_name ?: $row->customer_collection_unique_id,
                'uid' => $row->customer_collection_unique_id,
                'amount' => (float) $row->collection_amount,
                'collected_by' => $row->collected_by,
                'collected_at' => \Carbon\Carbon::parse($row->collection_date)->format('d M Y H:i'),
            ])
            ->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function collectionWindow(string $from, string $to): array
    {
        return [
            \Carbon\Carbon::parse($from)->startOfDay()->toDateTimeString(),
            \Carbon\Carbon::parse($to)->endOfDay()->toDateTimeString(),
        ];
    }
}
