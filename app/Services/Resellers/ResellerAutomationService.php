<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Services\Resellers\ResellerSubscriberSyncService;

final class ResellerAutomationService
{
    public function __construct(
        private readonly ResellerWalletLedgerService $ledger,
        private readonly ResellerPortalNotifier $notifier,
        private readonly ResellerSubscriberSyncService $subscriberSync,
    ) {}

    public function handleWalletRecharge(Reseller $reseller, float $amount): void
    {
        if (! $reseller->auto_restore_on_recharge) {
            return;
        }

        if (! $reseller->is_active && (float) $reseller->wallet_balance >= 0) {
            $reseller->forceFill(['is_active' => true])->save();
            $this->subscriberSync->handleResellerActiveChange($reseller, false, true);
        }

        $this->notifier->walletCredited($reseller, $amount, 'auto-restore');
    }

    public function evaluateLowBalance(Reseller $reseller): void
    {
        if (! $reseller->auto_suspend_on_low_balance) {
            return;
        }

        if (! $this->ledger->isLowBalance($reseller)) {
            return;
        }

        if (! $reseller->is_active) {
            return;
        }

        $reseller->forceFill(['is_active' => false])->save();
        $this->subscriberSync->handleResellerActiveChange($reseller, true, false);
        $this->notifier->lowBalanceSuspended($reseller);
    }
}
