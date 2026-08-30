<?php

namespace App\Services\Saas;

use App\Models\SaasInvoice;
use App\Models\SaasOperator;
use Carbon\CarbonInterface;

final class SaasBillingService
{
    public function __construct(
        private readonly SaasQuotaService $quotas,
    ) {}

    public function quote(SaasOperator $operator): int
    {
        if ($operator->isLifetime()) {
            $operator->user_base_count = $this->quotas->count($operator, 'customers');
            $operator->amount = 0;
            $operator->save();

            return 0;
        }

        $users = $this->quotas->count($operator, 'customers');
        $operator->user_base_count = $users;
        $amount = (int) $operator->base_amount + ($users * (int) $operator->per_user_rate);
        $operator->amount = $amount;
        $operator->save();

        return $amount;
    }

    public function issueInvoice(SaasOperator $operator, ?CarbonInterface $dueAt = null): ?SaasInvoice
    {
        if ($operator->isLifetime()) {
            return null;
        }

        $amount = $this->quote($operator);
        $due = $dueAt ?: ($operator->next_due_at ?: now());
        $cycle = $operator->billing_cycle === 'yearly' ? 'year' : 'month';
        $start = $due->copy()->startOfDay();
        $end = ($cycle === 'year' ? $due->copy()->addYear() : $due->copy()->addMonth())->subDay();

        return $operator->invoices()->create([
            'period_label' => $start->format('M Y').($cycle === 'year' ? '–'.$end->format('M Y') : ''),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'user_base' => $operator->user_base_count,
            'amount' => $amount,
            'status' => 'due',
            'due_at' => $due,
        ]);
    }

    public function markPaid(SaasInvoice $invoice, ?string $note = null): void
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_note' => $note,
        ]);

        $operator = $invoice->operator;
        $from = $operator->next_due_at && $operator->next_due_at->isFuture()
            ? $operator->next_due_at
            : now();
        $nextDue = $operator->billing_cycle === 'yearly'
            ? $from->copy()->addYear()
            : $from->copy()->addMonth();

        $operator->update([
            'status' => 'active',
            'last_paid_at' => now(),
            'next_due_at' => $nextDue,
            'locked_at' => null,
            'lock_reason' => null,
        ]);
    }

    public function lock(SaasOperator $operator, string $reason = 'unpaid'): void
    {
        $operator->update([
            'status' => 'locked',
            'locked_at' => now(),
            'lock_reason' => $reason,
        ]);
    }

    public function suspend(SaasOperator $operator): void
    {
        $operator->update([
            'status' => 'suspended',
            'locked_at' => now(),
            'lock_reason' => 'suspended',
        ]);
    }

    public function unlock(SaasOperator $operator): void
    {
        $operator->update([
            'status' => 'active',
            'locked_at' => null,
            'lock_reason' => null,
        ]);
    }

    public function refreshLock(SaasOperator $operator): void
    {
        if ($operator->isLifetime() || $operator->status === 'suspended') {
            return;
        }

        $overdue = $operator->invoices()
            ->where('status', '!=', 'paid')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->startOfDay())
            ->exists();

        $pastDue = $operator->next_due_at && $operator->next_due_at->copy()->startOfDay()->isPast();

        if ($overdue || $pastDue) {
            if ($operator->status !== 'locked') {
                $this->lock($operator, 'unpaid');
            }
        } elseif ($operator->status === 'locked' && $operator->lock_reason === 'unpaid') {
            $open = $operator->invoices()->where('status', '!=', 'paid')->exists();
            if (! $open && (! $operator->next_due_at || $operator->next_due_at->isFuture())) {
                $this->unlock($operator);
            }
        }
    }

    public function lockOverdue(): int
    {
        $count = 0;
        foreach (SaasOperator::query()->where('status', '!=', 'suspended')->get() as $operator) {
            $before = $operator->status;
            $this->refreshLock($operator);
            if ($operator->fresh()?->status === 'locked' && $before !== 'locked') {
                $count++;
            }
        }

        return $count;
    }
}
