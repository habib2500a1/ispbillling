<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerBalanceTransfer;
use App\Models\ResellerWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResellerWalletLedgerService
{
    public function availableMainBalance(Reseller $reseller): float
    {
        $balance = (float) $reseller->wallet_balance;
        $credit = (float) $reseller->credit_limit;

        return $balance + max(0, $credit);
    }

    public function totalSpendable(Reseller $reseller): float
    {
        return $this->availableMainBalance($reseller) + (float) $reseller->bonus_wallet_balance;
    }

    public function isLowBalance(Reseller $reseller): bool
    {
        $threshold = $reseller->low_balance_threshold;
        if ($threshold === null || (float) $threshold <= 0) {
            return (float) $reseller->wallet_balance < 0;
        }

        return (float) $reseller->wallet_balance < (float) $threshold;
    }

    public function creditMain(
        Reseller $reseller,
        float $amount,
        string $transactionType,
        ?string $reference = null,
        ?string $notes = null,
        ?ResellerBalanceTransfer $transfer = null,
    ): ResellerWalletTransaction {
        return $this->adjust($reseller, ResellerWalletTransaction::WALLET_MAIN, $amount, ResellerWalletTransaction::DIRECTION_CREDIT, $transactionType, $reference, $notes, $transfer);
    }

    public function debitMain(
        Reseller $reseller,
        float $amount,
        string $transactionType,
        ?string $reference = null,
        ?string $notes = null,
        bool $allowCredit = true,
        ?ResellerBalanceTransfer $transfer = null,
    ): ResellerWalletTransaction {
        if (! $allowCredit && $this->availableMainBalance($reseller) < $amount) {
            throw ValidationException::withMessages(['wallet' => 'Insufficient main wallet balance.']);
        }

        if ($reseller->wallet_frozen) {
            throw ValidationException::withMessages(['wallet' => 'Wallet is frozen. Contact admin.']);
        }

        return $this->adjust($reseller, ResellerWalletTransaction::WALLET_MAIN, $amount, ResellerWalletTransaction::DIRECTION_DEBIT, $transactionType, $reference, $notes, $transfer);
    }

    public function creditBonus(
        Reseller $reseller,
        float $amount,
        string $transactionType,
        ?string $reference = null,
        ?string $notes = null,
    ): ResellerWalletTransaction {
        return $this->adjust($reseller, ResellerWalletTransaction::WALLET_BONUS, $amount, ResellerWalletTransaction::DIRECTION_CREDIT, $transactionType, $reference, $notes);
    }

    public function debitBonus(
        Reseller $reseller,
        float $amount,
        string $transactionType,
        ?string $reference = null,
        ?string $notes = null,
    ): ResellerWalletTransaction {
        if ((float) $reseller->bonus_wallet_balance < $amount) {
            throw ValidationException::withMessages(['wallet' => 'Insufficient bonus wallet balance.']);
        }

        return $this->adjust($reseller, ResellerWalletTransaction::WALLET_BONUS, $amount, ResellerWalletTransaction::DIRECTION_DEBIT, $transactionType, $reference, $notes);
    }

    /**
     * Debit main wallet (may go negative within credit limit), then bonus if needed.
     */
    public function debitAuto(
        Reseller $reseller,
        float $amount,
        string $transactionType,
        ?string $reference = null,
        ?string $notes = null,
        ?ResellerBalanceTransfer $transfer = null,
    ): ResellerWalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        if ($this->totalSpendable($reseller) < $amount) {
            throw ValidationException::withMessages(['wallet' => 'Insufficient wallet balance (including credit limit and bonus).']);
        }

        return DB::transaction(function () use ($reseller, $amount, $transactionType, $reference, $notes, $transfer): ResellerWalletTransaction {
            $locked = Reseller::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($reseller->id);
            $mainBalance = (float) $locked->wallet_balance;
            $credit = max(0, (float) $locked->credit_limit);
            $fromMain = min($amount, $mainBalance + $credit);
            $remainder = $amount - $fromMain;

            $lastTx = null;
            if ($fromMain > 0) {
                $lastTx = $this->debitMain($locked->fresh(), $fromMain, $transactionType, $reference, $notes, true, $transfer);
            }
            if ($remainder > 0) {
                $lastTx = $this->debitBonus($locked->fresh(), $remainder, $transactionType, $reference, $notes);
            }

            return $lastTx ?? $this->debitMain($locked->fresh(), $amount, $transactionType, $reference, $notes, true, $transfer);
        });
    }

    private function adjust(
        Reseller $reseller,
        string $walletType,
        float $amount,
        string $direction,
        string $transactionType,
        ?string $reference,
        ?string $notes,
        ?ResellerBalanceTransfer $transfer = null,
    ): ResellerWalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($reseller, $walletType, $amount, $direction, $transactionType, $reference, $notes, $transfer): ResellerWalletTransaction {
            $reseller = Reseller::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($reseller->id);
            $column = $walletType === ResellerWalletTransaction::WALLET_BONUS ? 'bonus_wallet_balance' : 'wallet_balance';

            if ($direction === ResellerWalletTransaction::DIRECTION_CREDIT) {
                $reseller->increment($column, $amount);
            } else {
                $reseller->decrement($column, $amount);
            }

            $balanceAfter = (float) $reseller->fresh()->{$column};

            return ResellerWalletTransaction::query()->create([
                'tenant_id' => $reseller->tenant_id,
                'reseller_id' => $reseller->id,
                'wallet_type' => $walletType,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'transaction_type' => $transactionType,
                'reference' => $reference,
                'notes' => $notes,
                'related_transfer_id' => $transfer?->id,
                'created_by' => auth()->id(),
            ]);
        });
    }
}
