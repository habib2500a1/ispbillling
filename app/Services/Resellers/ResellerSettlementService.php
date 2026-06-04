<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerSettlement;
use App\Models\User;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResellerSettlementService
{
    public function outstandingBalance(Reseller $reseller): float
    {
        $pendingCommission = (float) $reseller->commissions()
            ->where('status', 'pending')
            ->sum('commission_amount');

        $pendingSettlements = (float) $reseller->settlements()
            ->where('status', ResellerSettlement::STATUS_PENDING)
            ->sum('net_amount');

        return round((float) $reseller->wallet_balance + $pendingCommission - $pendingSettlements, 2);
    }

    public function submitRequest(
        Reseller $reseller,
        float $amount,
        ?string $notes = null,
        float $expenseDeduction = 0,
        ?string $paymentMethod = null,
        ?string $reference = null,
        ?User $submittedBy = null,
    ): ResellerSettlement {
        $amount = round(max(0, $amount), 2);
        $expenseDeduction = round(max(0, $expenseDeduction), 2);
        $net = round(max(0, $amount - $expenseDeduction), 2);

        if ($net <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Settlement amount must be greater than expense deductions.',
            ]);
        }

        if ($reseller->wallet_frozen) {
            throw ValidationException::withMessages([
                'wallet' => 'Your wallet is frozen. Contact admin to request settlement.',
            ]);
        }

        if ($net > $this->outstandingBalance($reseller) + 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Requested amount exceeds available balance (wallet + pending commission).',
            ]);
        }

        return DB::transaction(function () use ($reseller, $amount, $expenseDeduction, $net, $notes, $paymentMethod, $reference, $submittedBy): ResellerSettlement {
            return ResellerSettlement::query()->create([
                'tenant_id' => $reseller->tenant_id ?? TenantResolver::requiredTenantId(),
                'reseller_id' => $reseller->id,
                'settlement_number' => ResellerSettlement::generateNumber((int) $reseller->tenant_id),
                'amount' => $amount,
                'expense_deduction' => $expenseDeduction,
                'net_amount' => $net,
                'status' => ResellerSettlement::STATUS_PENDING,
                'payment_method' => $paymentMethod,
                'reference' => $reference,
                'notes' => $notes,
                'submitted_by' => $submittedBy?->id,
                'submitted_at' => now(),
            ]);
        });
    }

    public function approve(ResellerSettlement $settlement, User $approver): ResellerSettlement
    {
        if ($settlement->status !== ResellerSettlement::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Only pending settlements can be approved.']);
        }

        $reseller = $settlement->reseller;
        if ($reseller === null) {
            throw ValidationException::withMessages(['reseller' => 'Reseller not found.']);
        }

        return DB::transaction(function () use ($settlement, $reseller, $approver): ResellerSettlement {
            $net = (float) $settlement->net_amount;
            if ($net > (float) $reseller->wallet_balance + 0.01) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient wallet balance to approve this settlement.',
                ]);
            }

            if ($net > 0) {
                app(ResellerBalanceService::class)->debit(
                    $reseller,
                    $net,
                    'Settlement '.$settlement->settlement_number.' approved by '.$approver->name,
                );
            }

            $settlement->update([
                'status' => ResellerSettlement::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $settlement->fresh();
        });
    }

    public function reject(ResellerSettlement $settlement, User $approver, string $reason): ResellerSettlement
    {
        if ($settlement->status !== ResellerSettlement::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Only pending settlements can be rejected.']);
        }

        $settlement->update([
            'status' => ResellerSettlement::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $settlement->fresh();
    }
}
