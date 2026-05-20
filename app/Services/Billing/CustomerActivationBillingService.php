<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Automation\PrepaidWalletAutoSettleService;
use App\Support\BillingDefaults;
use Carbon\Carbon;

final class CustomerActivationBillingService
{
    public const CYCLE_THIS_MONTH = 'this_month';

    public const CYCLE_NEXT_MONTH = 'next_month';

    /**
     * Create first invoice when a subscriber is registered (prepaid / optional postpaid).
     *
     * @return array{invoice: ?Invoice, message: string, settled: bool}
     */
    public function issueFirstBillIfRequested(
        Customer $customer,
        string $firstBillCycle,
        bool $settlePrepaidWallet = true,
    ): array {
        $cycle = $firstBillCycle === self::CYCLE_NEXT_MONTH
            ? self::CYCLE_NEXT_MONTH
            : self::CYCLE_THIS_MONTH;

        if ($cycle === self::CYCLE_NEXT_MONTH) {
            return [
                'invoice' => null,
                'message' => 'First bill will generate on bill day ('.$customer->billing_day.') next cycle.',
                'settled' => false,
            ];
        }

        if (! $customer->shouldGenerateInvoice()) {
            return [
                'invoice' => null,
                'message' => 'Auto invoice is off or subscriber is not billable.',
                'settled' => false,
            ];
        }

        $customer->loadMissing('package');
        if ($customer->package === null) {
            return [
                'invoice' => null,
                'message' => 'No package — cannot create bill.',
                'settled' => false,
            ];
        }

        $reference = Carbon::parse($customer->joined_at ?? now())->startOfDay();
        if ($reference->isFuture()) {
            $reference = now()->startOfDay();
        }

        $invoice = InvoiceGenerator::generateForCustomer($customer, $reference, false, null);

        if ($invoice === null) {
            return [
                'invoice' => null,
                'message' => 'Bill for this period already exists or could not be created.',
                'settled' => false,
            ];
        }

        $settled = false;
        if ($settlePrepaidWallet
            && in_array($customer->billing_mode, ['prepaid', 'advance'], true)
            && config('billing.prepaid_wallet_auto_settle', true)) {
            $result = app(PrepaidWalletAutoSettleService::class)->settleForCustomer($customer->fresh(), false);
            $settled = $result['applied'] > 0;
            $customer->refresh();
        }

        $customer->refresh();
        $this->applyServiceValidityFromInvoice($customer, $invoice->fresh());

        return [
            'invoice' => $invoice->fresh(),
            'message' => 'First bill '.$invoice->invoice_number.' created. Line off after expire date.',
            'settled' => $settled,
        ];
    }

    /**
     * Align service_expires_at with billed period so line turns off after expire date.
     */
    public function applyServiceValidityFromInvoice(Customer $customer, ?Invoice $invoice): void
    {
        if ($invoice === null || $invoice->period_end === null) {
            return;
        }

        $expires = Carbon::parse($invoice->period_end)->toDateString();
        $customer->forceFill(['service_expires_at' => $expires])->saveQuietly();

        if (config('network.service_expiry_enforced', true)) {
            app(\App\Services\Network\NetworkAccessCoordinator::class)->syncCustomer($customer->fresh());
        }
    }

    /**
     * Bill day from activation date (day customer opened), not forced to 1st for everyone.
     */
    public static function defaultBillingDayForJoin(?string $joinedAt = null): int
    {
        return BillingDefaults::billingDayForActivation($joinedAt);
    }

    /**
     * Default first-bill option from billing mode.
     */
    public static function defaultFirstBillCycle(string $billingMode): string
    {
        if (in_array($billingMode, ['prepaid', 'advance'], true)) {
            return (string) config('billing.default_first_bill_cycle_prepaid', self::CYCLE_THIS_MONTH);
        }

        return (string) config('billing.default_first_bill_cycle_postpaid', self::CYCLE_NEXT_MONTH);
    }
}
