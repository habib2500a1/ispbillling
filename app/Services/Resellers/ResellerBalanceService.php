<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerBalanceTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResellerBalanceService
{
    public function transfer(
        ?Reseller $from,
        Reseller $to,
        float $amount,
        ?string $notes = null,
        string $type = ResellerBalanceTransfer::TYPE_TRANSFER,
    ): ResellerBalanceTransfer {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        if ($from !== null && (float) $from->wallet_balance < $amount) {
            throw ValidationException::withMessages(['amount' => 'Insufficient reseller wallet balance.']);
        }

        return DB::transaction(function () use ($from, $to, $amount, $notes, $type): ResellerBalanceTransfer {
            if ($from !== null) {
                $from->decrement('wallet_balance', $amount);
            }

            $to->increment('wallet_balance', $amount);

            return ResellerBalanceTransfer::query()->create([
                'tenant_id' => $to->tenant_id,
                'from_reseller_id' => $from?->id,
                'to_reseller_id' => $to->id,
                'amount' => $amount,
                'transfer_type' => $type,
                'reference' => 'TRF-'.now()->format('YmdHis'),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function credit(
        Reseller $to,
        float $amount,
        string $type = ResellerBalanceTransfer::TYPE_CREDIT,
        ?string $reference = null,
        ?string $notes = null,
    ): ResellerBalanceTransfer {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($to, $amount, $type, $reference, $notes): ResellerBalanceTransfer {
            $to->increment('wallet_balance', $amount);

            return ResellerBalanceTransfer::query()->create([
                'tenant_id' => $to->tenant_id,
                'from_reseller_id' => null,
                'to_reseller_id' => $to->id,
                'amount' => $amount,
                'transfer_type' => $type,
                'reference' => $reference ?? 'CRD-'.now()->format('YmdHis'),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function debit(Reseller $from, float $amount, ?string $notes = null): ResellerBalanceTransfer
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        if ((float) $from->wallet_balance < $amount) {
            throw ValidationException::withMessages(['amount' => 'Insufficient balance.']);
        }

        return DB::transaction(function () use ($from, $amount, $notes): ResellerBalanceTransfer {
            $from->decrement('wallet_balance', $amount);

            return ResellerBalanceTransfer::query()->create([
                'tenant_id' => $from->tenant_id,
                'from_reseller_id' => $from->id,
                'to_reseller_id' => $from->id,
                'amount' => $amount,
                'transfer_type' => ResellerBalanceTransfer::TYPE_DEBIT,
                'reference' => 'DBT-'.now()->format('YmdHis'),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }
}
