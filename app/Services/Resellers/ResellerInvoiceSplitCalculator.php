<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Reseller;
use App\Services\Billing\PackagePriceResolver;
use Carbon\Carbon;

/**
 * Splits customer invoice into retail (customer bill), wholesale (admin receivable), margin (reseller).
 */
final class ResellerInvoiceSplitCalculator
{
    /**
     * @return array{retail: float, wholesale: float, margin: float, retail_outstanding: float}
     */
    public function splitForInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer.package', 'customer.reseller', 'items']);
        $customer = $invoice->customer;
        if ($customer === null || $customer->reseller_id === null) {
            return ['retail' => 0.0, 'wholesale' => 0.0, 'margin' => 0.0, 'retail_outstanding' => 0.0];
        }

        $retail = round((float) $invoice->total, 2);
        $wholesale = app(ResellerWholesaleDebitService::class)->resolveAmount($invoice, $customer);
        $margin = max(0, round($retail - $wholesale, 2));
        $outstanding = max(0, round($retail - (float) $invoice->amount_paid, 2));

        return [
            'retail' => $retail,
            'wholesale' => $wholesale,
            'margin' => $margin,
            'retail_outstanding' => $outstanding,
        ];
    }

    /**
     * @return array{retail: float, wholesale: float, margin: float}
     */
    public function estimateForCustomer(Customer $customer, ?Carbon $referenceDate = null): array
    {
        if ($customer->reseller_id === null) {
            return ['retail' => 0.0, 'wholesale' => 0.0, 'margin' => 0.0];
        }

        $package = $customer->package;
        $reseller = $customer->reseller ?? Reseller::query()->find($customer->reseller_id);
        if ($package === null || $reseller === null) {
            return ['retail' => 0.0, 'wholesale' => 0.0, 'margin' => 0.0];
        }

        $date = $referenceDate ?? now();
        $retail = PackagePriceResolver::resolveCyclePrice($package, $customer, $date);
        $wholesale = app(ResellerWholesaleDebitService::class)->estimateForCustomer($customer, $date);

        return [
            'retail' => round($retail, 2),
            'wholesale' => round($wholesale, 2),
            'margin' => max(0, round($retail - $wholesale, 2)),
        ];
    }
}
