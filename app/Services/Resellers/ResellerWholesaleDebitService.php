<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Reseller;
use App\Models\ResellerBalanceTransfer;
use App\Services\Billing\PackagePriceResolver;
use App\Services\Billing\ProrationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * When a subscriber invoice is created, debit the reseller wallet for the admin wholesale rate.
 */
final class ResellerWholesaleDebitService
{
    public function isEnabled(): bool
    {
        return (bool) config('reseller.wholesale_debit.enabled', true);
    }

    /**
     * @return array{transfer: ?ResellerBalanceTransfer, amount: float, message: string, debited: bool}
     */
    public function debitForInvoice(Invoice $invoice): array
    {
        if (! $this->isEnabled()) {
            return $this->result(null, 0, '', false);
        }

        $invoice->loadMissing(['customer.package', 'customer.reseller', 'items']);
        $customer = $invoice->customer;
        if ($customer === null || $customer->reseller_id === null) {
            return $this->result(null, 0, '', false);
        }

        $reseller = $customer->reseller;
        if ($reseller !== null && $this->shouldSkipWalletDebit($reseller)) {
            return $this->result(null, 0, 'Postpaid due account — wholesale accrues to admin receivable.', false);
        }

        $amount = $this->resolveAmount($invoice, $customer);
        if ($amount <= 0) {
            return $this->result(null, 0, '', false);
        }

        $reseller = $customer->reseller;
        if ($reseller === null || ! $reseller->is_active) {
            return $this->result(null, $amount, 'Reseller account inactive — wholesale not debited.', false);
        }

        $reference = $this->referenceFor($invoice);

        $existing = ResellerBalanceTransfer::query()
            ->where('transfer_type', ResellerBalanceTransfer::TYPE_WHOLESALE_DEBIT)
            ->where('reference', $reference)
            ->first();

        if ($existing !== null) {
            return $this->result(
                $existing,
                $amount,
                'Wholesale already debited for this bill.',
                true,
            );
        }

        if ($reseller->wallet_frozen) {
            $this->notifyInsufficient($reseller, $customer, $invoice, $amount, 'Wallet frozen');

            return $this->result(null, $amount, 'Wallet frozen — wholesale '.number_format($amount, 2).' BDT not debited.', false);
        }

        if ((float) $reseller->wallet_balance + 0.009 < $amount) {
            $this->notifyInsufficient($reseller, $customer, $invoice, $amount, 'Insufficient balance');

            return $this->result(
                null,
                $amount,
                'Insufficient wallet ('.number_format((float) $reseller->wallet_balance, 2).' BDT) for wholesale '.number_format($amount, 2).' BDT.',
                false,
            );
        }

        $transfer = DB::transaction(function () use ($reseller, $amount, $reference, $invoice, $customer): ResellerBalanceTransfer {
            $transfer = app(ResellerBalanceService::class)->debit(
                $reseller,
                $amount,
                sprintf(
                    'Wholesale for bill %s · %s (%s)',
                    $invoice->invoice_number,
                    $customer->customer_code,
                    $customer->name,
                ),
                ResellerBalanceTransfer::TYPE_WHOLESALE_DEBIT,
                $reference,
            );

            app(ResellerPortalActivityLogger::class)->log(
                $reseller,
                'wallet.wholesale_debit',
                $invoice,
                [
                    'amount' => $amount,
                    'customer_code' => $customer->customer_code,
                    'transfer_id' => $transfer->id,
                ],
            );

            return $transfer;
        });

        app(ResellerPortalNotifier::class)->wholesaleDebited(
            $reseller,
            $amount,
            $invoice->invoice_number,
            $customer->customer_code,
        );

        return $this->result(
            $transfer,
            $amount,
            'Wholesale '.number_format($amount, 2).' BDT debited from wallet.',
            true,
        );
    }

    public function estimateForCustomer(Customer $customer, ?Carbon $referenceDate = null, bool $noProrate = false): float
    {
        if (! $this->isEnabled() || $customer->reseller_id === null) {
            return 0.0;
        }

        $customer->loadMissing(['package', 'reseller']);
        $package = $customer->package;
        $reseller = $customer->reseller;
        if ($package === null || $reseller === null) {
            return 0.0;
        }

        $monthly = app(ResellerPackageCatalogService::class)->wholesalePriceFor($reseller, $package);
        if ($monthly === null || $monthly <= 0) {
            return 0.0;
        }

        $referenceDate ??= Carbon::today();
        $cycleWholesale = PackagePriceResolver::scaleToCycle($monthly, $package);

        if ($noProrate || ! config('reseller.wholesale_debit.prorate_with_invoice', true)) {
            return round($cycleWholesale, 2);
        }

        if ($customer->joined_at === null) {
            return round($cycleWholesale, 2);
        }

        [$periodStart, $periodEnd] = \App\Services\Billing\BillingPeriodResolver::resolve($package, $referenceDate);
        $joined = Carbon::parse($customer->joined_at)->startOfDay();

        if ($joined->lte($periodStart)) {
            return round($cycleWholesale, 2);
        }

        return ProrationService::proratedAmount(
            $cycleWholesale,
            $periodStart,
            $periodEnd,
            $joined,
        );
    }

    public function assertWalletCanCover(Customer $customer, ?Carbon $referenceDate = null, bool $noProrate = false): void
    {
        if (! $this->isEnabled() || ! config('reseller.wholesale_debit.block_on_insufficient_balance', false)) {
            return;
        }

        $customer->loadMissing('reseller');
        if ($customer->reseller !== null && $this->shouldSkipWalletDebit($customer->reseller)) {
            return;
        }

        $amount = $this->estimateForCustomer($customer, $referenceDate, $noProrate);
        if ($amount <= 0) {
            return;
        }

        $customer->loadMissing('reseller');
        $reseller = $customer->reseller;
        if ($reseller === null) {
            return;
        }

        if ($reseller->wallet_frozen) {
            throw ValidationException::withMessages([
                'wallet' => 'Your wallet is frozen. Contact admin before generating bills.',
            ]);
        }

        if ((float) $reseller->wallet_balance + 0.009 < $amount) {
            throw ValidationException::withMessages([
                'wallet' => 'Insufficient wallet balance. Need '.number_format($amount, 2).' BDT wholesale for this bill. Top up your wallet first.',
            ]);
        }
    }

    public function resolveAmount(Invoice $invoice, Customer $customer): float
    {
        $package = $customer->package;
        $reseller = $customer->reseller;
        if ($package === null || $reseller === null) {
            return 0.0;
        }

        $monthly = app(ResellerPackageCatalogService::class)->wholesalePriceFor($reseller, $package);
        if ($monthly === null || $monthly <= 0) {
            return 0.0;
        }

        $cycleWholesale = PackagePriceResolver::scaleToCycle($monthly, $package);

        if (! config('reseller.wholesale_debit.prorate_with_invoice', true)) {
            return round($cycleWholesale, 2);
        }

        /** @var InvoiceItem|null $packageItem */
        $packageItem = $invoice->items->firstWhere('item_type', 'package');
        if ($packageItem === null) {
            return round($cycleWholesale, 2);
        }

        $reference = Carbon::parse($invoice->period_start ?? $invoice->issue_date ?? now());
        $fullCycleCustomer = PackagePriceResolver::resolveCyclePrice($package, $customer, $reference);
        if ($fullCycleCustomer <= 0) {
            return round($cycleWholesale, 2);
        }

        $ratio = min(1.0, (float) $packageItem->unit_price / $fullCycleCustomer);

        return round($cycleWholesale * $ratio, 2);
    }

    public function messageForInvoice(Invoice $invoice): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $invoice->loadMissing(['customer.package', 'customer.reseller', 'items']);
        $customer = $invoice->customer;
        if ($customer === null || $customer->reseller_id === null) {
            return '';
        }

        $reference = $this->referenceFor($invoice);
        $transfer = ResellerBalanceTransfer::query()
            ->where('transfer_type', ResellerBalanceTransfer::TYPE_WHOLESALE_DEBIT)
            ->where('reference', $reference)
            ->first();

        if ($transfer !== null) {
            return 'Wholesale '.number_format((float) $transfer->amount, 2).' BDT debited from your wallet.';
        }

        $amount = $this->resolveAmount($invoice, $customer);
        if ($amount > 0) {
            return 'Wholesale '.number_format($amount, 2).' BDT not debited — top up wallet.';
        }

        return '';
    }

    private function referenceFor(Invoice $invoice): string
    {
        return 'WHOLESALE-INV-'.$invoice->id;
    }

    private function notifyInsufficient(
        Reseller $reseller,
        Customer $customer,
        Invoice $invoice,
        float $amount,
        string $reason,
    ): void {
        app(ResellerPortalNotifier::class)->wholesaleDebitFailed(
            $reseller,
            $amount,
            $invoice->invoice_number,
            $customer->customer_code,
            $reason,
        );

        app(ResellerPortalActivityLogger::class)->log(
            $reseller,
            'wallet.wholesale_debit_failed',
            $invoice,
            [
                'amount' => $amount,
                'reason' => $reason,
                'customer_code' => $customer->customer_code,
            ],
        );
    }

    /**
     * @return array{transfer: ?ResellerBalanceTransfer, amount: float, message: string, debited: bool}
     */
    private function shouldSkipWalletDebit(\App\Models\Reseller $reseller): bool
    {
        if (! config('reseller_billing.hierarchical_enabled', true)) {
            return false;
        }

        $mode = $reseller->billing_settlement_mode
            ?? config('reseller_billing.default_settlement_mode', 'postpaid_due');

        return $mode === 'postpaid_due';
    }

    /**
     * @return array{transfer: ?ResellerBalanceTransfer, amount: float, message: string, debited: bool}
     */
    private function result(?ResellerBalanceTransfer $transfer, float $amount, string $message, bool $debited): array
    {
        return [
            'transfer' => $transfer,
            'amount' => $amount,
            'message' => $message,
            'debited' => $debited,
        ];
    }
}
